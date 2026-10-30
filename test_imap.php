<?php
/**
 * Fampay Gateway — IMAP Connection & Email Scan Test
 *
 * Run on your server via SSH:
 *   php /var/www/paymenter/extensions/Gateways/FampayGateway/test_imap.php
 *
 * This script tests:
 *   1. PHP IMAP extension is installed
 *   2. Gmail IMAP connection with your App Password
 *   3. Searches for recent FamPay emails
 *   4. Parses the latest FamPay email to show what the gateway sees
 *
 * ⚠️ DELETE THIS FILE after testing — it contains credentials in plain text!
 */

// ─── CONFIG (fill these in) ───────────────────────────────────────────────
$GMAIL_EMAIL       = '';   // e.g., 'yourname@gmail.com'
$GMAIL_APP_PASSWORD = '';   // e.g., 'abcd efgh ijkl mnop'
$FAMPAY_SENDER     = 'no-reply@famapp.in';
// ──────────────────────────────────────────────────────────────────────────

echo "\n";
echo "╔══════════════════════════════════════════════════╗\n";
echo "║      Fampay Gateway — IMAP Connection Test        ║\n";
echo "╚══════════════════════════════════════════════════╝\n\n";

// ─── Check config ─────────────────────────────────────────────────────────
if (empty($GMAIL_EMAIL) || empty($GMAIL_APP_PASSWORD)) {
    echo "❌ ERROR: Edit this file and fill in \$GMAIL_EMAIL and \$GMAIL_APP_PASSWORD\n";
    echo "   File: " . __FILE__ . "\n\n";
    exit(1);
}

// ─── Test 1: PHP IMAP Extension ──────────────────────────────────────────
echo "━━━ Test 1: PHP IMAP Extension ━━━\n";
if (function_exists('imap_open')) {
    echo "✅ php-imap is installed (PHP " . PHP_VERSION . ")\n\n";
} else {
    echo "❌ php-imap is NOT installed!\n";
    echo "   Fix: sudo apt install php-imap && sudo systemctl restart php*-fpm\n\n";
    exit(1);
}

// ─── Test 2: Gmail IMAP Connection ───────────────────────────────────────
echo "━━━ Test 2: Gmail IMAP Connection ━━━\n";
echo "📧 Connecting to imap.gmail.com:993 as {$GMAIL_EMAIL}...\n";

$mailbox = @imap_open(
    '{imap.gmail.com:993/imap/ssl}INBOX',
    $GMAIL_EMAIL,
    $GMAIL_APP_PASSWORD,
    0,
    1
);

if (!$mailbox) {
    $error = imap_last_error();
    echo "❌ Connection FAILED!\n";
    echo "   Error: {$error}\n\n";
    echo "   Common fixes:\n";
    echo "   • Make sure 2-Step Verification is enabled on your Google Account\n";
    echo "   • Generate a new App Password at: https://myaccount.google.com/apppasswords\n";
    echo "   • Don't use your regular Gmail password — use the 16-char App Password\n";
    echo "   • Check for spaces in the App Password (they're OK, Gmail ignores them)\n\n";
    exit(1);
}

$check = imap_check($mailbox);
echo "✅ Connected successfully!\n";
echo "   Mailbox: {$check->Mailbox}\n";
echo "   Total emails: {$check->Nmsgs}\n";
echo "   Recent emails: {$check->Recent}\n\n";

// ─── Test 3: Search for FamPay Emails ────────────────────────────────────
echo "━━━ Test 3: FamPay Email Search ━━━\n";
$since = date('d-M-Y', strtotime('-7 days'));
$criteria = "FROM \"{$FAMPAY_SENDER}\" SINCE \"{$since}\"";
echo "🔍 Searching: {$criteria}\n";

$messages = @imap_search($mailbox, $criteria, SE_UID);

if (!$messages || empty($messages)) {
    echo "📭 No FamPay emails found in the last 7 days\n";
    echo "   • Check that payment notification emails from {$FAMPAY_SENDER} are in your inbox\n";
    echo "   • Make sure they're not going to spam\n";
    echo "   • If you use a different FamPay sender email, update \$FAMPAY_SENDER above\n\n";

    // Try searching for ANY recent email to confirm search works
    $anyMessages = @imap_search($mailbox, "SINCE \"{$since}\"", SE_UID);
    $anyCount = $anyMessages ? count($anyMessages) : 0;
    echo "   ℹ️  Total emails in last 7 days (from all senders): {$anyCount}\n\n";

    imap_close($mailbox);
    exit(0);
}

$count = count($messages);
echo "✅ Found {$count} FamPay email(s) in the last 7 days\n\n";

// ─── Test 4: Parse the Latest FamPay Email ───────────────────────────────
echo "━━━ Test 4: Parse Latest FamPay Email ━━━\n";
$latestUid = end($messages);
$headerInfo = @imap_headerinfo($mailbox, imap_msgno($mailbox, $latestUid));

echo "📨 Latest FamPay email:\n";
echo "   Date:    " . ($headerInfo->date ?? 'N/A') . "\n";
echo "   Subject: " . (isset($headerInfo->subject) ? imap_utf8($headerInfo->subject) : 'N/A') . "\n";
echo "   From:    " . ($headerInfo->fromaddress ?? 'N/A') . "\n\n";

// Get and parse the body
$structure = @imap_fetchstructure($mailbox, $latestUid, FT_UID);
$body = '';

if ($structure && empty($structure->parts)) {
    $raw = @imap_fetchbody($mailbox, $latestUid, '1', FT_UID);
    $body = decodeBody($raw, $structure->encoding ?? 0);
} elseif ($structure) {
    foreach ($structure->parts as $i => $part) {
        if ($part->type === 0) {
            $raw = @imap_fetchbody($mailbox, $latestUid, (string)($i + 1), FT_UID);
            $decoded = decodeBody($raw, $part->encoding ?? 0);
            $subtype = strtolower($part->subtype ?? 'plain');
            if ($subtype === 'plain') { $body = $decoded; break; }
            if ($subtype === 'html' && empty($body)) { $body = strip_tags($decoded); }
        }
        if ($part->type === 1 && !empty($part->parts)) {
            foreach ($part->parts as $j => $sub) {
                if ($sub->type === 0) {
                    $raw = @imap_fetchbody($mailbox, $latestUid, ($i+1).'.'.($j+1), FT_UID);
                    $decoded = decodeBody($raw, $sub->encoding ?? 0);
                    $subtype = strtolower($sub->subtype ?? 'plain');
                    if ($subtype === 'plain') { $body = $decoded; break 2; }
                    if ($subtype === 'html' && empty($body)) { $body = strip_tags($decoded); }
                }
            }
        }
    }
}

if (empty($body)) {
    echo "⚠️  Could not extract email body\n\n";
} else {
    echo "📝 Email body (first 500 chars):\n";
    echo "   ┌──────────────────────────────────────────────\n";
    $lines = explode("\n", substr(trim($body), 0, 500));
    foreach ($lines as $line) {
        echo "   │ " . trim($line) . "\n";
    }
    echo "   └──────────────────────────────────────────────\n\n";

    // Parse payment details
    echo "━━━ Parsed Payment Details ━━━\n";
    echo "   (This is what the gateway extracts from the email)\n\n";

    // Amount
    if (preg_match('/₹\s?([\d,]+(?:\.\d+)?)/', $body, $m)) {
        echo "   💰 Amount:         ₹" . $m[1] . "\n";
    } else {
        echo "   ❌ Amount:         Could not extract\n";
    }

    // Sender
    if (preg_match('/from\s+(.+?)(?:\s*\n|\s*\r|Transaction)/is', $body, $m)) {
        echo "   👤 Sender:         " . trim($m[1]) . "\n";
    } else {
        echo "   ⚠️  Sender:         Could not extract\n";
    }

    // Transaction ID
    if (preg_match('/Transaction\s*ID\s*:?\s*([A-Z0-9]+)/i', $body, $m)) {
        echo "   🔑 Transaction ID: " . trim($m[1]) . "\n";
    } else {
        echo "   ⚠️  Transaction ID: Could not extract\n";
    }

    // UTR
    if (preg_match('/UTR\s*:?\s*(\d+)/i', $body, $m)) {
        echo "   🏦 UTR:            " . trim($m[1]) . "\n";
    } else {
        echo "   ⚠️  UTR:            Could not extract\n";
    }

    // Purpose/Note
    if (preg_match('/Purpose\s*:?\s*(.+?)(?:\s*\n|\s*\r|If)/is', $body, $m)) {
        echo "   📋 Purpose/Note:   " . trim($m[1]) . "\n";
    } else {
        echo "   ⚠️  Purpose/Note:   Could not extract\n";
    }

    echo "\n";
}

imap_close($mailbox);

echo "╔══════════════════════════════════════════════════╗\n";
echo "║              All Tests Complete!                 ║\n";
echo "╠══════════════════════════════════════════════════╣\n";
echo "║  ⚠️  DELETE this file after testing!             ║\n";
echo "║  rm test_imap.php                                ║\n";
echo "╚══════════════════════════════════════════════════╝\n\n";

function decodeBody(string $body, int $encoding): string {
    return match ($encoding) {
        3 => base64_decode($body),
        4 => quoted_printable_decode($body),
        default => $body,
    };
}
