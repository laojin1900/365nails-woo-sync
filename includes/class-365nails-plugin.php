<?php
/**
 * Core orchestration: activation bookkeeping, the WooCommerce-presence guard,
 * and the init seam later feature slices attach to.
 *
 * Deliberately tiny. The repo's rule (inherited from the portal's briefs) is
 * that every piece of real behaviour belongs to a named feature file — this
 * class only owns "is the plugin allowed to run, and what did (de)activation
 * do". When a later slice adds a feature (order panel, emails), it registers
 * itself from init() here; this file then stays readable as the table of
 * contents of what the plugin does.
 *
 * @package Nai365_Sync
 */

defined( 'ABSPATH' ) || exit;

final class Nai365_Sync {

	/**
	 * Register the hooks that exist in this version.
	 *
	 * The WooCommerce-missing notice is registered unconditionally: if a merchant
	 * deactivates WooCommerce while the plugin is on, "the plugin went silent"
	 * must not be the explanation they have to guess at.
	 */
	public static function init(): void {
		add_action( 'admin_notices', array( __CLASS__, 'maybe_woo_missing_notice' ) );

		// Feature registry — one line per slice, so this function reads as the
		// table of contents of what the plugin does.
		Nai365_Tracking_Display::init(); // W3: order-admin panel + shipping email.
	}

	/**
	 * Activation: record the version and refuse to run without WooCommerce.
	 *
	 * The stored version is the single point future upgrades read: distribution
	 * is a zip the merchant reinstalls by hand (no .org update channel — T22
	 * decision, 2026-09-18), so "the merchant just swapped the files" is a real
	 * and routine event, and any migration logic must be able to ask "from
	 * which version" without guessing.
	 */
	public static function activate(): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			deactivate_plugins( plugin_basename( NAI365_SYNC_FILE ) );
			wp_die(
				esc_html__( '365nails Sync requires WooCommerce to be installed and active. The plugin was deactivated.', '365nails-sync' ),
				'',
				array( 'back_link' => true )
			);
		}
		update_option( 'nai365_sync_version', NAI365_SYNC_VERSION, false );
	}

	/**
	 * Deactivation: intentionally writes nothing and deletes nothing.
	 *
	 * No webhooks, order meta, or options are removed. Two of those belong to
	 * the merchant or are maintained from the portal's side, and an uninstall
	 * that quietly sweeps the merchant's own rows is the worst kind of helpful.
	 * (A future explicit "remove data on uninstall" switch is a product
	 * decision, and is not this file's call.)
	 */
	public static function deactivate(): void {
		// Deliberately no-op — see the docblock.
	}

	/**
	 * Admin notice when the plugin is active without WooCommerce.
	 *
	 * `Requires Plugins: woocommerce` in the main header already blocks NEW
	 * activations on WP 6.5+; this covers the other window — WooCommerce being
	 * deactivated while the plugin is on.
	 */
	public static function maybe_woo_missing_notice(): void {
		if ( class_exists( 'WooCommerce' ) ) {
			return;
		}
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html__( '365nails Sync is active, but WooCommerce is not. The plugin is idle until WooCommerce is installed and active.', '365nails-sync' )
		);
	}
}
