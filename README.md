# PayGuard Payment Gateway for WooCommerce

[![Version](https://img.shields.io/badge/version-3.0.0-blue)](https://github.com/abefimrs/payguard-woocommerce)
[![WooCommerce](https://img.shields.io/badge/WooCommerce-6.0%2B-purple)](https://woocommerce.com)
[![PHP](https://img.shields.io/badge/PHP-8.1%2B-blue)](https://php.net)
[![License](https://img.shields.io/badge/license-GPL--2.0-green)](LICENSE)

Accept **bKash, Nagad, Rocket, Upay, Credit/Debit Card and TAP Wallet** payments in your WooCommerce store via [PayGuard](https://app.sourcemonkey.online) — the zero commission payment gateway built for Bangladesh merchants.

---

## How It Looks

**Admin — one settings page, one API key:**

```
PayGuard Settings
─────────────────────────────────────────
API Key          ••••••••••••••••••••
Webhook Secret   ••••••••••••••••••••

Payment Methods
─────────────────────────────────────────
☑ bKash              Connection ID  [12]
☑ Nagad              Connection ID  [14]
☑ Rocket             Connection ID  [16]
☑ Upay               Connection ID  [18]
☑ Credit/Debit Card  Connection ID  [20]
☑ TAP Wallet         Connection ID  [22]
```

**Checkout — customer picks their method:**

```
○ PayGuard — Mobile Banking / Card
  ● [bKash]   bKash
  ○ [Nagad]   Nagad
  ○ [Rocket]  Rocket
  ○ [Upay]    Upay
  ○ [VISA][MC][AMEX]  Credit / Debit Card
  ○ [TAP]     TAP Wallet
```

Only enabled methods (with a connection ID set) appear at checkout.

---

## Requirements

| Requirement | Version |
|---|---|
| WordPress | 5.8+ |
| WooCommerce | 6.0+ |
| PHP | 8.1+ |
| PayGuard account | [app.sourcemonkey.online](https://app.sourcemonkey.online) |

---

## Installation

### Option A — Upload via WordPress Admin (recommended)

1. Download the latest release zip from [GitHub Releases](https://github.com/abefimrs/payguard-woocommerce/releases)
2. Go to **WP Admin → Plugins → Add New → Upload Plugin**
3. Upload the zip, click **Install Now**, then **Activate**

### Option B — Manual

1. Clone or download this repo
2. Copy the entire folder into `wp-content/plugins/payguard-payment-gateway/`
3. Go to **WP Admin → Plugins** and activate **PayGuard Payment Gateway**

### Option C — WP-CLI

```bash
wp plugin install https://github.com/abefimrs/payguard-woocommerce/archive/refs/heads/main.zip --activate
```

---

## Configuration

### Step 1 — PayGuard Dashboard

Log into [app.sourcemonkey.online](https://app.sourcemonkey.online) and collect:

| Item | Where |
|---|---|
| API Key | Dashboard → API & Webhooks → Generate Key |
| Webhook Secret | Dashboard → Connections → Edit → Webhook Secret |
| Connection IDs | Dashboard → Connections → each row's ID column |

Set your store's **Webhook URL** in PayGuard to:
```
https://yourdomain.com/wp-json/payguard/v1/ipn
```

### Step 2 — WooCommerce Settings

Go to **WP Admin → WooCommerce → Settings → Payments → PayGuard → Manage**

1. Enter your **API Key**, **API Base URL**, and **Webhook Secret**
2. For each payment method you want to offer:
   - Tick **Enable**
   - Enter the **Connection ID** from your PayGuard dashboard
3. Click **Save changes**

That's it. Only methods with a connection ID entered will appear at checkout.

---

## Payment Flow

```
Customer selects method (e.g. bKash) and clicks "Place Order"
    ↓
PayGuard transaction created  (POST /api/v1/transactions)
    ↓
Provider initiated            (POST /api/v1/bkash/initiate/{id})
    ↓
Customer redirected to bKash / Nagad / etc. checkout page
    ↓
Customer completes payment
    ↓
Browser returns to your store  (/wc-api/payguard_callback)
    → Order marked Processing, customer sees confirmation page
    ↓
PayGuard server sends signed webhook  (POST /wp-json/payguard/v1/ipn)
    → Signature verified (HMAC-SHA256)
    → Order confirmed as paid  ← authoritative source of truth
```

The webhook is the authoritative confirmation. The browser callback is a best-effort for the customer experience — it also marks the order paid immediately so the customer sees the confirmation page even if the webhook is slightly delayed.

---

## Webhook Security

Every IPN request from PayGuard is signed with HMAC-SHA256:

```
Header: X-PayGuard-Signature: <hmac>
```

The plugin verifies this signature using your Webhook Secret before processing any order. Requests with missing or invalid signatures are rejected with HTTP 401.

---

## Logos

The plugin ships with placeholder SVG logos in brand colours. Replace them with official brand assets before going live:

| File | Method |
|---|---|
| `assets/logos/bkash.svg` | bKash — [developer.bkash.com](https://developer.bkash.com) |
| `assets/logos/nagad.svg` | Nagad — [nagad.com.bd](https://nagad.com.bd) |
| `assets/logos/rocket.svg` | Rocket — [dutchbanglabank.com](https://www.dutchbanglabank.com) |
| `assets/logos/upay.svg` | Upay — [upaybd.com](https://upaybd.com) |
| `assets/logos/card-visa.svg` | Visa — [visa.com](https://www.visa.com) Brand Center |
| `assets/logos/card-mastercard.svg` | Mastercard — [brand.mastercard.com](https://brand.mastercard.com) |
| `assets/logos/card-amex.svg` | Amex — [americanexpress.com](https://www.americanexpress.com) Brand Standards |
| `assets/logos/tap.svg` | TAP Wallet — from PayGuard/TAP dashboard |

PNG or SVG both work. Drop the new file in with the same filename — no code changes needed.

---

## Local Development (Testing Without HTTPS)

WooCommerce hides payment gateways on non-HTTPS checkout by default. Add a must-use plugin to disable that restriction locally:

```bash
mkdir -p wp-content/mu-plugins

cat > wp-content/mu-plugins/local-dev.php << 'EOF'
<?php
// Local dev only — remove before production
add_filter('woocommerce_force_ssl_checkout', '__return_false');
add_filter('woocommerce_is_https', '__return_true');
EOF
```

### Webhook testing with ngrok

PayGuard can't reach `localhost`. Use [ngrok](https://ngrok.com):

```bash
ngrok http 8002
```

Update WordPress to use the ngrok URL:

```bash
wp option update siteurl "https://xxxx.ngrok-free.app" --allow-root
wp option update home    "https://xxxx.ngrok-free.app" --allow-root
```

Set the webhook URL in PayGuard dashboard to:
```
https://xxxx.ngrok-free.app/wp-json/payguard/v1/ipn
```

Reset when done:
```bash
wp option update siteurl "http://localhost:8002" --allow-root
wp option update home    "http://localhost:8002" --allow-root
```

---

## File Structure

```
payguard-payment-gateway/
├── payguard-payment-gateway.php      ← Plugin entry point
├── includes/
│   ├── class-payguard-gateway.php    ← Main gateway (settings, UI, payment processing)
│   └── class-payguard-ipn-handler.php ← Signed webhook handler
└── assets/
    ├── checkout.css                  ← Radio button UI styles
    ├── checkout.js                   ← Selected state JS
    └── logos/
        ├── bkash.svg
        ├── nagad.svg
        ├── rocket.svg
        ├── upay.svg
        ├── card-visa.svg
        ├── card-mastercard.svg
        ├── card-amex.svg
        └── tap.svg
```

---

## Changelog

### 3.0.0
- Redesigned as single gateway with radio button method selector at checkout
- Added Rocket and Upay support
- Split Card and TAP into separate selectable methods
- One API key, per-method connection IDs
- HMAC-SHA256 webhook signature verification
- `&amp;` encoding fix for callback URL query params
- Placeholder SVG logos for all providers

### 2.0.0
- Separate WooCommerce gateway per provider (bKash, Nagad, Card)

### 1.0.0
- Initial release — single gateway with provider dropdown

---

## Support

- PayGuard docs: [app.sourcemonkey.online/docs](https://app.sourcemonkey.online/docs)
- PayGuard dashboard: [app.sourcemonkey.online](https://app.sourcemonkey.online)
- Issues: [github.com/abefimrs/payguard-woocommerce/issues](https://github.com/abefimrs/payguard-woocommerce/issues)

---

## License

GPL-2.0+ — see [LICENSE](LICENSE)
