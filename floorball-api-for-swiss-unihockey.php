<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

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
 * @package           Swiss_Floorball_Api
 *
 * @wordpress-plugin
 * Plugin Name:       Swiss Floorball API
 * Plugin URI:        https://flaviowaser.ch
 * Description:       Ein kleines Plugin, welches ermöglicht, die aktuellen Daten der Swiss Floorball API abzufragen und auf der Webseite darzustellen.
 * Version:           1.0.4
 * Author:            Flavio Waser
 * Author URI:        https://flaviowaser.ch/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       floorball-api-for-swiss-unihockey
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.1 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'SWISS_FLOORBALL_API_VERSION', '1.0.4' );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-floorball-api-for-swiss-unihockey-activator.php
 */
function activateSwissFloorballApi() {
    require_once plugin_dir_path( __FILE__ ) . 'includes/class-floorball-api-for-swiss-unihockey-activator.php';
    Swiss_Floorball_Api_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-floorball-api-for-swiss-unihockey-deactivator.php
 */
function deactivateSwissFloorballApi() {
    require_once plugin_dir_path( __FILE__ ) . 'includes/class-floorball-api-for-swiss-unihockey-deactivator.php';
    Swiss_Floorball_Api_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activateSwissFloorballApi' );
register_deactivation_hook( __FILE__, 'deactivateSwissFloorballApi' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require_once plugin_dir_path( __FILE__ ) . 'includes/class-floorball-api-for-swiss-unihockey.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function runSwissFloorballApi() {
    $plugin = new Swiss_Floorball_Api();
    $plugin->run();
}
runSwissFloorballApi();
