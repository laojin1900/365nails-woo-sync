=== 365nails Sync ===
Contributors: 365nails
Tags: woocommerce, dropshipping, order sync, fulfillment, tracking
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 0.2.1
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Connects your WooCommerce store to 365nails dropshipping: orders flow to your 365nails portal, and tracking numbers flow back to the order — visible in your admin and in your customers' emails.

== Description ==

365nails Sync is the merchant-visible half of the 365nails dropshipping WooCommerce channel:

* **Dropshipping panel on the order screen** — tracking number, carrier, and tracking link, HPOS-compatible (custom order tables and classic storage alike).
* **Tracking in the customer's shipping email** — same information, without touching your theme templates.

It does not receive or verify webhooks (WooCommerce delivers straight to the portal's signed endpoint), and it does not change how your storefront promises delivery times — that part of your store is yours.

== Installation ==

1. Download `365nails-sync.zip` from the GitHub releases page.
2. Plugins → Add New → Upload Plugin → choose the zip → Activate.
3. WooCommerce must be installed and active.

== Upgrade notice ==

Reinstall the newer zip over the old one. Deactivation and replacement never delete your webhooks, order meta, or data.

== Changelog ==

= 0.2.1 =
* W4: plugins-page notice when a newer release exists on GitHub (the other half of the zip-reinstall maintenance promise).

= 0.2.0 =
* W3 merchant visibility: "365nails Dropshipping" panel on the order screen (HPOS + classic storage) and the tracking block in the customer's shipping email.

= 0.1.0 =
* Plugin skeleton: HPOS compatibility declaration, activation/deactivation, WooCommerce-presence guard.
