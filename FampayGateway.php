<?php

namespace Paymenter\Extensions\Gateways\FampayGateway;

use App\Attributes\ExtensionMeta;
use App\Classes\Extension\Gateway;
use App\Helpers\ExtensionHelper;
use App\Models\Invoice;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\View;

#[ExtensionMeta(
    name: 'Fampay Gateway',
    description: 'FamPay UPI Payment Gateway with QR code display, mobile UPI deep-linking, and automatic Gmail-based payment verification',
    version: '1.0.0',
    author: 'Rolex Dev',
    url: ''
)]
class FampayGateway extends Gateway
{
    /**
     * Boot the extension — register routes and views.
     */
    public function boot()
    {
        require __DIR__ . '/routes.php';
        $this->registerViews();
    }

    /**
     * Admin-editable configuration fields.
     * All settings are manageable from the Paymenter admin panel.
     */
    public function getConfig($values = [])
    {
        return [
            [
                'name' => 'upi_id',
                'label' => 'UPI ID',
                'type' => 'text',
                'description' => "Your FamPay UPI ID (e.g., username@fam)",
                'required' => true,
            ],
            [
                'name' => 'payee_name',
                'label' => 'Payee Display Name',
                'type' => 'text',
                'description' => "Name displayed to the payer on UPI payment screen",
                'required' => true,
            ],
            [
                'name' => 'note_source',
                'label' => 'UPI Note Source',
                'type' => 'select',
                'description' => "Choose whether the UPI note uses the invoice DB id or the visible invoice number. The final note is digits only.",
                'options' => [
                    ['value' => 'id', 'label' => 'Invoice ID (digits only)'],
                    ['value' => 'number', 'label' => 'Invoice Number (digits only)'],
                ],
                'default' => 'id',
                'required' => true,
            ],
            [
                'name' => 'gmail_email',
                'label' => 'Gmail Email Address',
                'type' => 'email',
                'description' => "Gmail address that receives FamPay payment notification emails",
                'required' => true,
            ],
            [
                'name' => 'gmail_app_password',
                'label' => 'Gmail App Password',
                'type' => 'password',
                'description' => "App Password generated from Google Account settings (NOT your regular Gmail password)",
                'required' => true,
            ],
            [
                'name' => 'payment_timeout',
                'label' => 'Payment Timeout (minutes)',
                'type' => 'number',
                'description' => "Time in minutes before the QR code expires (default: 10)",
                'default' => '10',
                'required' => true,
            ],
            [
                'name' => 'poll_interval',
                'label' => 'Payment Check Interval (seconds)',
                'type' => 'number',
                'description' => "How frequently the payment page checks for confirmation (default: 10)",
                'default' => '10',
                'required' => true,
            ],
            [
                'name' => 'famapp_sender',
                'label' => 'FamPay Sender Email',
                'type' => 'text',
                'description' => "Email address FamPay sends payment notifications from",
                'default' => 'no-reply@famapp.in',
                'required' => true,
            ],
            [
                'name' => 'debug_mode',
                'label' => 'Debug Mode',
                'type' => 'checkbox',
                'description' => "Enable detailed logging for troubleshooting payment verification",
                'default' => false,
            ],
            [
                'name' => 'discord_webhook_logs',
                'label' => 'Discord Webhook (Logs)',
                'type' => 'text',
                'description' => "Discord webhook URL for general logs (checking, errors, tests). Optional.",
                'required' => false,
            ],
            [
                'name' => 'discord_webhook_paid',
                'label' => 'Discord Webhook (Paid)',
                'type' => 'text',
                'description' => "Discord webhook URL used specifically for confirmed/paid invoice embeds. Optional — falls back to Logs webhook if empty.",
                'required' => false,
            ],
            [
                'name' => 'test_connection',
                'label' => '🔧 Test Connection',
                'type' => 'text',
                'description' => "👉 Open this URL to test your configuration: " . url('/extensions/gateways/FampayGateway/test'),
                'default' => url('/extensions/gateways/FampayGateway/test'),
                'required' => false,
            ],
        ];
    }

    /**
     * Only allow this gateway for INR (Indian Rupee) payments.
     */
    public function canUseGateway($total, $currency, $type, $items = [])
    {
        return strtoupper($currency) === 'INR';
    }

    /**
     * Get a DiscordLogger instance using the configured webhook URL.
     */
    private function discord(): DiscordLogger
    {
        $logs = $this->config('discord_webhook_logs') ?? $this->config('discord_webhook_url');
        $paid = $this->config('discord_webhook_paid');
        return new DiscordLogger($logs, $paid);
    }

    /**
     * Register the extension's Blade view namespace before rendering.
     */
    private function registerViews(): void
    {
        static $registered = false;

        if ($registered) {
            return;
        }

        View::addNamespace('FampayGateway', __DIR__ . '/resources/views');

        $registered = true;
    }

    /**
     * Process payment — generate UPI QR code and return the payment view.
     *
     * @param Invoice $invoice The invoice to pay
     * @param mixed $total The total amount to collect
     * @return \Illuminate\View\View
     */
    public function pay(Invoice $invoice, $total)
    {
        $this->registerViews();

        $upiId = $this->config('upi_id');
        $payeeName = $this->config('payee_name');
        $timeout = (int) ($this->config('payment_timeout') ?? 10);
        $pollInterval = (int) ($this->config('poll_interval') ?? 10);

        // Store the payment start time in session (this is when the 10-min window starts)
        Session::put('fampay_pay_start_' . $invoice->id, now()->toIso8601String());

        // Build the payment note/purpose from invoice items
        $note = $this->buildPaymentNote($invoice);

        // Build UPI deep link string
        $upiString = $this->buildUpiString($upiId, $payeeName, $total, $note);

        // Generate QR code as base64 data URI (server-side, no CSP issues)
        $qrDataUri = $this->generateQrDataUri($upiString);

        // Discord: Log gateway triggered
        $this->discord()->logGatewayTriggered($invoice->id, $total, $note, $upiId);

        return view('FampayGateway::pay', [
            'invoice' => $invoice,
            'total' => $total,
            'upiString' => $upiString,
            'upiId' => $upiId,
            'payeeName' => $payeeName,
            'note' => $note,
            'timeout' => $timeout,
            'pollInterval' => $pollInterval,
            'qrDataUri' => $qrDataUri,
            'checkPaymentUrl' => Route::has('extensions.gateways.FampayGateway.check-payment')
                ? route('extensions.gateways.FampayGateway.check-payment', $invoice->id)
                : url('/extensions/gateways/FampayGateway/check-payment/' . $invoice->id),
            'invoiceUrl' => route('invoices.show', $invoice),
        ]);
    }

    /**
     * Build a payment note using only the numeric part of the selected invoice source.
     * This avoids UPI apps rewriting or duplicating text prefixes in the displayed note.
     */
    private function buildPaymentNote(Invoice $invoice): string
    {
        $source = $this->config('note_source') ?? 'id';

        if ($source === 'number' && !empty($invoice->number)) {
            $value = $invoice->number;
        } else {
            $value = $invoice->id;
        }

        $digitsOnly = preg_replace('/\D+/', '', (string) $value);

        return $digitsOnly !== '' ? $digitsOnly : (string) $value;
    }

    /**
     * Build a standard UPI payment URI string.
     * This is used for both QR code content and the mobile deep link.
     */
    private function buildUpiString(string $upiId, string $payeeName, float $amount, string $note): string
    {
        $params = [
            'pa' => $upiId,
            'pn' => $payeeName,
            'am' => number_format($amount, 2, '.', ''),
            'cu' => 'INR',
            'tn' => $note,
        ];

        return 'upi://pay?' . http_build_query($params);
    }

    /**
     * Generate a QR code as a base64 data URI (server-side).
     * This avoids CSP img-src restrictions since the image is embedded inline.
     *
     * Uses Google Charts API via server-side HTTP request.
     * Falls back to empty string if the API is unreachable.
     */
    private function generateQrDataUri(string $data): string
    {
        try {
            $url = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&format=png&margin=8&data=' . urlencode($data);

            $response = \Illuminate\Support\Facades\Http::timeout(5)->get($url);

            if ($response->successful()) {
                $contentType = $response->header('Content-Type') ?? 'image/png';
                return 'data:' . $contentType . ';base64,' . base64_encode($response->body());
            }
        } catch (\Exception $e) {
            try { Log::debug('FampayGateway: QR generation failed, view will use JS fallback', [
                'error' => $e->getMessage(),
            ]); } catch (\Throwable $le) {}
        }

        return '';
    }

    /**
     * AJAX endpoint: Check Gmail via IMAP for a matching FamPay payment email.
     * Called periodically by the payment page JavaScript.
     */
    public function checkPayment(Request $request, $invoiceId)
    {
        try {
            $invoice = Invoice::findOrFail($invoiceId);
            $discord = $this->discord();

            // Already paid?
            if ($invoice->status === 'paid') {
                return response()->json([
                    'status' => 'paid',
                    'redirect' => route('invoices.show', $invoice),
                ]);
            }

            $timeout = (int) ($this->config('payment_timeout') ?? 10);

            // Use session-stored payment start time (set when user clicked Pay)
            $payStartedRaw = Session::get('fampay_pay_start_' . $invoiceId);
            $payStartedAt = $payStartedRaw ? Carbon::parse($payStartedRaw) : now();
            $expiresAt = $payStartedAt->copy()->addMinutes($timeout);

            // Check if the payment window has expired
            if (now()->greaterThan($expiresAt)) {
                Session::forget('fampay_pay_start_' . $invoiceId);
                try { $discord->logPaymentExpired($invoiceId, (float) ($invoice->total ?? 0)); } catch (\Throwable $de) {}
                return response()->json(['status' => 'expired']);
            }

            // Get the expected amount — try multiple properties
            $expectedAmount = 0;
            if (isset($invoice->total)) {
                $expectedAmount = (float) $invoice->total;
            } elseif (method_exists($invoice, 'getTotal')) {
                $expectedAmount = (float) $invoice->getTotal();
            } elseif (isset($invoice->remaining)) {
                $expectedAmount = (float) $invoice->remaining;
            }

            if ($expectedAmount <= 0) {
                try { Log::warning('FampayGateway: Could not determine invoice amount', [
                    'invoice_id' => $invoiceId,
                    'invoice_attrs' => array_keys($invoice->getAttributes()),
                ]); } catch (\Throwable $le) {}
                return response()->json(['status' => 'pending', 'message' => 'Amount not resolved']);
            }

            // Check Gmail via IMAP for a matching payment
            $verifier = new GmailVerifier(
                $this->config('gmail_email'),
                $this->config('gmail_app_password'),
                $this->config('famapp_sender') ?? 'no-reply@famapp.in',
                (bool) $this->config('debug_mode'),
                $discord,
                $invoiceId
            );

            $note = $this->buildPaymentNote($invoice);
            $result = $verifier->checkForPayment(
                $expectedAmount,
                $note,
                $payStartedAt,
                $timeout
            );

            if ($result) {
                // Payment found — mark invoice as paid
                ExtensionHelper::addPayment(
                    $invoice->id,
                    'FampayGateway',
                    $result['amount'],
                    transactionId: $result['transactionId']
                );

                Session::forget('fampay_pay_start_' . $invoiceId);

                try { Log::info('FampayGateway: Payment confirmed', [
                    'invoice_id' => $invoice->id,
                    'amount' => $result['amount'],
                    'txn_id' => $result['transactionId'],
                    'sender' => $result['sender'],
                    'note_matched' => $result['noteMatched'],
                ]); } catch (\Throwable $le) {}

                try {
                    $discord->logPaymentConfirmed(
                        $invoice->id,
                        $result['amount'],
                        $result['transactionId'],
                        $result['sender'] ?? 'Unknown',
                        $result['noteMatched']
                    );
                } catch (\Throwable $de) {}

                return response()->json([
                    'status' => 'paid',
                    'transactionId' => $result['transactionId'],
                    'redirect' => route('invoices.show', $invoice),
                ]);
            }

            return response()->json(['status' => 'pending']);

        } catch (\Throwable $e) {
            try { Log::error('FampayGateway: checkPayment error', [
                'error' => $e->getMessage(),
                'file' => $e->getFile() . ':' . $e->getLine(),
                'invoice_id' => $invoiceId,
            ]); } catch (\Throwable $le) {}

            try { $this->discord()->logError($invoiceId, $e->getMessage()); } catch (\Throwable $de) {}

            return response()->json([
                'status' => 'error',
                'message' => $this->config('debug_mode') ? $e->getMessage() : 'Internal error',
            ], 200);  /* Return 200 so frontend can parse the JSON */
        }
    }

    /**
     * Admin test page — returns a full diagnostic HTML page.
     * Visit: /extensions/gateways/FampayGateway/test
     */
    public function testConnection(Request $request)
    {
        $tests = [];
        $latestEmail = null;

        // ─── Test 1: PHP IMAP Extension ─────────────────────────────
        if (!function_exists('imap_open')) {
            $tests[] = [
                'name' => 'PHP IMAP Extension',
                'status' => 'fail',
                'message' => 'php-imap is not installed',
                'fix' => 'Run on server: sudo apt install php-imap && sudo systemctl restart php*-fpm',
            ];
        } else {
            $tests[] = [
                'name' => 'PHP IMAP Extension',
                'status' => 'pass',
                'message' => 'Installed (PHP ' . PHP_VERSION . ')',
            ];
        }

        // ─── Test 2: UPI ID Format ──────────────────────────────────
        $upiId = $this->config('upi_id');
        if ($upiId && preg_match('/^[a-zA-Z0-9._-]+@[a-zA-Z0-9]+$/', $upiId)) {
            $tests[] = [
                'name' => 'UPI ID',
                'status' => 'pass',
                'message' => "Format valid: {$upiId}",
            ];
        } else {
            $tests[] = [
                'name' => 'UPI ID',
                'status' => 'fail',
                'message' => "Invalid format: '" . ($upiId ?: 'empty') . "'",
                'fix' => 'Enter a valid UPI ID in the format username@provider (e.g., myname@fam)',
            ];
        }

        // ─── Test 3: Gmail Config Present ───────────────────────────
        $email = $this->config('gmail_email');
        $appPw = $this->config('gmail_app_password');

        if (empty($email) || empty($appPw)) {
            $tests[] = [
                'name' => 'Gmail Credentials',
                'status' => 'fail',
                'message' => 'Gmail email or App Password is empty',
                'fix' => 'Fill in both Gmail Email Address and Gmail App Password in the gateway config',
            ];
        } else {
            $tests[] = [
                'name' => 'Gmail Credentials',
                'status' => 'pass',
                'message' => "Email: {$email}, App Password: " . str_repeat('•', 12) . substr($appPw, -4),
            ];
        }

        // ─── Test 4: Gmail IMAP Connection ──────────────────────────
        if (function_exists('imap_open') && !empty($email) && !empty($appPw)) {
            try {
                $verifier = new GmailVerifier(
                    $email,
                    $appPw,
                    $this->config('famapp_sender') ?? 'no-reply@famapp.in',
                    true
                );

                $verifier->testConnection();
                $tests[] = [
                    'name' => 'Gmail IMAP Connection',
                    'status' => 'pass',
                    'message' => "Connected to imap.gmail.com:993 as {$email}",
                ];

                // ─── Test 5: FamPay Emails ──────────────────────────
                $sender = $this->config('famapp_sender') ?? 'no-reply@famapp.in';
                $recentCount = $verifier->countRecentEmails(168); // 7 days

                if ($recentCount > 0) {
                    $tests[] = [
                        'name' => 'FamPay Emails',
                        'status' => 'pass',
                        'message' => "Found {$recentCount} email(s) from {$sender} in the last 7 days",
                    ];

                    // Parse latest email for preview
                    $latestEmail = $verifier->getLatestEmailPreview($sender);
                } else {
                    $tests[] = [
                        'name' => 'FamPay Emails',
                        'status' => 'warn',
                        'message' => "No emails from {$sender} found in the last 7 days",
                        'fix' => "Check that FamPay payment notifications are arriving in {$email}. Also check spam folder.",
                    ];
                }
            } catch (\Exception $e) {
                $errorMsg = $e->getMessage();
                $fix = 'Check your Gmail email and App Password';

                if (stripos($errorMsg, 'Authentication') !== false || stripos($errorMsg, 'AUTHENTICATIONFAILED') !== false) {
                    $fix = "Authentication failed. Make sure:\n• 2-Step Verification is enabled on Google Account\n• You're using an App Password (not your regular password)\n• Generate a new one at: https://myaccount.google.com/apppasswords";
                } elseif (stripos($errorMsg, 'Connection') !== false || stripos($errorMsg, 'timeout') !== false) {
                    $fix = "Cannot reach imap.gmail.com:993. Check:\n• Server firewall allows outbound port 993\n• Run: telnet imap.gmail.com 993";
                }

                $tests[] = [
                    'name' => 'Gmail IMAP Connection',
                    'status' => 'fail',
                    'message' => $errorMsg,
                    'fix' => $fix,
                ];
                $tests[] = [
                    'name' => 'FamPay Emails',
                    'status' => 'skip',
                    'message' => 'Skipped — Gmail connection must succeed first',
                ];
            }
        } else {
            if (!function_exists('imap_open')) {
                $tests[] = ['name' => 'Gmail IMAP Connection', 'status' => 'skip', 'message' => 'Skipped — php-imap required'];
            }
            $tests[] = ['name' => 'FamPay Emails', 'status' => 'skip', 'message' => 'Skipped — Gmail connection required'];
        }

        // ─── Test 6: Discord Webhook ────────────────────────────────
        $webhookUrl = $this->config('discord_webhook_logs') ?? $this->config('discord_webhook_url');
        if (!empty($webhookUrl)) {
            try {
                $response = \Illuminate\Support\Facades\Http::post($webhookUrl, [
                    'content' => '🔧 **FampayGateway** — Test connection successful! (' . now()->format('d M Y, h:i A') . ')',
                ]);
                if ($response->successful()) {
                    $tests[] = ['name' => 'Discord Webhook', 'status' => 'pass', 'message' => 'Webhook sent successfully'];
                } else {
                    $tests[] = ['name' => 'Discord Webhook', 'status' => 'fail', 'message' => 'Webhook returned HTTP ' . $response->status(), 'fix' => 'Verify the webhook URL is correct and not deleted'];
                }
            } catch (\Exception $e) {
                $tests[] = ['name' => 'Discord Webhook', 'status' => 'fail', 'message' => $e->getMessage(), 'fix' => 'Check the webhook URL format'];
            }
        } else {
            $tests[] = ['name' => 'Discord Webhook', 'status' => 'skip', 'message' => 'Not configured (optional)'];
        }

        // ─── Build JSON for Discord full log ────────────────────────
        $resultsMap = [];
        foreach ($tests as $t) {
            $resultsMap[$t['name']] = ['status' => $t['status'], 'message' => $t['message']];
        }
        $this->discord()->logTestResult($resultsMap);

        // ─── Render HTML page ───────────────────────────────────────
        return $this->renderTestPage($tests, $latestEmail);
    }

    /**
     * Render the HTML diagnostic test page.
     */
    private function renderTestPage(array $tests, ?array $latestEmail)
    {
        $allPassed = collect($tests)->every(fn($t) => in_array($t['status'], ['pass', 'skip']));

        $html = '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
        $html .= '<title>Fampay Gateway — Connection Test</title>';
        $html .= '<style>';
        $html .= 'body{font-family:Inter,system-ui,-apple-system,sans-serif;background:#0f1117;color:#e2e8f0;margin:0;padding:2rem}';
        $html .= '.card{background:linear-gradient(135deg,rgba(30,32,48,.95),rgba(20,22,36,.98));border:1px solid rgba(99,102,241,.2);border-radius:1rem;padding:1.5rem;max-width:640px;margin:0 auto;box-shadow:0 8px 32px rgba(0,0,0,.4)}';
        $html .= 'h1{font-size:1.4rem;margin:0 0 1.5rem;color:#a78bfa;text-align:center}';
        $html .= '.test{display:flex;align-items:flex-start;gap:.75rem;padding:.75rem;border-radius:.5rem;margin-bottom:.5rem;background:rgba(255,255,255,.03)}';
        $html .= '.icon{font-size:1.2rem;flex-shrink:0;width:1.5rem;text-align:center}';
        $html .= '.info{flex:1}';
        $html .= '.name{font-weight:600;font-size:.9rem;color:#e2e8f0}';
        $html .= '.msg{font-size:.8rem;color:#94a3b8;margin-top:.15rem}';
        $html .= '.fix{font-size:.75rem;color:#f59e0b;margin-top:.35rem;background:rgba(245,158,11,.08);padding:.4rem .6rem;border-radius:.35rem;white-space:pre-wrap;border-left:2px solid #f59e0b}';
        $html .= '.pass{border-left:3px solid #22c55e}.fail{border-left:3px solid #ef4444}.warn{border-left:3px solid #f59e0b}.skip{border-left:3px solid #64748b}';
        $html .= '.summary{text-align:center;padding:1rem;margin-top:1rem;border-radius:.5rem;font-weight:700;font-size:1rem}';
        $html .= '.summary.ok{background:rgba(34,197,94,.1);color:#22c55e;border:1px solid rgba(34,197,94,.3)}';
        $html .= '.summary.bad{background:rgba(239,68,68,.1);color:#ef4444;border:1px solid rgba(239,68,68,.3)}';
        $html .= '.email-preview{margin-top:1.5rem;padding:1rem;background:rgba(99,102,241,.05);border-radius:.5rem;border:1px solid rgba(99,102,241,.15)}';
        $html .= '.email-preview h3{font-size:.85rem;color:#818cf8;margin:0 0 .75rem}';
        $html .= '.email-preview .row{display:flex;gap:.5rem;font-size:.8rem;margin-bottom:.3rem}';
        $html .= '.email-preview .lbl{color:#64748b;min-width:100px}';
        $html .= '.email-preview .val{color:#cbd5e1}';
        $html .= '.back{display:inline-block;margin-top:1rem;color:#6366f1;text-decoration:none;font-size:.85rem}';
        $html .= '.back:hover{text-decoration:underline}';
        $html .= '.btn{display:inline-block;margin-top:1rem;padding:.5rem 1.5rem;background:linear-gradient(135deg,#6366f1,#7c3aed);color:#fff;border:none;border-radius:.5rem;font-size:.85rem;font-weight:600;cursor:pointer;text-decoration:none}';
        $html .= '.btn:hover{opacity:.9}';
        $html .= '</style></head><body>';
        $html .= '<div class="card">';
        $html .= '<h1>🔧 Fampay Gateway — Connection Test</h1>';

        $icons = ['pass' => '✅', 'fail' => '❌', 'warn' => '⚠️', 'skip' => '⏭️'];

        foreach ($tests as $t) {
            $cls = $t['status'];
            $icon = $icons[$cls] ?? '⏳';
            $html .= "<div class=\"test {$cls}\">";
            $html .= "<div class=\"icon\">{$icon}</div>";
            $html .= '<div class="info">';
            $html .= "<div class=\"name\">{$t['name']}</div>";
            $html .= "<div class=\"msg\">{$t['message']}</div>";
            if (!empty($t['fix'])) {
                $html .= "<div class=\"fix\">💡 {$t['fix']}</div>";
            }
            $html .= '</div></div>';
        }

        if ($allPassed) {
            $html .= '<div class="summary ok">✅ All checks passed — Gateway is ready!</div>';
        } else {
            $html .= '<div class="summary bad">❌ Some checks failed — Fix the issues above</div>';
        }

        // Latest email preview
        if ($latestEmail) {
            $html .= '<div class="email-preview">';
            $html .= '<h3>📨 Latest FamPay Email (what the gateway sees)</h3>';
            foreach ($latestEmail as $key => $val) {
                $label = ucfirst(str_replace('_', ' ', $key));
                $val = htmlspecialchars($val ?? 'N/A');
                $html .= "<div class=\"row\"><span class=\"lbl\">{$label}:</span><span class=\"val\">{$val}</span></div>";
            }
            $html .= '</div>';
        }

        $html .= '<div style="text-align:center">';
        $html .= '<a href="javascript:location.reload()" class="btn">🔄 Run Again</a>';
        $html .= '</div>';

        $html .= '</div></body></html>';

        return response($html);
    }
}
