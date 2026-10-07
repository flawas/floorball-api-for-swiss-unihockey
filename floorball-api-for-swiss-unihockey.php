<?php
/**
 * Compatibility loader for installations that activated the plugin under its old main file name.
 *
 * The main file is now swiss-floorball-api.php. WordPress stores the path of the main file of every
 * active plugin, so without this file those sites would lose the activation when they update. The
 * file has no plugin header on purpose, so it is not listed as a second plugin.
 *
 * @link       https://flaviowaser.ch
 * @since      2.0.2
 *
 * @package    SWFL
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Point the stored activation at the new main file.
 *
 * @since 2.0.2
 * @return void
 */
function swfl_redirect_legacy_activation() {
	$legacy = plugin_basename( __FILE__ );
	$main   = dirname( $legacy ) . '/swiss-floorball-api.php';

	$active = get_option( 'active_plugins', array() );
	if ( is_array( $active ) && in_array( $legacy, $active, true ) ) {
		$active = array_values( array_unique( str_replace( $legacy, $main, $active ) ) );
		update_option( 'active_plugins', $active );
	}

	// The auto-update setting is stored per basename as well, so it would silently stop otherwise.
	$auto_update = get_site_option( 'auto_update_plugins', array() );
	if ( is_array( $auto_update ) && in_array( $legacy, $auto_update, true ) ) {
		$auto_update = array_values( array_unique( str_replace( $legacy, $main, $auto_update ) ) );
		update_site_option( 'auto_update_plugins', $auto_update );
	}

	if ( is_multisite() ) {
		$network = get_site_option( 'active_sitewide_plugins', array() );
		if ( is_array( $network ) && isset( $network[ $legacy ] ) ) {
			$network[ $main ] = $network[ $legacy ];
			unset( $network[ $legacy ] );
			update_site_option( 'active_sitewide_plugins', $network );
		}
	}
}

if ( ! defined( 'SWFL_VERSION' ) ) {
	swfl_redirect_legacy_activation();
	require_once __DIR__ . '/swiss-floorball-api.php';
}
