 <div align="center">

# ⚡ Fampay Gateway

### Premium UPI Payment Gateway for Paymenter v2

**A smarter way to accept UPI payments.**

Accept payments through FamPay with dynamic QR codes, UPI deep linking, automated Gmail transaction verification, and real-time Discord notifications.

<br>

[![Version](https://img.shields.io/badge/version-1.0.0-6366f1?style=for-the-badge)](#changelog)
[![Paymenter](https://img.shields.io/badge/Paymenter-v2-22c55e?style=for-the-badge)](https://paymenter.org)
[![PHP](https://img.shields.io/badge/PHP-8.1+-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://php.net)
[![License](https://img.shields.io/badge/License-Premium-f59e0b?style=for-the-badge)](#license)

<br>

**Developed by [Para.exe](https://github.com/ItzMePara9)**

</div>

---

## 📖 Overview

**Fampay Gateway** is a premium payment extension built specifically for [Paymenter v2](https://paymenter.org).

It integrates FamPay-based UPI payments directly into your billing system, providing customers with a seamless checkout experience while automating payment verification in the background.

Unlike traditional manual UPI verification, Fampay Gateway uses Gmail IMAP to detect incoming payment notifications and automatically match transactions with the correct invoices.

### Why Fampay Gateway?

- No manual payment verification.
- No Google Cloud project or OAuth setup.
- No additional payment API integration required.
- Automatic invoice payment confirmation.
- Real-time transaction notifications through Discord.
- Simple configuration directly from the Paymenter admin panel.

---

## ✨ Features

### 💳 Modern Payment Experience

| Feature | Description |
|---|---|
| Dynamic QR Codes | Automatically generates UPI QR codes with pre-filled payment details. |
| UPI Deep Linking | Opens supported UPI applications directly from mobile devices. |
| Multiple UPI Apps | Compatible with GPay, PhonePe, Paytm and other UPI applications. |
| Payment Countdown | Configurable payment expiration timer with animated visuals. |
| Live Payment Status | Automatically checks payment status at configurable intervals. |
| Responsive Interface | Premium dark-themed interface with gradients and glassmorphism. |

### 🔍 Intelligent Payment Verification

| Feature | Description |
|---|---|
| Gmail IMAP Integration | Reads incoming FamPay payment notification emails. |
| Automatic Detection | Detects successful payments without manual intervention. |
| Smart Matching | Uses transaction remarks and payment amounts to identify invoices. |
| Reference Matching | Matches numeric invoice references against payment remarks. |
| Transaction Deduplication | Prevents previously processed transactions from being claimed again. |
| Concurrent Payment Protection | Prevents ambiguous transactions from being automatically assigned. |
| Email Parsing | Supports multiple email formats through flexible parsing patterns. |

### 📊 Discord Integration

Get real-time payment activity directly in your Discord server.

**Supported notification events:**

- 🔔 Gateway Triggered
- 🔍 Checking for Payments
- 📬 Payment Email Found
- ✅ Payment Confirmed
- ⏰ Payment Expired
- ❌ Verification Errors
- 🧪 Connection Test Results

Notifications can include invoice information, payment amounts, transaction IDs, UTR details, sender information, and matching results.

### 🛠️ Administration & Diagnostics

- Visual connection testing interface.
- Gmail IMAP connection diagnostics.
- UPI configuration validation.
- Discord webhook testing.
- Latest detected email preview.
- Detailed diagnostic messages with troubleshooting suggestions.
- Optional Laravel debug logging.
- Configuration management through the Paymenter admin panel.

---

## ⚙️ Requirements

Make sure your environment meets the following requirements before installation.

| Component | Requirement |
|---|---|
| Paymenter | Version 2.x |
| PHP | 8.1 or higher |
| PHP Extension | IMAP |
| Composer | Installed |
| Gmail | Account with 2-Step Verification |
| Gmail App Password | Required |
| FamPay | Active UPI account |
| Discord Webhook | Optional |

---

## 🚀 Installation Guide

Follow these steps to install Fampay Gateway on your Paymenter instance.

### 01. Install PHP IMAP

The PHP IMAP extension is required for Gmail-based payment verification.

**Ubuntu / Debian**

```bash
sudo apt update
sudo apt install php-imap
```

If you are using PHP-FPM, restart the corresponding service:

```bash
sudo systemctl restart php8.2-fpm
```

Replace `8.2` with your installed PHP version.

Verify that IMAP is enabled:

```bash
php -m | grep -i imap
```

Expected output:

```text
imap
```

### 02. Upload the Extension

Upload or clone the `FampayGateway` directory into your Paymenter extensions directory.

```text
/var/www/paymenter/extensions/Gateways/FampayGateway/
```

Ensure the extension structure is correct and all files are uploaded.

### 03. Refresh Composer Autoload

Navigate to your Paymenter installation directory:

```bash
cd /var/www/paymenter
```

Refresh the Composer autoloader:

```bash
composer dump-autoload
```

### 04. Enable the Gateway

1. Log in to your Paymenter administration panel.
2. Navigate to **Admin → Gateways**.
3. Select **Create Gateway**.
4. Choose **Fampay Gateway**.
5. Configure your UPI, Gmail, and optional Discord settings.
6. Save your configuration.

Your gateway is now ready for configuration testing.

---

## 🔑 Gmail App Password Configuration

Fampay Gateway uses Gmail IMAP to automatically detect incoming FamPay payment notifications.

**Good news:** You do not need a Google Cloud project, OAuth credentials, or a Gmail API application.

You only need a Gmail App Password.

### Step 1 — Enable 2-Step Verification

Visit [Google Account Security](https://myaccount.google.com/security).

Enable 2-Step Verification on the Gmail account you want to use.

### Step 2 — Generate an App Password

Visit [Google App Passwords](https://myaccount.google.com/apppasswords).

1. Sign in to your Google account.
2. Create a new App Password.
3. Give it a recognizable name, such as `Fampay Gateway`.
4. Generate your password.
5. Copy the generated 16-character password.

### Step 3 — Configure Paymenter

Enter the following details in your gateway configuration:

| Field | Value |
|---|---|
| Gmail Email | Your Gmail address |
| Gmail App Password | Generated 16-character password |
| FamPay Sender | FamPay notification sender address |

**Security reminder:** Never share your Gmail App Password publicly or commit it to GitHub. Use a dedicated Gmail account where possible.

---

## ⚙️ Configuration Reference

All gateway settings can be managed directly from the Paymenter administration panel.

| Configuration | Description | Required |
|---|---|---|
| UPI ID | Your FamPay UPI ID | Yes |
| Payee Name | Name displayed during payment | Yes |
| Gmail Email | Gmail account receiving notifications | Yes |
| Gmail App Password | Gmail IMAP authentication password | Yes |
| Payment Timeout | QR payment expiration in minutes | Yes |
| Poll Interval | Payment verification frequency in seconds | Yes |
| FamPay Sender | Sender email used for notification filtering | Yes |
| Debug Mode | Enables additional Laravel logging | No |
| Discord Webhook | Discord payment notification endpoint | No |
| Test Connection | Gateway diagnostic page | No |

**Recommended defaults:**

- Payment Timeout: `10 minutes`
- Poll Interval: `10 seconds`

Adjust these values according to your server resources and payment requirements.

---

## 🔄 Payment Workflow

Fampay Gateway automates the entire payment verification process.

```text
             CUSTOMER CHECKOUT
                    |
                    ▼
          Customer opens invoice
                    |
                    ▼
          UPI QR Code Generated
                    |
                    ▼
          Customer makes payment
                    |
                    ▼
          Payment status polling
                    |
                    ▼
           Gmail IMAP Scanning
                    |
                    ▼
          FamPay Email Detected
                    |
                    ▼
          Transaction Verification
                    |
             ┌──────┴──────┐
             │             │
          MATCHED       NOT MATCHED
             │             │
             ▼             ▼
       Verify Payment   Continue Checking
             │
             ▼
       Invoice Marked Paid
             |
             ▼
       Discord Notification
             |
             ▼
       Payment Completed
```

### Detailed Process

1. **Invoice Initialization:** The customer opens an invoice and selects Fampay Gateway.
2. **QR Generation:** A dynamic UPI QR code and mobile payment link are generated.
3. **Payment Submission:** The customer completes the payment using their preferred UPI application.
4. **Email Monitoring:** The gateway periodically checks Gmail through IMAP.
5. **Transaction Matching:** Incoming payment notifications are parsed and matched against invoice references and amounts.
6. **Payment Confirmation:** A successfully verified transaction triggers invoice payment confirmation.
7. **Notifications:** Discord receives a notification containing the payment details.

---

## 🛡️ Transaction Matching & Safety

Handling multiple simultaneous payments requires careful transaction matching.

Fampay Gateway implements multiple safeguards to reduce incorrect payment assignments.

| Protection | Implementation |
|---|---|
| Unique References | Uses numeric invoice references to identify payments. |
| Exact Note Matching | Prioritizes matching the UPI payment remark with the invoice reference. |
| Amount Fallback | Uses amount-based matching only when a single eligible candidate exists. |
| Duplicate Prevention | Temporarily caches processed transaction IDs. |
| Ambiguous Payment Protection | Blocks automatic matching when multiple candidates exist. |
| Time Window Validation | Considers the relevant payment time window during verification. |

### Two-Pass Matching System

**Pass 1 — Reference Matching**

The gateway attempts to match the transaction remark against the invoice reference.

**Pass 2 — Amount Fallback**

If an exact reference match is unavailable, the gateway can use amount-based matching when only one eligible invoice candidate exists.

If multiple candidates exist, the gateway refuses ambiguous automatic matching.

> **Important:** Customers should always use the invoice reference shown on the payment page as their UPI payment remark. Amount-only matching is a fallback, not a replacement for reference matching.

---

## 🧪 Diagnostics & Testing

Fampay Gateway includes a built-in diagnostic interface to help administrators identify configuration and connectivity issues.

Access the test page using the URL provided in your gateway configuration.

```text
https://yourdomain.com/extensions/gateways/FampayGateway/test
```

### Available Diagnostic Checks

| Test | What It Checks |
|---|---|
| PHP IMAP | Checks whether the IMAP extension is installed. |
| UPI Validation | Validates the configured UPI ID format. |
| Gmail Configuration | Checks whether Gmail credentials are configured. |
| Gmail IMAP Connection | Tests Gmail authentication and connectivity. |
| Email Search | Searches for FamPay notifications from the last 7 days. |
| Email Parsing | Displays extracted information from the latest detected email. |
| Discord Webhook | Tests Discord webhook connectivity. |

The diagnostic page also provides useful error details to simplify troubleshooting.

---

## 🔧 Troubleshooting

Common problems and their possible solutions.

| Issue | Recommended Solution |
|---|---|
| IMAP connection failed | Verify your Gmail address and App Password. |
| IMAP extension missing | Install `php-imap` and restart PHP-FPM. |
| Payments not detected | Verify that FamPay notifications are reaching your Gmail inbox. |
| Incorrect payment matching | Ensure the customer uses the correct invoice reference as the UPI remark. |
| QR code not loading | Check outbound server connectivity to the configured QR generation provider. |
| Discord notifications missing | Verify your webhook URL and Discord channel permissions. |
| Timer expires unexpectedly | Verify your installed extension version and payment session configuration. |
| Gateway not appearing | Run `composer dump-autoload` and check Laravel logs. |

### Debugging

Enable Debug Mode in the gateway configuration.

Check your Laravel logs:

```bash
tail -f /var/www/paymenter/storage/logs/laravel.log
```

This can help identify Gmail authentication issues, email parsing failures, and transaction verification errors.

---

## 📂 Project Structure

```text
FampayGateway/
│
├── FampayGateway.php
│   └── Main gateway implementation
│
├── GmailVerifier.php
│   └── Gmail IMAP integration and payment verification
│
├── DiscordLogger.php
│   └── Discord webhook notifications
│
├── routes.php
│   └── Extension route definitions
│
├── resources/
│   └── views/
│       └── pay.blade.php
│           └── Customer payment interface
│
└── README.md
```

---

## 📋 Changelog

### Version 1.0.0 — Initial Release

**Core Payment Features**
- UPI QR code generation.
- Mobile UPI deep linking.
- Configurable payment expiration.
- Live payment status polling.
- Responsive premium payment interface.

**Payment Verification**
- Gmail IMAP integration.
- Gmail App Password authentication.
- Automatic FamPay notification detection.
- Two-pass transaction matching.
- Concurrent payment protection.
- Transaction deduplication.

**Discord Integration**
- Real-time payment event notifications.
- Seven notification event types.
- Detailed transaction information.
- Webhook connectivity testing.

**Administration**
- Visual diagnostic interface.
- Gmail connection testing.
- Latest email preview.
- Configurable debug mode.
- Admin-side gateway configuration.

---

## 🔐 Security & Important Notes

- Keep Gmail App Passwords private.
- Never expose Discord webhook URLs.
- Do not commit production credentials to public repositories.
- Use HTTPS for your Paymenter installation.
- Restrict access to diagnostic endpoints where appropriate.
- Regularly review Laravel logs for unexpected verification failures.
- Payment confirmation should only occur after successful transaction verification.

**Payment verification note:** This extension relies on Gmail notification delivery and parsing. Email delays, changed notification formats, or missing notifications can affect automatic detection. Always test the integration with real transactions before enabling it for production use.

---

## 📄 License

Fampay Gateway is distributed as a **Premium Proprietary Extension**.

All rights reserved.

- Unauthorized redistribution is prohibited.
- Reselling or sublicensing is prohibited.
- Modification and redistribution are subject to the applicable purchase or licensing agreement.

For licensing or commercial inquiries, contact the developer.

---

<div align="center">

## ⚡ Developed by Para.exe

**Engineering tools. Building infrastructure. Creating solutions.**

[GitHub](https://github.com/ItzMePara9)

<br>

*Fampay Gateway — Premium UPI Payments for Paymenter.*

**Made with ❤️ by Para.exe**

</div>
