<?php
/**
 * W3 — the merchant-visible half of the chain: a "365nails dropshipping"
 * panel on the order screen, and the same tracking block in the customer's
 * shipping email.
 *
 * ── WHERE THE DATA COMES FROM ────────────────────────────────────────────
 * The portal (dropshipper-portal, P1d) already DOUBLE-writes tracking to the
 * Woo order when a shipment goes out:
 *   `_365_tracking_number` / `_365_carrier` / `_365_tracking_url`
 * (order meta — machine-readable, F4 in the P1 brief: `update_meta_data` is
 * source-verified writable) plus an order note for the merchant's eyes. This
 * plugin only READS that meta and renders it. It never writes order data —
 * the store's orders are not ours to edit.
 *
 * ── WHY THE PANEL IS A META BOX (F3/F10, source-verified) ────────────────
 * HPOS's OrderEdit controller fires the STANDARD WordPress meta box API
 * (`do_action('add_meta_boxes', $screen_id, $order)` and the specific
 * `add_meta_boxes_{$screen_id}`) — `src/Internal/Admin/Orders/Edit.php:170,180`.
 * So `add_meta_box()` works in BOTH storage modes through the generic hook,
 * provided the callback checks the screen id instead of assuming one:
 *   · classic (CPT):            `shop_order`
 *   · HPOS, menu visible:       `woocommerce_page_wc-orders`
 *   · HPOS, menu hidden:        `admin_page_wc-orders`
 *   · custom order types:       either of the two above with a `--<type>` suffix
 * (`src/Internal/Admin/Orders/PageController.php:150-151`). Hooking ONE exact
 * screen id would make the panel silently vanish for merchants on the other
 * storage mode or with a custom order type — the exact silent-breakage class
 * this codebase refuses to ship.
 *
 * ── WHY THE EMAIL USES HOOKS, NOT TEMPLATE OVERRIDES ─────────────────────
 * `woocommerce_email_after_order_table` passes ($order, $sent_to_admin,
 * $plain_text, $email) (templates/emails/email-order-details.php:49,225).
 * A template override would render for the ADMIN copy too and would be wiped
 * by every theme update. Hooks do neither.
 *
 * @package Nai365_Sync
 */

defined( 'ABSPATH' ) || exit;

final class Nai365_Tracking_Display {

	/** Order meta keys written by the portal (dropshipper-portal api-version.ts). */
	private const META_NUMBER = '_365_tracking_number';
	private const META_CARRIER = '_365_carrier';
	private const META_URL = '_365_tracking_url';

	/**
	 * Screen-id prefixes on which the order editor lives. Exact matches are
	 * intentionally NOT used — see the file header (F10).
	 */
	private const ORDER_SCREEN_PREFIXES = array(
		'shop_order',
		'woocommerce_page_wc-orders',
		'admin_page_wc-orders',
	);

	public static function init(): void {
		add_action( 'add_meta_boxes', array( __CLASS__, 'register_meta_box' ), 10, 2 );
		add_action( 'woocommerce_email_after_order_table', array( __CLASS__, 'render_email_block' ), 10, 4 );
	}

	/**
	 * Register the panel on whatever screen the order editor actually is.
	 *
	 * @param string $screen_id Screen id from the generic add_meta_boxes hook.
	 */
	public static function register_meta_box( $screen_id ): void {
		foreach ( self::ORDER_SCREEN_PREFIXES as $prefix ) {
			if ( 0 === strpos( (string) $screen_id, $prefix ) ) {
				add_meta_box(
					'nai365-sync-tracking',
					__( '365nails Dropshipping', '365nails-sync' ),
					array( __CLASS__, 'render_meta_box' ),
					$screen_id,
					'side',
					'default'
				);
				return;
			}
		}
	}

	/**
	 * Resolve the order from whatever the meta box renderer hands us:
	 * a WC_Order (HPOS) or a WP_Post (classic).
	 *
	 * @param mixed $post_or_order WC_Order | WP_Post | anything.
	 * @return WC_Order|null
	 */
	private static function resolve_order( $post_or_order ) {
		if ( $post_or_order instanceof \WC_Order ) {
			return $post_or_order;
		}
		if ( $post_or_order instanceof \WP_Post ) {
			$order = wc_get_order( (int) $post_or_order->ID );
			return $order instanceof \WC_Order ? $order : null;
		}
		return null;
	}

	/**
	 * The three meta values, normalized. Empty string means "not shipped by
	 * 365nails" — we never invent a partial record.
	 *
	 * @return array{number:string,carrier:string,url:string}
	 */
	private static function tracking_for( \WC_Order $order ): array {
		return array(
			'number'  => trim( (string) $order->get_meta( self::META_NUMBER ) ),
			'carrier' => trim( (string) $order->get_meta( self::META_CARRIER ) ),
			'url'     => trim( (string) $order->get_meta( self::META_URL ) ),
		);
	}

	/**
	 * The order-admin panel. Always rendered (it is the product's visibility
	 * point), with a calm empty state when the order has no 365nails shipment.
	 *
	 * @param mixed $post_or_order WC_Order (HPOS) | WP_Post (classic).
	 */
	public static function render_meta_box( $post_or_order ): void {
		$order = self::resolve_order( $post_or_order );
		if ( ! $order ) {
			return;
		}

		$t = self::tracking_for( $order );

		if ( '' === $t['number'] && '' === $t['carrier'] ) {
			echo '<p>' . esc_html__( 'No 365nails tracking on this order yet. It will appear here once the order ships.', '365nails-sync' ) . '</p>';
			return;
		}

		echo '<ul class="nai365-tracking">';
		if ( '' !== $t['carrier'] ) {
			echo '<li><strong>' . esc_html__( 'Carrier', '365nails-sync' ) . ':</strong> ' . esc_html( $t['carrier'] ) . '</li>';
		}
		if ( '' !== $t['number'] ) {
			$label = esc_html( $t['number'] );
			$value = ( '' !== $t['url'] )
				? '<a href="' . esc_url( $t['url'] ) . '" target="_blank" rel="noopener noreferrer">' . $label . '</a>'
				: $label;
			echo '<li><strong>' . esc_html__( 'Tracking', '365nails-sync' ) . ':</strong> ' . $value . '</li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- parts escaped above.
		}
		echo '</ul>';
	}

	/**
	 * The customer's shipping email block. Silent for the admin copy (the admin
	 * already has the order panel) and silent when there is no tracking — an
	 * empty box in a customer email is a broken-looking email.
	 *
	 * @param WC_Order $order         The order.
	 * @param bool     $sent_to_admin Admin copy?
	 * @param bool     $plain_text    Plain-text email?
	 */
	public static function render_email_block( $order, $sent_to_admin, $plain_text ): void {
		if ( $sent_to_admin ) {
			return;
		}
		if ( ! $order instanceof \WC_Order ) {
			return;
		}

		$t = self::tracking_for( $order );
		if ( '' === $t['number'] && '' === $t['carrier'] ) {
			return;
		}

		if ( $plain_text ) {
			echo "\n" . esc_html__( 'TRACKING', '365nails-sync' ) . "\n\n";
			if ( '' !== $t['carrier'] ) {
				echo esc_html__( 'Carrier', '365nails-sync' ) . ': ' . esc_html( $t['carrier'] ) . "\n";
			}
			if ( '' !== $t['number'] ) {
				echo esc_html__( 'Tracking', '365nails-sync' ) . ': ' . esc_html( $t['number'] ) . "\n";
			}
			if ( '' !== $t['url'] ) {
				echo esc_html( $t['url'] ) . "\n";
			}
			return;
		}

		// HTML email: one bordered block in the WooCommerce email style (their own
		// templates use the same border-top + padding rhythm).
		echo '<div style="margin:0 0 16px;padding:12px;border:1px solid #e5e5e5;">';
		echo '<h2 style="display:block;margin:0 0 8px;font-size:18px;line-height:1.3;">' . esc_html__( 'Tracking', '365nails-sync' ) . '</h2>';
		echo '<p style="margin:0;">';
		if ( '' !== $t['carrier'] ) {
			echo esc_html__( 'Carrier', '365nails-sync' ) . ': <strong>' . esc_html( $t['carrier'] ) . '</strong><br/>';
		}
		if ( '' !== $t['number'] ) {
			echo esc_html__( 'Tracking', '365nails-sync' ) . ': ';
			if ( '' !== $t['url'] ) {
				echo '<a href="' . esc_url( $t['url'] ) . '">' . esc_html( $t['number'] ) . '</a>';
			} else {
				echo '<strong>' . esc_html( $t['number'] ) . '</strong>';
			}
		}
		echo '</p></div>';
	}
}
