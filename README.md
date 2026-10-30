 <div align="center">

# ⚡ FAMPAY GATEWAY

### Premium UPI Payment Gateway for Paymenter v2

**Seamless UPI payments. Automated Gmail verification. Real-time payment tracking.**

Accept payments through FamPay with dynamic QR codes, UPI deep links, automatic Gmail transaction verification, and Discord notifications.

<br>

![Version](https://img.shields.io/badge/version-1.0.0-6366f1?style=for-the-badge)
![Paymenter](https://img.shields.io/badge/Paymenter-v2-22c55e?style=for-the-badge)
![PHP](https://img.shields.io/badge/PHP-8.1%2B-777BB4?style=for-the-badge)
![License](https://img.shields.io/badge/License-Premium-f59e0b?style=for-the-badge)

**Developed by [Para](https://github.com/ItzMePara9)**

---

</div>

## ✨ Overview

**Fampay Gateway** is a premium payment gateway extension built for **Paymenter v2**, designed to simplify UPI payments for Indian hosting providers, businesses, and digital service platforms.

It combines a modern payment experience with automated transaction verification, eliminating the need for manually checking every payment.

Customers can scan a QR code or open their preferred UPI application, complete their payment, and let the gateway automatically verify the transaction through Gmail.

### Why Fampay Gateway?

- No manual payment confirmation.
- No additional payment gateway API required.
- Automatic transaction verification through Gmail IMAP.
- Compatible with popular UPI applications.
- Real-time Discord payment notifications.
- Modern, responsive payment interface.

---

## 🚀 Features

### 💳 Modern Payment Experience

- **Dynamic UPI QR Codes** – Automatically generate QR codes with pre-filled payment amounts and invoice references.
- **UPI Deep Linking** – Open Google Pay, PhonePe, Paytm, and other supported UPI applications.
- **Live Countdown** – Animated payment expiration timer.
- **Real-Time Payment Status** – Automatically refresh payment status without reloading the page.
- **Premium Interface** – Modern dark UI with glassmorphism, gradients, and smooth animations.
- **Responsive Design** – Optimized for desktop, tablets, and mobile devices.

### 🔐 Automated Payment Verification

- **Gmail IMAP Integration** – Automatically scan Gmail for incoming FamPay payment notifications.
- **Smart Transaction Matching** – Two-pass verification using invoice references and payment amounts.
- **Duplicate Protection** – Prevent previously processed transactions from being claimed again.
- **Concurrent Payment Handling** – Reduce incorrect payment matching when multiple customers pay simultaneously.
- **Flexible Email Parsing** – Multiple regex patterns to handle different notification formats.
- **Automatic Invoice Confirmation** – Mark eligible invoices as paid after successful verification.

### 📡 Discord Integration

Receive real-time payment notifications directly in your Discord server.

Supported event notifications:

| Event | Description |
|---|---|
| 🔔 Gateway Triggered | Customer opens the payment page |
| 🔍 Checking Email | Gmail verification begins |
| 📩 Email Found | Payment notification detected |
| ✅ Payment Confirmed | Transaction successfully verified |
| ⏰ Payment Expired | Payment session expires |
| ❌ Verification Error | An error occurs during verification |
| 🧪 Test Results | Diagnostic test results |

Notifications can include invoice IDs, payment amounts, transaction references, UTR details, and verification information.

### 🛠️ Administration & Diagnostics

- Centralized configuration through the Paymenter admin panel.
- Built-in diagnostic and connection testing page.
- Gmail IMAP connection verification.
- UPI ID validation.
- FamPay notification email preview.
- Discord webhook testing.
- Detailed error reporting with troubleshooting suggestions.
- Optional debug logging for developers.

---

## 📋 Requirements

Make sure your server meets the following requirements before installation.

| Component | Requirement |
|---|---|
| Paymenter | Version 2 |
| PHP | 8.1 or higher |
| PHP Extension | IMAP |
| Composer | Compatible with your Paymenter installation |
| Gmail | Gmail account with 2-Step Verification |
| Gmail App Password | Required for IMAP authentication |
| FamPay | Active UPI ID |
| Discord Webhook | Optional |

---

## 📦 Installation

### 1. Install PHP IMAP

For Ubuntu or Debian:

```bash
sudo apt update
sudo apt install php-imap -y
```

Restart your PHP-FPM service. Replace `8.2` with your installed PHP version.

```bash
sudo systemctl restart php8.2-fpm
```

Verify the extension:

```bash
php -m | grep -i imap
```

### 2. Download the Extension

Upload the `FampayGateway` directory to:

```text
/var/www/paymenter/extensions/Gateways/FampayGateway/
```

Make sure the directory structure is correct.

### 3. Regenerate Composer Autoload

Navigate to your Paymenter installation:

```bash
cd /var/www/paymenter
composer dump-autoload
```

### 4. Enable the Gateway

1. Open your Paymenter administration panel.
2. Navigate to **Admin → Gateways**.
3. Create a new payment gateway.
4. Select **Fampay Gateway**.
5. Configure your UPI, Gmail, and Discord settings.
6. Save your configuration.

Your gateway is now ready for configuration testing.

---

## ⚙️ Configuration

Configure the following settings from the Paymenter administration panel.

| Setting | Description |
|---|---|
| UPI ID | Your FamPay UPI ID |
| Payee Name | Name displayed during UPI payment |
| Gmail Email | Gmail account receiving payment notifications |
| Gmail App Password | Google-generated 16-character App Password |
| Payment Timeout | Payment expiration time in minutes |
| Poll Interval | Gmail verification interval in seconds |
| FamPay Sender | Expected sender email address |
| Debug Mode | Enables detailed diagnostic logging |
| Discord Webhook | Discord notification webhook URL |

**Important:** Keep your Gmail App Password and Discord webhook private. Never publish them in your repository or share them with untrusted users.

---

## 🔑 Gmail App Password Setup

Fampay Gateway uses Gmail IMAP to detect payment notifications.

You do not need a Google Cloud project.

### Step 1 – Enable 2-Step Verification

Visit:

https://myaccount.google.com/security

Enable 2-Step Verification on your Gmail account.

### Step 2 – Generate an App Password

Visit:

https://myaccount.google.com/apppasswords

Generate an App Password for your application.

### Step 3 – Configure Paymenter

Enter your:

- Gmail email address.
- Generated Gmail App Password.

Save the configuration and use the diagnostic page to test the connection.

---

## 🔄 Payment Workflow

```text
          CUSTOMER
              |
              v
       Opens Payment Page
              |
              v
      QR Code + UPI Deep Link
              |
              v
       Customer Makes Payment
              |
              v
        Paymenter Polling
              |
              v
        Gmail IMAP Scanner
              |
              v
       Transaction Matching
              |
         +----+----+
         |         |
         v         v
      MATCHED   NOT FOUND
         |         |
         v         v
   Verify Details  Continue
         |
         v
   Confirm Transaction
         |
         v
    Invoice Paid
         |
         v
  Discord Notification
```

---

## 🛡️ Transaction Safety

Fampay Gateway includes several mechanisms to reduce incorrect payment confirmations.

| Protection | Description |
|---|---|
| Unique Invoice Reference | Uses invoice-specific numeric references |
| Two-Pass Matching | Attempts exact reference matching before amount fallback |
| Transaction Deduplication | Prevents duplicate transaction claims |
| Multi-Candidate Protection | Avoids ambiguous amount-only matches |
| Payment Timeout | Prevents expired payment sessions from remaining active |

**Note:** Amount-only matching should only be used when there is exactly one eligible candidate. It is not equivalent to cryptographic payment verification. Always validate the transaction details available in the notification before confirming an invoice.

---

## 🧪 Diagnostic & Testing

Fampay Gateway includes a built-in diagnostic page to help administrators identify configuration problems.

Access the test page using the URL displayed in the gateway configuration.

Example:

```text
https://yourdomain.com/extensions/gateways/FampayGateway/test
```

### Available Tests

- PHP IMAP extension availability.
- UPI ID format validation.
- Gmail configuration validation.
- Gmail IMAP authentication.
- FamPay notification email detection.
- Latest notification parsing preview.
- Discord webhook connectivity.

Use the diagnostic page before accepting live payments.

---

## 📁 Project Structure

```text
FampayGateway/
│
├── FampayGateway.php
│   └── Main Paymenter gateway implementation
│
├── GmailVerifier.php
│   └── Gmail IMAP integration and transaction parsing
│
├── DiscordLogger.php
│   └── Discord webhook notification system
│
├── routes.php
│   └── Gateway route definitions
│
├── resources/
│   └── views/
│       └── pay.blade.php
│           └── Customer payment interface
│
└── README.md
```

---

## 🔧 Troubleshooting

| Issue | Possible Solution |
|---|---|
| IMAP connection failed | Verify Gmail credentials and App Password |
| IMAP extension missing | Install `php-imap` and restart PHP-FPM |
| Payments not detected | Check Gmail inbox, spam folder, and Laravel logs |
| QR code not loading | Verify external QR service connectivity |
| Payment timer expires unexpectedly | Check server time and payment session initialization |
| Incorrect payment matching | Verify invoice reference and transaction matching logic |
| Discord notifications unavailable | Check webhook URL and outbound server connectivity |

### Debug Logs

Enable Debug Mode and inspect:

```bash
tail -f /var/www/paymenter/storage/logs/laravel.log
```

Avoid exposing logs publicly because they may contain sensitive configuration or transaction information.

---

## 📝 Changelog

### Version 1.0.0

- Initial release of Fampay Gateway.
- Dynamic UPI QR code generation.
- UPI mobile application deep linking.
- Gmail IMAP payment verification.
- Automatic transaction matching.
- Duplicate transaction protection.
- Concurrent payment handling.
- Discord webhook integration.
- Built-in diagnostic page.
- Premium responsive payment interface.

---

## 🔒 Security Notice

- Never expose Gmail App Passwords.
- Never publish Discord webhook URLs.
- Use HTTPS for your Paymenter installation.
- Restrict access to diagnostic pages.
- Keep Paymenter and PHP updated.
- Validate payment notifications before marking invoices as paid.
- Do not treat an email notification alone as cryptographic proof of payment.

---

## 📄 License

**Premium Software – All Rights Reserved.**

This extension is distributed as premium software.

- Unauthorized redistribution is prohibited.
- Reselling without permission is prohibited.
- Modification and redistribution are subject to the developer's authorization.

---

<div align="center">

# ⚡ PARA

**Designed & Developed by Para**

Building tools. Creating solutions. Engineering possibilities.

[GitHub](https://github.com/ItzMePara9)

---

<sub>Fampay Gateway · Paymenter v2 · Version 1.0.0</sub>

**© 2026 Para. All Rights Reserved.**

</div>
