<?php
/**
 * Plugin Name:       365nails Sync
 * Plugin URI:        https://365nails.com
 * Description:       Connects your WooCommerce store to 365nails dropshipping: orders flow to your 365nails portal, and tracking numbers flow back — visible in your order admin and in your customers' emails.
 * Version:           0.1.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            365nails
 * Author URI:        https://365nails.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       365nails-sync
 * Requires Plugins:  woocommerce
 *
 * ─────────────────────────────────────────────────────────────────────────
 * WHAT THIS PLUGIN IS (and what it deliberately is NOT)
 *
 * It is the merchant-visible half of the WooCommerce channel in the
 * dropshipper portal (repo: dropshipper-portal, docs/maps/channel-expansion.md,
 * brief docs/channel-expansion-briefs/p1d-wp-plugin-brief.md).
 *
 * It does NOT receive or verify webhooks — WooCommerce delivers straight to
 * the portal's signed endpoint (D15: the plugin's original "收 webhook" role
 * was dropped after source-verifying the delivery direction). It does NOT
 * write delivery-time promises into the store (D19: that is the merchant's
 * own storefront decision, not ours).
 *
 * What it is FOR (brief §三, W1/W3):
 *  · HPOS-compatible "365nails dropshipping" panel on the order screen
 *    (tracking number / carrier / tracking URL), and the same info in the
 *    customer's shipping email — the only part of the chain that lives
 *    on the merchant's own site.
 *
 * v0.1.0 is the W1 skeleton: main file, HPOS declaration, activation /
 * deactivation, and the WooCommerce-presence guard. Feature slices land on
 * top of this; nothing here is dead code by accident, it is the load-bearing
 * minimum every later slice attaches to.
 * ─────────────────────────────────────────────────────────────────────────
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'NAI365_SYNC_VERSION', '0.1.0' );
define( 'NAI365_SYNC_FILE', __FILE__ );
define( 'NAI365_SYNC_PATH', plugin_dir_path( __FILE__ ) );

require_once NAI365_SYNC_PATH . 'includes/class-365nails-plugin.php';

/**
 * HPOS (custom order tables) compatibility declaration.
 *
 * Source-verified API (woocommerce/woocommerce, src/Utilities/FeaturesUtil.php:87):
 *   declare_compatibility( string $feature_id, string $plugin_file,
 *                          bool $positive_compatibility = true ): bool
 * Feature id for HPOS is 'custom_order_tables'.
 *
 * Without this declaration WooCommerce puts the plugin on its "incompatible
 * plugins" screen and — worse — a merchant turning HPOS on later meets the
 * classic silent-breakage class (order meta written against the wrong
 * storage). One line now is cheaper than that.
 */
add_action(
	'before_woocommerce_init',
	function (): void {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', NAI365_SYNC_FILE, true );
		}
	}
);

register_activation_hook( NAI365_SYNC_FILE, array( 'Nai365_Sync', 'activate' ) );
register_deactivation_hook( NAI365_SYNC_FILE, array( 'Nai365_Sync', 'deactivate' ) );

Nai365_Sync::init();
