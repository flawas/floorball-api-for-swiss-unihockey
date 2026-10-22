<?php
/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://flaviowaser.ch
 * @since             1.0.0
 * @package           SWFL
 *
 * @wordpress-plugin
 * Plugin Name:       Swiss Floorball API
 * Plugin URI:        https://flaviowaser.ch
 * Description:       Shows the current data of the Swiss Floorball API (games, rankings, topscorers, players and calendars) on your website.
 * Version:           2.2.0
 * Requires PHP:      8.2
 * Author:            Flavio Waser
 * Author URI:        https://flaviowaser.ch/
 * License:           GPLv2 or later
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       swiss-floorball-api
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.1 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'SWFL_VERSION', '2.2.0' );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-swfl-activator.php
 */
function swfl_activate() {
	SWFL_Php_Requirement::refuse_activation();
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-swfl-activator.php';
	SWFL_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-swfl-deactivator.php
 */
function swfl_deactivate() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-swfl-deactivator.php';
	SWFL_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'swfl_activate' );
register_deactivation_hook( __FILE__, 'swfl_deactivate' );

/*
 * The plugin needs PHP 8.2.35, 8.3.36, 8.4.27, 8.5.12 or newer (see SWFL_Php_Requirement). On an older version
 * it loads nothing and tells administrators which version they need.
 */
require_once plugin_dir_path( __FILE__ ) . 'includes/class-swfl-php-requirement.php';
if ( ! SWFL_Php_Requirement::is_met() ) {
	add_action( 'admin_notices', array( 'SWFL_Php_Requirement', 'render_notice' ) );
	return;
}

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require_once plugin_dir_path( __FILE__ ) . 'includes/class-swfl-plugin.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function swfl_run() {
	$plugin = new SWFL_Plugin();
	$plugin->run();
}
swfl_run();
