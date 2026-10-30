<?php

namespace Paymenter\Extensions\Gateways\FampayGateway;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DiscordLogger
{
    private ?string $logsWebhook;
    private ?string $paidWebhook;

    private const COLORS = [
        'info'    => 0x6366F1, // Indigo — general info
        'trigger' => 0x8B5CF6, // Purple — gateway triggered
        'search'  => 0x3B82F6, // Blue — searching emails
        'found'   => 0x06B6D4, // Cyan — email found
        'success' => 0x22C55E, // Green — payment confirmed
        'expired' => 0xEF4444, // Red — payment expired
        'error'   => 0xF59E0B, // Amber — error
        'test'    => 0x64748B, // Slate — test connection
    ];

    /**
     * @param string|null $logsWebhook Webhook for general logs (checking, errors, tests)
     * @param string|null $paidWebhook Webhook for confirmed/paid invoice embeds
     */
    public function __construct(?string $logsWebhook, ?string $paidWebhook = null)
    {
        $this->logsWebhook = $logsWebhook;
        $this->paidWebhook = $paidWebhook;
    }

    /**
     * Check if Discord logging is enabled.
     */
    public function isEnabled(): bool
    {
        return !empty($this->logsWebhook) || !empty($this->paidWebhook);
    }

    /**
     * Log when the gateway is triggered (user clicks Pay).
     */
    public function logGatewayTriggered(int $invoiceId, float $amount, string $note, string $upiId): void
    {
        $this->send('trigger', '🔔 Payment Gateway Triggered', [
            ['name' => 'Invoice ID', 'value' => "#{$invoiceId}", 'inline' => true],
            ['name' => 'Amount', 'value' => "₹" . number_format($amount, 2), 'inline' => true],
            ['name' => 'UPI ID', 'value' => "`{$upiId}`", 'inline' => true],
            ['name' => 'Payment Note', 'value' => $note, 'inline' => false],
        ]);
    }

    /**
     * Log when the system starts checking Gmail for payment.
     */
    public function logCheckingMail(int $invoiceId, float $expectedAmount, string $expectedNote, string $searchCriteria): void
    {
        $this->send('search', '📧 Checking Gmail for Payment', [
            ['name' => 'Invoice ID', 'value' => "#{$invoiceId}", 'inline' => true],
            ['name' => 'Expected Amount', 'value' => "₹" . number_format($expectedAmount, 2), 'inline' => true],
            ['name' => 'Expected Note', 'value' => $expectedNote, 'inline' => true],
            ['name' => 'IMAP Search', 'value' => "```{$searchCriteria}```", 'inline' => false],
        ]);
    }

    /**
     * Log when a potential FamPay email is found and parsed.
     */
    public function logEmailFound(int $invoiceId, array $payment, float $expectedAmount, bool $amountMatch, bool $noteMatch): void
    {
        $status = $amountMatch ? '✅ Amount Match' : '❌ Amount Mismatch';

        $this->send('found', "📬 FamPay Email Found — {$status}", [
            ['name' => 'Invoice ID', 'value' => "#{$invoiceId}", 'inline' => true],
            ['name' => 'Received Amount', 'value' => "₹" . number_format($payment['amount'], 2), 'inline' => true],
            ['name' => 'Expected Amount', 'value' => "₹" . number_format($expectedAmount, 2), 'inline' => true],
            ['name' => 'Sender', 'value' => $payment['sender'] ?? 'Unknown', 'inline' => true],
            ['name' => 'Transaction ID', 'value' => "`" . ($payment['transactionId'] ?? 'N/A') . "`", 'inline' => true],
            ['name' => 'UTR', 'value' => "`" . ($payment['utr'] ?? 'N/A') . "`", 'inline' => true],
            ['name' => 'Purpose/Note', 'value' => $payment['purpose'] ?? 'N/A', 'inline' => true],
            ['name' => 'Note Match', 'value' => $noteMatch ? '✅ Yes' : '⚠️ No (non-blocking)', 'inline' => true],
            ['name' => 'Amount Match', 'value' => $amountMatch ? '✅ Yes' : '❌ No', 'inline' => true],
        ]);
    }

    /**
     * Log when no matching emails were found in a check.
     */
    public function logNoEmailsFound(int $invoiceId, int $emailsScanned): void
    {
        $this->send('info', '📭 No Matching Payment Found', [
            ['name' => 'Invoice ID', 'value' => "#{$invoiceId}", 'inline' => true],
            ['name' => 'Emails Scanned', 'value' => (string) $emailsScanned, 'inline' => true],
            ['name' => 'Status', 'value' => 'Still waiting…', 'inline' => true],
        ]);
    }

    /**
     * Log when payment is confirmed and invoice is marked as paid.
     */
    public function logPaymentConfirmed(int $invoiceId, float $amount, string $transactionId, string $sender, bool $noteMatched): void
    {
        // Prefer the paid-specific webhook if configured, otherwise fall back to logs webhook
        $webhook = $this->paidWebhook ?? $this->logsWebhook;
        $this->send('success', '✅ Payment Confirmed & Invoice Paid', [
            ['name' => 'Invoice ID', 'value' => "#{$invoiceId}", 'inline' => true],
            ['name' => 'Amount Paid', 'value' => "₹" . number_format($amount, 2), 'inline' => true],
            ['name' => 'Transaction ID', 'value' => "`{$transactionId}`", 'inline' => true],
            ['name' => 'Paid By', 'value' => $sender, 'inline' => true],
            ['name' => 'Note Matched', 'value' => $noteMatched ? '✅ Yes' : '⚠️ No', 'inline' => true],
            ['name' => 'Status', 'value' => '**PAID**', 'inline' => true],
        ], webhook: $webhook);
    }

    /**
     * Log when a payment window expires.
     */
    public function logPaymentExpired(int $invoiceId, float $amount): void
    {
        $this->send('expired', '⏰ Payment Window Expired', [
            ['name' => 'Invoice ID', 'value' => "#{$invoiceId}", 'inline' => true],
            ['name' => 'Amount', 'value' => "₹" . number_format($amount, 2), 'inline' => true],
            ['name' => 'Status', 'value' => 'QR code expired, user did not pay in time', 'inline' => false],
        ]);
    }

    /**
     * Log errors during payment checking.
     */
    public function logError(int $invoiceId, string $errorMessage): void
    {
        $this->send('error', '⚠️ Payment Check Error', [
            ['name' => 'Invoice ID', 'value' => "#{$invoiceId}", 'inline' => true],
            ['name' => 'Error', 'value' => "```{$errorMessage}```", 'inline' => false],
        ]);
    }

    /**
     * Log test connection results.
     */
    public function logTestResult(array $results): void
    {
        $fields = [];
        foreach ($results as $key => $result) {
            $icon = match ($result['status']) {
                'pass' => '✅',
                'fail' => '❌',
                'skip' => '⏭️',
                default => '⏳',
            };
            $fields[] = [
                'name' => strtoupper($key),
                'value' => "{$icon} {$result['message']}",
                'inline' => false,
            ];
        }

        $this->send('test', '🔧 Connection Test Results', $fields);
    }

    /**
     * Send a Discord webhook embed.
     */
    /**
     * Send a Discord webhook embed. If $webhook is null, the message is skipped.
     * @param string|null $webhook Optional webhook URL override
     */
    private function send(string $type, string $title, array $fields, ?string $webhook = null): void
    {
        $target = $webhook ?? $this->logsWebhook;
        if (empty($target)) {
            return;
        }

        try {
            Http::post($target, [
                'embeds' => [
                    [
                        'title' => $title,
                        'color' => self::COLORS[$type] ?? self::COLORS['info'],
                        'fields' => $fields,
                        'footer' => [
                            'text' => 'FampayGateway • ' . now()->format('d M Y, h:i:s A'),
                        ],
                        'timestamp' => now()->toIso8601String(),
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            Log::warning('FampayGateway: Discord webhook failed', [
                'error' => $e->getMessage(),
                'webhook' => substr($target, 0, 64),
            ]);
        }
    }
}
