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
 * @package    Swiss_Floorball_Api
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
function swiss_floorball_api_uninstall_site() {
	global $wpdb;

	// Delete plugin options.
	delete_option( 'swissfloorball_api_key' );
	delete_option( 'swissfloorball_club_number' );
	delete_option( 'swissfloorball_club_name' );
	delete_option( 'swissfloorball_actual_season' );

	// Delete all cached API data (transients with 'swfl_' prefix)
	// This includes both the transient values and their timeout entries.
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
if ( is_multisite() ) {
	// Get all sites in the network.
	$sites = get_sites( array( 'number' => 0 ) );

	foreach ( $sites as $site ) {
		// Switch to each site and run cleanup.
		switch_to_blog( $site->blog_id );
		swiss_floorball_api_uninstall_site();
		restore_current_blog();
	}
} else {
	// Single site installation.
	swiss_floorball_api_uninstall_site();
}
