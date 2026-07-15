=== PayGuard Payment Gateway ===
Contributors: sanaullahAsif
Tags: payment, bkash, nagad, tap, bangladesh, woocommerce
Requires at least: 5.8
Tested up to: 6.4
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPLv2 or later

Accept bKash, Nagad, TAP and more via PayGuard — zero commission payment gateway for Bangladesh.

== Description ==

PayGuard allows WooCommerce stores in Bangladesh to accept payments via:
* bKash (Tokenized Checkout & Dynamic Charging)
* Nagad
* TAP Wallet
* More providers coming soon

**Why PayGuard?**
* Zero per-transaction commission — flat monthly fee only
* Money goes directly to YOUR account — no settlement delays
* One plugin, all providers
* Real-time IPN webhook notifications
* Automatic order status updates

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`
2. Activate via Plugins menu in WordPress
3. Go to WooCommerce → Settings → Payments → PayGuard
4. Enter your API key from https://app.sourcemonkey.online/merchant/api
5. Enter your MFS Connection ID
6. Enter your Webhook Secret
7. Save settings

== Configuration ==

= Getting Your API Key =
1. Register at https://app.sourcemonkey.online/merchant/register
2. Add your bKash/Nagad connection under MFS Connections
3. Go to API & Webhooks → Generate Key

= Webhook URL =
Set this as your IPN callback in PayGuard:
`https://yoursite.com/wp-json/payguard/v1/ipn`

== Frequently Asked Questions ==

= Do I need my own bKash account? =
Yes. You bring your own bKash merchant account credentials. PayGuard stores them securely and handles all API calls.

= Is there a transaction commission? =
No. PayGuard charges a flat monthly subscription fee only. Zero per-transaction commission.

= How fast is settlement? =
Instant. Money goes directly to your bKash/Nagad merchant account — PayGuard never holds your funds.

== Changelog ==

= 1.0.0 =
* Initial release
* bKash, Nagad, TAP support
* IPN webhook handler
* Refund support

== Upgrade Notice ==

= 1.0.0 =
Initial release.