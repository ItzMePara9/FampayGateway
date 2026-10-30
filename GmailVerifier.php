<?php

namespace Paymenter\Extensions\Gateways\FampayGateway;

use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class GmailVerifier
{
    private string $email;
    private string $appPassword;
    private string $senderEmail;
    private bool $debug;
    private ?DiscordLogger $discord;
    private ?int $invoiceId;

    private const IMAP_HOST = '{imap.gmail.com:993/imap/ssl}INBOX';

    public function __construct(
        string $email,
        string $appPassword,
        string $senderEmail = 'no-reply@famapp.in',
        bool $debug = false,
        ?DiscordLogger $discord = null,
        ?int $invoiceId = null
    ) {
        $this->email = $email;
        $this->appPassword = $appPassword;
        $this->senderEmail = $senderEmail;
        $this->debug = $debug;
        $this->discord = $discord;
        $this->invoiceId = $invoiceId;
    }

    /**
     * Safe logging — never crashes even if log file permissions are broken.
     */
    private function log(string $level, string $message, array $context = []): void
    {
        try {
            Log::$level($message, $context);
        } catch (\Throwable $e) {
            // Silently ignore log failures — payment flow must not break
        }
    }

    /**
     * Open an IMAP connection to Gmail.
     *
     * @return resource|\IMAP\Connection
     * @throws \Exception
     */
    private function connect()
    {
        $mailbox = @imap_open(
            self::IMAP_HOST,
            $this->email,
            $this->appPassword,
            0,
            1
        );

        if (!$mailbox) {
            $error = imap_last_error();
            throw new \Exception('IMAP connection failed: ' . ($error ?: 'Unknown error'));
        }

        return $mailbox;
    }

    /**
     * Test the IMAP connection — returns true if successful.
     */
    public function testConnection(): bool
    {
        $mailbox = $this->connect();
        $check = imap_check($mailbox);
        imap_close($mailbox);

        return $check !== false;
    }

    /**
     * Get the connected email address (for display in test results).
     */
    public function getEmailAddress(): string
    {
        return $this->email;
    }

    /**
     * Count FamPay emails received in the last N hours.
     */
    public function countRecentEmails(int $hours = 24): int
    {
        $mailbox = $this->connect();

        $since = Carbon::now()->subHours($hours)->format('d-M-Y');
        $criteria = 'FROM "' . $this->senderEmail . '" SINCE "' . $since . '"';

        $messages = @imap_search($mailbox, $criteria, SE_UID);
        $count = $messages ? count($messages) : 0;

        imap_close($mailbox);

        return $count;
    }

    /**
     * Get parsed details from the latest FamPay email (for test page preview).
     */
    public function getLatestEmailPreview(string $senderEmail): ?array
    {
        $mailbox = $this->connect();

        $since = Carbon::now()->subDays(7)->format('d-M-Y');
        $criteria = 'FROM "' . $senderEmail . '" SINCE "' . $since . '"';
        $messages = @imap_search($mailbox, $criteria, SE_UID);

        if (!$messages || empty($messages)) {
            imap_close($mailbox);
            return null;
        }

        $latestUid = end($messages);
        $headerInfo = @imap_headerinfo($mailbox, imap_msgno($mailbox, $latestUid));

        $result = [
            'date' => $headerInfo->date ?? 'N/A',
            'subject' => isset($headerInfo->subject) ? imap_utf8($headerInfo->subject) : 'N/A',
            'from' => $headerInfo->fromaddress ?? 'N/A',
        ];

        $body = $this->getEmailBody($mailbox, $latestUid);

        if (!empty($body)) {
            $payment = $this->extractPaymentFromBody($body);
            if ($payment) {
                $result['amount'] = '₹' . number_format($payment['amount'], 2);
                $result['sender'] = $payment['sender'] ?? 'N/A';
                $result['transaction_id'] = $payment['transactionId'] ?? 'N/A';
                $result['utr'] = $payment['utr'] ?? 'N/A';
                $result['purpose_note'] = $payment['purpose'] ?? 'N/A';
            } else {
                $result['parsing'] = '⚠️ Could not extract payment details from email body';
            }
        }

        imap_close($mailbox);
        return $result;
    }

    /**
     * Check Gmail for a FamPay payment email matching the expected amount and time window.
     *
     * Matching logic (two-pass for concurrent payment safety):
     *
    * PASS 1 (strict): Amount + Note MUST both match
    *   - Note format is digits only, derived from the selected invoice source
     *   - If found → confirm immediately
     *
     * PASS 2 (fallback): Amount only match
     *   - Only runs if Pass 1 found nothing
     *   - Used when payer didn't include the note
     *   - Skips transaction IDs already claimed by other invoices
     *
     * Transaction ID deduplication:
     *   - Each confirmed transaction ID is cached for 30 minutes
     *   - Prevents two invoices from claiming the same payment email
     *
     * @param float $expectedAmount The invoice amount
    * @param string $expectedNote The expected payment note (e.g., "1234")
     * @param Carbon $payStartedAt When the user clicked Pay
     * @param int $timeoutMinutes Maximum age of payment in minutes
     * @return array|null Payment details if found, null otherwise
     */
    public function checkForPayment(
        float $expectedAmount,
        string $expectedNote,
        Carbon $payStartedAt,
        int $timeoutMinutes = 10
    ): ?array {
        $mailbox = $this->connect();

        // Search for FamPay emails since payment was started
        // Try multiple search strategies to be robust
        $since = $payStartedAt->format('d-M-Y');
        $messages = null;

        // Strategy 1: Search by sender email
        $criteria1 = 'FROM "' . $this->senderEmail . '" SINCE "' . $since . '"';
        if ($this->debug) {
            $this->log('debug', 'FampayGateway: IMAP search strategy 1', ['criteria' => $criteria1]);
        }
        $messages = @imap_search($mailbox, $criteria1, SE_UID);

        // Strategy 2: Search by subject keyword if sender search fails
        if (!$messages || empty($messages)) {
            $criteria2 = 'SUBJECT "received" SINCE "' . $since . '"';
            if ($this->debug) {
                $this->log('debug', 'FampayGateway: IMAP search strategy 2 (subject fallback)', ['criteria' => $criteria2]);
            }
            $messages = @imap_search($mailbox, $criteria2, SE_UID);
        }

        // Strategy 3: Search all recent emails as last resort
        if (!$messages || empty($messages)) {
            $criteria3 = 'SINCE "' . $since . '"';
            if ($this->debug) {
                $this->log('debug', 'FampayGateway: IMAP search strategy 3 (all recent)', ['criteria' => $criteria3]);
            }
            $messages = @imap_search($mailbox, $criteria3, SE_UID);
        }

        // Discord: Log email checking
        if ($this->discord && $this->invoiceId) {
            $this->discord->logCheckingMail($this->invoiceId, $expectedAmount, $expectedNote, $criteria1);
        }

        if (!$messages || empty($messages)) {
            if ($this->debug) {
                $this->log('debug', 'FampayGateway: No emails found with any search strategy');
            }
            if ($this->discord && $this->invoiceId) {
                $this->discord->logNoEmailsFound($this->invoiceId, 0);
            }
            imap_close($mailbox);
            return null;
        }

        if ($this->debug) {
            $this->log('debug', 'FampayGateway: Found ' . count($messages) . ' candidate emails');
        }

        $expiresAt = $payStartedAt->copy()->addMinutes($timeoutMinutes);

        // Collect all valid candidates from emails
        $candidates = [];

        foreach ($messages as $uid) {
            $headerInfo = @imap_headerinfo($mailbox, imap_msgno($mailbox, $uid));
            if (!$headerInfo) continue;

            $emailDate = isset($headerInfo->date) ? Carbon::parse($headerInfo->date) : null;
            if (!$emailDate) continue;

            // Skip emails outside the payment window (with 2 min grace before start)
            if ($emailDate->greaterThan($expiresAt)) continue;
            if ($emailDate->lessThan($payStartedAt->copy()->subMinutes(2))) continue;

            // Get and parse the email body
            $body = $this->getEmailBody($mailbox, $uid);
            if (empty($body)) continue;

            if ($this->debug) {
                $this->log('debug', 'FampayGateway: Raw email body (first 300 chars)', [
                    'uid' => $uid,
                    'date' => $emailDate->toDateTimeString(),
                    'body_preview' => substr($body, 0, 300),
                ]);
            }

            $payment = $this->extractPaymentFromBody($body);
            if (!$payment) continue;

            if ($this->debug) {
                $this->log('debug', 'FampayGateway: Parsed payment email', $payment);
            }

            // Check amount match (within ₹1.00 tolerance for rounding)
            $amountMatch = abs($payment['amount'] - $expectedAmount) <= 1.00;

            // Check note match — looks for the expected digits in the purpose field
            $noteMatched = false;
            if (!empty($payment['purpose']) && !empty($expectedNote)) {
                $noteMatched = stripos($payment['purpose'], $expectedNote) !== false;
            }

            // Discord: Log every parsed email
            if ($this->discord && $this->invoiceId) {
                $this->discord->logEmailFound(
                    $this->invoiceId, $payment, $expectedAmount, $amountMatch, $noteMatched
                );
            }

            if (!$amountMatch) continue;

            // Check if this transaction ID was already claimed by another invoice
            $txnId = $payment['transactionId'];
            $cacheKey = 'fampay_txn_' . $txnId;
            $claimedBy = cache($cacheKey);

            if ($claimedBy && $claimedBy != $this->invoiceId) {
                if ($this->debug) {
                    $this->log('debug', 'FampayGateway: Transaction already claimed', [
                        'txn_id' => $txnId,
                        'claimed_by_invoice' => $claimedBy,
                        'current_invoice' => $this->invoiceId,
                    ]);
                }
                continue;
            }

            $candidates[] = [
                'uid' => $uid,
                'payment' => $payment,
                'noteMatched' => $noteMatched,
                'emailDate' => $emailDate,
            ];
        }

        if (empty($candidates)) {
            if ($this->discord && $this->invoiceId) {
                $this->discord->logNoEmailsFound($this->invoiceId, count($messages));
            }
            imap_close($mailbox);
            return null;
        }

        // Require an exact note match. Do NOT accept amount-only matches.
        $match = null;
        foreach ($candidates as $c) {
            if ($c['noteMatched']) {
                $match = $c;
                break;
            }
        }

        if (!$match) {
            // No exact note match — abort to avoid ambiguous amount-only confirmations
            $this->log('warning', 'FampayGateway: No exact note match found; aborting to avoid ambiguous matches', [
                'invoice_id' => $this->invoiceId,
                'candidate_count' => count($candidates),
                'expected_note' => $expectedNote,
            ]);
            if ($this->discord && $this->invoiceId) {
                $this->discord->logError(
                    $this->invoiceId,
                    'No email contained expected note "' . $expectedNote . '". Waiting for exact match.'
                );
            }
            imap_close($mailbox);
            return null;
        }

        // Claim this transaction ID in cache (30 min TTL)
        $txnId = $match['payment']['transactionId'];
        cache(['fampay_txn_' . $txnId => $this->invoiceId], now()->addMinutes(30));

        // Mark the email as read
        @imap_setflag_full($mailbox, (string) $match['uid'], '\\Seen', ST_UID);

        imap_close($mailbox);

        return [
            'amount' => $match['payment']['amount'],
            'transactionId' => $txnId,
            'sender' => $match['payment']['sender'],
            'purpose' => $match['payment']['purpose'],
            'noteMatched' => $match['noteMatched'],
            'paymentTime' => $match['emailDate']->toDateTimeString(),
        ];
    }

    /**
     * Parse a FamPay payment notification email to extract payment details.
     * Uses multiple regex patterns for robustness.
     */
    private function extractPaymentFromBody(string $body): ?array
    {
        // Normalize whitespace
        $body = preg_replace('/\s+/', ' ', $body);

        // Extract amount — multiple patterns
        $amount = null;
        $amountPatterns = [
            '/₹\s?([\d,]+(?:\.\d+)?)/',                    // ₹700.0 or ₹1,500.00
            '/Rs\.?\s?([\d,]+(?:\.\d+)?)/i',               // Rs. 700 or Rs 1500
            '/INR\s?([\d,]+(?:\.\d+)?)/i',                  // INR 700.00
            '/received\s+(?:₹|Rs\.?|INR)?\s?([\d,]+(?:\.\d+)?)/i', // received 700
            '/([\d,]+(?:\.\d+)?)\s*(?:₹|rupees?)/i',        // 700 rupees
        ];
        foreach ($amountPatterns as $pattern) {
            if (preg_match($pattern, $body, $m)) {
                $amount = (float) str_replace(',', '', $m[1]);
                if ($amount > 0) break;
                $amount = null;
            }
        }
        if (!$amount || $amount <= 0) {
            return null;
        }

        // Extract sender name
        $sender = null;
        $senderPatterns = [
            '/from\s+(.+?)(?:\s*Transaction|\s*\.|\s*UTR|\s*$)/is',
            '/received.*?from\s+([A-Za-z\s]+)/is',
            '/sender[:\s]+([A-Za-z\s]+)/is',
        ];
        foreach ($senderPatterns as $p) {
            if (preg_match($p, $body, $m)) {
                $sender = trim($m[1]);
                if (strlen($sender) > 2 && strlen($sender) < 80) break;
                $sender = null;
            }
        }

        // Extract Transaction ID
        $transactionId = null;
        $txnPatterns = [
            '/Transaction\s*ID\s*[:\-]?\s*([A-Z0-9]+)/i',
            '/Txn\s*(?:ID|No|Number)\s*[:\-]?\s*([A-Z0-9]+)/i',
            '/FMP[A-Z]*\d+/',  // FamPay format like FMPIB5453837444
            '/Reference\s*(?:ID|No|Number)?\s*[:\-]?\s*([A-Z0-9]+)/i',
        ];
        foreach ($txnPatterns as $p) {
            if (preg_match($p, $body, $m)) {
                $transactionId = trim($m[1] ?? $m[0]);
                if (strlen($transactionId) > 4) break;
                $transactionId = null;
            }
        }

        // Extract UTR
        $utr = null;
        if (preg_match('/UTR\s*[:\-]?\s*(\d{8,})/i', $body, $m)) {
            $utr = trim($m[1]);
        } elseif (preg_match('/\b(\d{12,})\b/', $body, $m)) {
            $utr = trim($m[1]); // Long number likely to be UTR
        }

        // Extract Purpose/Note
        $purpose = null;
        $purposePatterns = [
            '/Purpose\s*[:\-]?\s*(.+?)(?:\s*If|\s*Transaction|\s*$)/is',
            '/Note\s*[:\-]?\s*(.+?)(?:\s*If|\s*Transaction|\s*$)/is',
            '/Remark\s*[:\-]?\s*(.+?)(?:\s*If|\s*Transaction|\s*$)/is',
            '/Message\s*[:\-]?\s*(.+?)(?:\s*If|\s*Transaction|\s*$)/is',
        ];
        foreach ($purposePatterns as $p) {
            if (preg_match($p, $body, $m)) {
                $purpose = trim($m[1]);
                if (strlen($purpose) > 1 && strlen($purpose) < 200) break;
                $purpose = null;
            }
        }

        return [
            'amount' => $amount,
            'sender' => $sender,
            'transactionId' => $transactionId ?? $utr ?? ('AZN-' . time()),
            'utr' => $utr,
            'purpose' => $purpose,
        ];
    }

    /**
     * Get the text body of an email by UID (handles multipart messages).
     */
    private function getEmailBody($mailbox, int $uid): string
    {
        $structure = @imap_fetchstructure($mailbox, $uid, FT_UID);

        if (!$structure) {
            return '';
        }

        // Simple (non-multipart) message
        if (empty($structure->parts)) {
            $body = @imap_fetchbody($mailbox, $uid, '1', FT_UID);
            return $this->decodeBody($body, $structure->encoding ?? 0);
        }

        // Multipart message — look for text/plain first, then text/html
        $plainText = '';
        $htmlText = '';

        foreach ($structure->parts as $index => $part) {
            $partNumber = (string) ($index + 1);

            if ($part->type === 0) { // TEXT
                $body = @imap_fetchbody($mailbox, $uid, $partNumber, FT_UID);
                $decoded = $this->decodeBody($body, $part->encoding ?? 0);

                $subtype = strtolower($part->subtype ?? 'plain');
                if ($subtype === 'plain') {
                    $plainText = $decoded;
                } elseif ($subtype === 'html') {
                    $htmlText = $decoded;
                }
            }

            // Check nested multipart (e.g., multipart/alternative inside multipart/mixed)
            if ($part->type === 1 && !empty($part->parts)) {
                foreach ($part->parts as $subIndex => $subPart) {
                    $subPartNumber = $partNumber . '.' . ($subIndex + 1);

                    if ($subPart->type === 0) {
                        $body = @imap_fetchbody($mailbox, $uid, $subPartNumber, FT_UID);
                        $decoded = $this->decodeBody($body, $subPart->encoding ?? 0);

                        $subtype = strtolower($subPart->subtype ?? 'plain');
                        if ($subtype === 'plain' && empty($plainText)) {
                            $plainText = $decoded;
                        } elseif ($subtype === 'html' && empty($htmlText)) {
                            $htmlText = $decoded;
                        }
                    }
                }
            }
        }

        // Prefer plain text, fall back to HTML with tags stripped
        if (!empty($plainText)) {
            return $plainText;
        }

        if (!empty($htmlText)) {
            return strip_tags($htmlText);
        }

        return '';
    }

    /**
     * Decode an email body based on its transfer encoding.
     */
    private function decodeBody(string $body, int $encoding): string
    {
        switch ($encoding) {
            case 0: // 7BIT
            case 1: // 8BIT
                return $body;
            case 2: // BINARY
                return $body;
            case 3: // BASE64
                return base64_decode($body);
            case 4: // QUOTED-PRINTABLE
                return quoted_printable_decode($body);
            default:
                return $body;
        }
    }
}

