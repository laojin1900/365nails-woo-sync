<?php
/**
 * W4 (W0-b) — the "new version available" notice, the second half of the
 * narrowed maintenance promise: distribution is a zip the merchant reinstalls
 * by hand (no .org update channel, no auto-update), so the plugin itself must
 * TELL the merchant when a newer release exists, or "zip reinstall" silently
 * becomes "never updated".
 *
 * ── HOW IT WORKS ─────────────────────────────────────────────────────────
 * On the plugins screen only, compare NAI365_SYNC_VERSION against the latest
 * GitHub release tag (cached in a transient for 12h — one API call per site
 * per half day, not per page load). If the release is newer, show ONE admin
 * notice pointing at the releases page. That's the whole feature.
 *
 * ── THREE DELIBERATE LIMITS ──────────────────────────────────────────────
 *  · plugins.php ONLY: a "please upgrade" nag on every admin page teaches
 *    merchants to ignore notices (and it would show on screens the merchant
 *    never connects to the plugin).
 *  · Fetch failure is SILENT: a site that cannot reach GitHub gets no notice
 *    and no error — an upgrade reminder is never worth alarming anyone about
 *    their network. The next transient window tries again.
 *  · It never installs anything: the zip is a human act by design (T22).
 *
 * @package Nai365_Sync
 */

defined( 'ABSPATH' ) || exit;

final class Nai365_Update_Notice {

	private const TRANSIENT = 'nai365_sync_latest_release';
	private const CACHE_TTL = 43200; // 12 hours.
	private const API_URL = 'https://api.github.com/repos/laojin1900/365nails-woo-sync/releases/latest';
	private const RELEASES_URL = 'https://github.com/laojin1900/365nails-woo-sync/releases';

	public static function init(): void {
		add_action( 'admin_notices', array( __CLASS__, 'maybe_notice' ) );
	}

	public static function maybe_notice(): void {
		if ( ! current_user_can( 'update_plugins' ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'plugins' !== $screen->id ) {
			return;
		}

		$latest = self::latest_release_tag();
		if ( null === $latest ) {
			return;
		}
		if ( ! version_compare( $latest, NAI365_SYNC_VERSION, '>' ) ) {
			return;
		}

		printf(
			'<div class="notice notice-info"><p>%s</p></div>',
			sprintf(
				/* translators: 1: latest version, 2: current version, 3: releases page URL. */
				wp_kses(
					__( 'A newer 365nails Sync is available: <strong>%1$s</strong> (you have %2$s). Download the zip from the <a href="%3$s">releases page</a> and upload it over this one — reinstalling never deletes your data.', '365nails-sync' ),
					array( 'strong' => array(), 'a' => array( 'href' => array() ) )
				),
				esc_html( $latest ),
				esc_html( NAI365_SYNC_VERSION ),
				esc_url( self::RELEASES_URL )
			)
		);
	}

	/**
	 * Latest release tag from the GitHub API, cached for CACHE_TTL seconds.
	 * Returns null on any failure — the caller treats that as "say nothing".
	 */
	private static function latest_release_tag(): ?string {
		$cached = get_transient( self::TRANSIENT );
		if ( false !== $cached ) {
			return is_string( $cached ) && '' !== $cached ? $cached : null;
		}

		$res = wp_remote_get(
			self::API_URL,
			array(
				'timeout' => 5,
				'headers' => array(
					'Accept' => 'application/vnd.github+json',
					// GitHub requires a UA; identify honestly, do not impersonate a browser.
					'User-Agent' => '365nails-sync-plugin/' . NAI365_SYNC_VERSION,
				),
			)
		);

		$tag = null;
		if ( ! is_wp_error( $res ) && 200 === (int) wp_remote_retrieve_response_code( $res ) ) {
			$body = json_decode( (string) wp_remote_retrieve_body( $res ), true );
			if ( is_array( $body ) && isset( $body['tag_name'] ) && is_string( $body['tag_name'] ) ) {
				$tag = ltrim( trim( $body['tag_name'] ), 'vV' );
				if ( '' === $tag ) {
					$tag = null;
				}
			}
		}

		// Cache successes AND failures (empty string = "we asked, it failed") so a
		// network blip doesn't turn into one API call per admin page load.
		set_transient( self::TRANSIENT, $tag ?? '', self::CACHE_TTL );
		return $tag;
	}
}
