<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * This file is executed when the plugin is deleted through the WordPress admin interface.
 * It removes all plugin data from the database including:
 * - Plugin options (settings)
 * - Cached API data (transients)
 *
 * @link       https://flaviowaser.ch
 * @since      1.0.0
 *
 * @package    SWFL
 */

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Delete all plugin data for a single site.
 *
 * @since    1.0.0
 */
function swfl_uninstall_site() {
	global $wpdb;

	// Delete plugin options, including the ones stored under the legacy `swissfloorball_` prefix.
	$suffixes = array(
		'api_key',
		'api_secret',
		'api_source',
		'club_number',
		'club_name',
		'actual_season',
		'request_timeout',
		'show_icons',
		'theme',
		'seed_color',
		'table_style',
		'table_striped',
		'table_accent_color',
		'table_header_color',
		'table_divider_color',
		'table_highlight_color',
	);
	foreach ( $suffixes as $suffix ) {
		delete_option( 'swfl_' . $suffix );
		delete_option( 'swissfloorball_' . $suffix );
	}
	delete_option( 'swfl_options_migrated' );
	delete_transient( 'swfl_partner_token' );

	// Delete all cached API data (transients with 'swfl_' prefix).
	// This includes both the transient values and their timeout entries.
	// Transients cannot be deleted by prefix via the transient API, and caching is pointless during uninstall.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Prefix-based transient cleanup is not possible via the transient API.
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
			$wpdb->esc_like( '_transient_swfl_' ) . '%',
			$wpdb->esc_like( '_transient_timeout_swfl_' ) . '%'
		)
	);
}

/**
 * Run the uninstall process.
 *
 * For multisite installations, iterate through all sites.
 * For single site installations, just clean up the current site.
 *
 * @since    1.0.0
 */
function swfl_uninstall() {
	if ( is_multisite() ) {
		// Get all sites in the network.
		$sites = get_sites( array( 'number' => 0 ) );

		foreach ( $sites as $site ) {
			// Switch to each site and run cleanup.
			switch_to_blog( $site->blog_id );
			swfl_uninstall_site();
			restore_current_blog();
		}
	} else {
		// Single site installation.
		swfl_uninstall_site();
	}
}

swfl_uninstall();
