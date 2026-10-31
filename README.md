<div align="center">

# ⚡ Fampay Gateway

### Premium FamPay UPI Payment Gateway for Paymenter

**Accept UPI payments instantly with QR codes, mobile deep-linking, automatic Gmail verification, and real-time Discord logging.**

[![Version](https://img.shields.io/badge/version-1.0.0-6366f1?style=for-the-badge))
[![Paymenter](https://img.shields.io/badge/Paymenter-v2-22c55e?style=for-the-badge)](https://paymenter.org)
[![PHP](https://img.shields.io/badge/PHP-8.1+-8B5CF6?style=for-the-badge)](https://php.net)
[![License](https://img.shields.io/badge/license-Premium-f59e0b?style=for-the-badge)](#license)

---

</div>

## 🎯 What is Fampay Gateway?

Fampay Gateway is a **native Paymenter v2 extension** that enables UPI payments via FamPay. It provides a complete payment flow — from generating a QR code to automatically verifying the payment via Gmail — without any manual intervention.

**Built for Indian businesses** that need to accept UPI payments through their Paymenter billing panel.

---

## ✨ Features

### 💳 Payment Experience
- **Dynamic UPI QR Code** — Auto-generated with amount and reference pre-filled
- **Mobile UPI Deep Link** — "Pay using UPI App" button opens GPay, PhonePe, Paytm, etc.
- **Countdown Timer** — Animated ring timer with configurable expiry (default: 10 min)
- **Real-time Status** — Live polling with instant confirmation on payment
- **Premium UI** — Professional dark theme with glassmorphism, gradients, and micro-animations

### 🔍 Automatic Payment Verification
- **Gmail IMAP Scanning** — Automatically checks Gmail for FamPay payment notification emails
- **Smart Matching** — Two-pass matching system (note match → amount fallback) for accuracy
- **Concurrent Payment Safety** — Unique digits-only invoice references prevent cross-matching
- **Transaction Deduplication** — Cache-based dedup prevents double-claiming
- **Multiple Regex Patterns** — Robust email parsing handles various FamPay email formats

### 📊 Discord Webhook Logging
- **Real-time Embeds** — Color-coded Discord messages for every payment event
- **7 Event Types** — Gateway triggered, email checking, email found, confirmed, expired, errors, test results
- **Full Details** — Invoice ID, amount, sender, transaction ID, UTR, note match status

### 🔧 Admin Tools
- **Visual Test Page** — One-click diagnostic page tests IMAP, UPI, Gmail, Discord
- **Detailed Error Messages** — Every failure includes a fix suggestion
- **Latest Email Preview** — See exactly what the gateway extracts from FamPay emails
- **Debug Mode** — Verbose Laravel logging for troubleshooting
- **All Config in Admin UI** — No file editing required

---

## 📦 Requirements

| Requirement | Details |
|---|---|
| **Paymenter** | v2 (Laravel-based) |
| **PHP** | 8.1 or higher |
| **PHP IMAP Extension** | `php-imap` |
| **Gmail Account** | With 2-Step Verification + App Password |
| **FamPay Account** | UPI ID that receives payments |
| **Discord Webhook** | *(Optional)* For real-time logging |

---

## 🚀 Installation

### Step 1 — Install PHP IMAP Extension

```bash
# Ubuntu / Debian
sudo apt install php-imap
sudo systemctl restart php8.2-fpm

# Verify
php -m | grep imap
```

### Step 2 — Upload Extension

Upload the `FampayGateway` folder to your Paymenter extensions directory:

```
/var/www/paymenter/extensions/Gateways/FampayGateway/
```

### Step 3 — Dump Autoload

```bash
cd /var/www/paymenter
composer dump-autoload
```

### Step 4 — Enable in Admin Panel

1. Go to **Admin → Gateways → Create**
2. Select **Fampay Gateway**
3. Fill in the configuration fields
4. Save

---

## ⚙️ Configuration

| Setting | Description | Required |
|---|---|---|
| **UPI ID** | Your FamPay UPI ID (e.g., `user@fam`) | ✅ |
| **Payee Name** | Name shown to payer on UPI screen | ✅ |
| **Gmail Email** | Gmail receiving FamPay notifications | ✅ |
| **Gmail App Password** | 16-char App Password from Google | ✅ |
| **Payment Timeout** | QR expiry time in minutes (default: 10) | ✅ |
| **Poll Interval** | Check frequency in seconds (default: 10) | ✅ |
| **FamPay Sender** | FamPay's notification email | ✅ |
| **Debug Mode** | Enable verbose logging | ❌ |
| **Discord Webhook** | Webhook URL for payment logs | ❌ |
| **Test Connection** | Auto-generated test page URL | ❌ |

---

## 🔑 Gmail App Password Setup

**No Google Cloud project needed — takes 2 minutes.**

1. **Enable 2-Step Verification** → [Google Security](https://myaccount.google.com/security)
2. **Generate App Password** → [App Passwords](https://myaccount.google.com/apppasswords)
3. **Enter in Paymenter** → Gmail Email + 16-char App Password

---

## 🔄 How It Works

```
┌─────────────────────────────────────────────────┐
│  1. User clicks "Pay Invoice"                   │
│     → QR code + UPI button displayed            │
│     → 10-minute countdown starts                │
│     → Discord: 🔔 Gateway Triggered             │
├─────────────────────────────────────────────────┤
│  2. User scans QR / taps UPI button             │
│     → Payment made with auto-filled amount      │
│     → Reference: invoice digits only            │
├─────────────────────────────────────────────────┤
│  3. Page polls server every N seconds           │
│     → Server checks Gmail via IMAP              │
│     → Matches amount + reference + time window  │
│     → Discord: 📧 Checking / 📬 Found           │
├─────────────────────────────────────────────────┤
│  4. Payment confirmed                           │
│     → Invoice marked as paid automatically      │
│     → User redirected to services               │
│     → Discord: ✅ Confirmed                      │
└─────────────────────────────────────────────────┘
```

---

## 🛡️ Concurrent Payment Safety

When multiple users purchase simultaneously:

| Feature | How It Protects |
|---|---|
| **Unique Reference** | Each invoice gets its numeric invoice ID or number as the UPI note |
| **Two-Pass Matching** | Pass 1: exact note match. Pass 2: amount-only (single candidate only) |
| **Transaction Dedup** | Confirmed txn IDs are cached 30min to prevent double-claiming |
| **Multi-Candidate Block** | Refuses to auto-match when multiple payments have same amount but no note match |

---

## 🧪 Testing

Visit the built-in diagnostic page (URL auto-shown in admin config):

```
https://yourdomain.com/extensions/gateways/FampayGateway/test
```

Tests performed:
- ✅ PHP IMAP extension installed
- ✅ UPI ID format validation
- ✅ Gmail credentials present
- ✅ Gmail IMAP connection (with auth error details)
- ✅ FamPay email search (last 7 days)
- ✅ Latest email parsing preview
- ✅ Discord webhook connectivity

---

## 📁 File Structure

```
FampayGateway/
├── FampayGateway.php          # Main gateway (config, pay, check, test)
├── GmailVerifier.php          # IMAP email checking + parsing
├── DiscordLogger.php          # Discord webhook embeds
├── routes.php                 # Route definitions
├── resources/
│   └── views/
│       └── pay.blade.php      # Payment UI
└── README.md
```

---

## 🔧 Troubleshooting

| Problem | Solution |
|---|---|
| **IMAP connection failed** | Verify App Password. Generate a new one at [Google App Passwords](https://myaccount.google.com/apppasswords) |
| **php-imap not installed** | `sudo apt install php-imap && sudo systemctl restart php*-fpm` |
| **Payment not detected** | Enable Debug Mode → check `storage/logs/laravel.log`. Verify FamPay emails are in inbox (not spam) |
| **QR not loading** | Ensure server can reach `api.qrserver.com`. Falls back to Google Charts, then text display |
| **Timer expires instantly** | Update to latest version — this was fixed with session-based timer start |
| **Wrong payment matched** | Users must keep the UPI remark as the invoice digits shown on the page. The UI warns them about this |

---

## 📝 Changelog

### v1.0.0
- Initial release
- UPI QR code generation + mobile deep linking
- Gmail IMAP payment verification with App Passwords
- Two-pass matching with concurrent payment safety
- Discord webhook logging (7 event types)
- Visual admin test page
- Premium payment UI with responsive design

---

## 📄 License

This is a **premium extension**. All rights reserved.
Unauthorized redistribution or resale is prohibited.

---

<div align="center">

**Built with ❤️ by Para.exe**

*Fampay Gateway — Developed by para.dev*

</div>
