=== PayGuard Payment Gateway for WooCommerce ===
Contributors: sanaullahAsif
Tags: bkash, nagad, payment gateway, bangladesh, woocommerce
Requires at least: 5.8
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPL-2.0+
License URI: https://www.gnu.org/licenses/gpl-2.0.html
WC requires at least: 6.0
WC tested up to: 9.0

Accept bKash, Nagad, Rocket and more in your WooCommerce store via PayGuard — zero commission payment gateway for Bangladesh.

== Description ==

**PayGuard** is Bangladesh's first zero-commission payment gateway. Accept bKash, Nagad, Rocket, Upay, Credit/Debit Card and TAP Wallet payments in your WooCommerce store with a single API key — no per-transaction commission, flat monthly subscription only.

= Why PayGuard? =

* **Zero commission** — flat monthly fee, no per-transaction cuts
* **Instant settlement** — money goes directly to YOUR bKash/Nagad merchant account
* **One API key** — manage bKash, Nagad, Rocket and cards under one dashboard
* **Signed webhooks** — HMAC-SHA256 verified IPN for secure order confirmation
* **Simple setup** — one API key, one connection ID, done

= Supported Payment Methods =

* bKash (Tokenized Checkout)
* Nagad
* Rocket (DBBL)
* Upay
* Credit / Debit Card (Visa, Mastercard, Amex)
* TAP Wallet

= Requirements =

* A PayGuard account — [sign up free at app.sourcemonkey.online](https://app.sourcemonkey.online)
* Your own bKash / Nagad / Rocket merchant account credentials added to PayGuard
* WordPress 5.8+, WooCommerce 6.0+, PHP 8.1+

== Installation ==

= Automatic Installation =

1. Go to **WP Admin → Plugins → Add New**
2. Search for **PayGuard Payment Gateway**
3. Click **Install Now** then **Activate**

= Manual Installation =

1. Download the plugin zip
2. Go to **WP Admin → Plugins → Add New → Upload Plugin**
3. Upload the zip and activate

= Configuration =

**Step 1 — PayGuard Dashboard**

Log in to [app.sourcemonkey.online](https://app.sourcemonkey.online) and collect:

* **API Key** — Dashboard → API & Webhooks → Generate Key
* **Webhook Secret** — Dashboard → Connections → Edit → Webhook Secret
* **Connection ID** — Dashboard → Connections → row ID

Set your store's Webhook URL in PayGuard to:
`https://yourdomain.com/wp-json/payguard/v1/ipn`

**Step 2 — WooCommerce Settings**

Go to **WP Admin → WooCommerce → Settings → Payments → PayGuard → Manage**

1. Enter your **API Key**, **Connection ID** and **Webhook Secret**
2. Select your default **Provider** (bKash, Nagad, Rocket etc.)
3. Click **Save changes**

== Frequently Asked Questions ==

= Do I need a PayGuard account? =

Yes. Sign up free at [app.sourcemonkey.online](https://app.sourcemonkey.online).

= Do I need my own bKash/Nagad merchant account? =

Yes. PayGuard connects on top of your existing merchant credentials.

= What are the fees? =

PayGuard charges a flat monthly subscription — no per-transaction commission. See [app.sourcemonkey.online](https://app.sourcemonkey.online) for current pricing.

= Is the webhook secure? =

Yes. Every IPN from PayGuard is signed with HMAC-SHA256. The plugin verifies the signature before processing any order.

= Which WooCommerce checkout is supported? =

Classic shortcode checkout. Block checkout compatibility coming in a future release.

= Does it work with WooCommerce HPOS? =

Yes — the plugin declares compatibility with High-Performance Order Storage.

== Screenshots ==

1. PayGuard settings page in WooCommerce — API key and connection ID
2. Checkout page — customer selects PayGuard and places order
3. PayGuard dashboard — manage all transactions and connections

== Changelog ==

= 1.0.0 =
* Initial public release
* Supports bKash, Nagad, Rocket, Upay, Card and TAP Wallet
* HMAC-SHA256 webhook signature verification
* WooCommerce HPOS compatible
* GPL-2.0+ licensed

== Upgrade Notice ==

= 1.0.0 =
Initial release.

== Support ==

* Documentation: [app.sourcemonkey.online/docs](https://app.sourcemonkey.online/docs)
* Dashboard: [app.sourcemonkey.online](https://app.sourcemonkey.online)
* Email: support@sourcemonkey.online
