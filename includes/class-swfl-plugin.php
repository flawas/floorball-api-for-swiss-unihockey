<?php
/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://flaviowaser.ch
 * @since      1.0.0
 *
 * @package    SWFL
 * @subpackage SWFL_Plugin/includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    SWFL
 * @subpackage SWFL_Plugin/includes
 * @author     Flavio Waser <kontakt@flawas.ch>
 */
class SWFL_Plugin {

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      SWFL_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		if ( defined( 'SWFL_VERSION' ) ) {
			$this->version = SWFL_VERSION;
		} else {
			$this->version = '1.0.0';
		}
		$this->plugin_name = 'swiss-floorball-api';

		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();
	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - SWFL_Loader. Orchestrates the hooks of the plugin.
	 * - SWFL_I18n. Defines internationalization functionality.
	 * - SWFL_Admin. Defines all hooks for the admin area.
	 * - SWFL_Public. Defines all hooks for the public side of the site.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies() {

		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		require_once plugin_dir_path( __DIR__ ) . 'includes/class-swfl-loader.php';

		/**
		 * The class responsible for defining internationalization functionality
		 * of the plugin.
		 */
		require_once plugin_dir_path( __DIR__ ) . 'includes/class-swfl-i18n.php';
		require_once plugin_dir_path( __DIR__ ) . 'includes/class-swfl-migrator.php';

		/**
		 * The class responsible for defining all actions that occur in the admin area.
		 */
		require_once plugin_dir_path( __DIR__ ) . 'admin/class-swfl-admin.php';
		require_once plugin_dir_path( __DIR__ ) . 'admin/class-swfl-admin-settings.php';

		/**
		 * The class responsible for defining all actions that occur in the public-facing
		 * side of the site.
		 */
		require_once plugin_dir_path( __DIR__ ) . 'public/class-swfl-public.php';
		require_once plugin_dir_path( __DIR__ ) . 'public/class-swfl-public-assets.php';

		require_once plugin_dir_path( __DIR__ ) . 'includes/class-swfl-client.php';
		require_once plugin_dir_path( __DIR__ ) . 'includes/class-swfl-display.php';
		require_once plugin_dir_path( __DIR__ ) . 'includes/class-swfl-theme.php';
		require_once plugin_dir_path( __DIR__ ) . 'includes/class-swfl-display-stats.php';
		require_once plugin_dir_path( __DIR__ ) . 'includes/class-swfl-widgets.php';
		require_once plugin_dir_path( __DIR__ ) . 'includes/class-swfl-highlights.php';
		require_once plugin_dir_path( __DIR__ ) . 'includes/class-swfl-game-widget.php';
		require_once plugin_dir_path( __DIR__ ) . 'includes/class-swfl-calendar.php';
		require_once plugin_dir_path( __DIR__ ) . 'includes/class-swfl-rest.php';
		require_once plugin_dir_path( __DIR__ ) . 'includes/class-swfl-table-data.php';
		require_once plugin_dir_path( __DIR__ ) . 'includes/class-swfl-request-guard.php';
		require_once plugin_dir_path( __DIR__ ) . 'includes/class-swfl-counter.php';
		require_once plugin_dir_path( __DIR__ ) . 'includes/class-swfl-client-ip.php';
		require_once plugin_dir_path( __DIR__ ) . 'includes/class-swfl-cache-entry.php';
		require_once plugin_dir_path( __DIR__ ) . 'includes/class-swfl-upstream-budget.php';
		require_once plugin_dir_path( __DIR__ ) . 'includes/class-swfl-rate-limit-log.php';
		require_once plugin_dir_path( __DIR__ ) . 'includes/class-swfl-icons.php';
		require_once plugin_dir_path( __DIR__ ) . 'includes/class-swfl-warmup.php';

		$this->loader = new SWFL_Loader();
	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the SWFL_I18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function set_locale() {

		$plugin_i18n = new SWFL_I18n();

		// Early on init: WordPress wants no translations before it, and widgets_init (init, priority 1) needs them.
		$this->loader->add_action( 'init', $plugin_i18n, 'load_plugin_textdomain', 0 );
		// Runs first so no code reads the settings before the legacy options have been moved.
		$this->loader->add_action( 'plugins_loaded', 'SWFL_Migrator', 'maybe_migrate', 1 );
	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_admin_hooks() {

		$plugin_admin = new SWFL_Admin( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );
	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_public_hooks() {

		$plugin_public = new SWFL_Public( $this->get_plugin_name(), $this->get_version() );
		$plugin_public->register_shortcodes();
		$plugin_assets = new SWFL_Public_Assets( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_assets, 'enqueue_styles' );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_assets, 'enqueue_scripts' );
		$this->loader->add_action( 'rest_api_init', 'SWFL_Widgets', 'register_routes' );
		$this->loader->add_action( 'rest_api_init', 'SWFL_Rest', 'register_routes' );
		$this->loader->add_action( 'widgets_init', 'SWFL_Game_Widget', 'register' );
		// Retry-After for the 429 and 503 answers, and the end of the upstream budget state once a route has run.
		$this->loader->add_filter( 'rest_post_dispatch', 'SWFL_Request_Guard', 'add_retry_after' );
		$this->loader->add_filter( 'rest_request_after_callbacks', 'SWFL_Upstream_Budget', 'leave_public_request' );
		// Core runs the permission callback again for the Allow header (rest_post_dispatch, priority 10), also for requests that never reached their callback.
		$this->loader->add_filter( 'rest_post_dispatch', 'SWFL_Upstream_Budget', 'leave_public_request', 99 );

		// The warm-up is a WP-Cron job, so it is registered for every request and not only in the admin area.
		SWFL_Warmup::register_hooks( $this->loader );
	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     1.0.0
	 * @return    SWFL_Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}
}
