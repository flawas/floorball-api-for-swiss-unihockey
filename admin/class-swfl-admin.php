<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://flaviowaser.ch
 * @since      1.0.0
 *
 * @package    SWFL
 * @subpackage SWFL_Plugin/admin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    SWFL
 * @subpackage SWFL_Plugin/admin
 * @author     Flavio Waser <kontakt@flawas.ch>
 */
class SWFL_Admin {

	/**
	 * Page title of the plugin admin pages.
	 *
	 * @since 2.0.1
	 * @var   string
	 */
	const MENU_TITLE = 'Swiss Floorball';

	/**
	 * Directory of the admin page templates, relative to this file.
	 *
	 * @since 2.0.1
	 * @var   string
	 */
	const PARTIALS_DIR = 'partials/';

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Settings page registration.
	 *
	 * @since 2.0.1
	 * @var   SWFL_Admin_Settings
	 */
	private $settings;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string $plugin_name       The name of this plugin.
	 * @param      string $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version     = $version;
		add_action( 'admin_menu', array( $this, 'add_plugin_admin_menu' ), 9 );
		$this->settings = new SWFL_Admin_Settings();
		add_action( 'admin_init', array( $this->settings, 'register_and_build_fields' ) );
		add_action( 'admin_post_swfl_clear_cache', array( $this, 'handle_clear_cache' ) );
	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {
		// Read-only admin navigation parameter, no state change.
		$page = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( false === strpos( $page, $this->plugin_name ) ) {
			return;
		}
		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/swfl-admin.css', array(), $this->version, 'all' );
		$seed_css = SWFL_Theme::get_seed_css();
		if ( '' !== $seed_css ) {
			wp_add_inline_style( $this->plugin_name, $seed_css );
		}
		$table_css = SWFL_Theme::get_table_css();
		if ( '' !== $table_css ) {
			wp_add_inline_style( $this->plugin_name, $table_css );
		}
	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {
		// Read-only admin navigation parameter, no state change.
		$page = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( false === strpos( $page, $this->plugin_name ) ) {
			return;
		}
		wp_enqueue_style( 'wp-color-picker' );
		// The file modification time is the version so browsers never serve a stale admin script after an update.
		$script_path    = plugin_dir_path( __FILE__ ) . 'js/swfl-admin.js';
		$script_version = file_exists( $script_path ) ? (string) filemtime( $script_path ) : $this->version;
		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/swfl-admin.js', array( 'jquery', 'wp-color-picker' ), $script_version, false );
		wp_localize_script(
			$this->plugin_name,
			'swflAutosave',
			array(
				'saving' => __( 'Saving…', 'swiss-floorball-api' ),
				'saved'  => __( 'Saved.', 'swiss-floorball-api' ),
				'error'  => __( 'Saving failed. Please reload the page and try again.', 'swiss-floorball-api' ),
			)
		);
	}

	/**
	 * Register the sidebar for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function add_plugin_admin_menu() {
		// Material Symbols "storage" icon (inline, base64 SVG) instead of a Dashicon, for a consistent icon set across admin UI and menu.
		$menu_icon = 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAyNCAyNCIgd2lkdGg9IjIwIiBoZWlnaHQ9IjIwIiBmaWxsPSJibGFjayI+PHBhdGggZD0iTTIgMjBoMjB2LTRIMnY0em0yLTNoMnYySDR2LTJ6TTIgNHY0aDIwVjRIMnptNCAzSDRWNWgydjJ6bS00IDdoMjB2LTRIMnY0em0yLTNoMnYySDR2LTJ6Ii8+PC9zdmc+';

		// Top-level menu entry, registered with the page title, menu title, capability, slug, callback, icon and position.
		add_menu_page( $this->plugin_name, self::MENU_TITLE, 'manage_options', $this->plugin_name, array( $this, 'display_plugin_admin_dashboard' ), $menu_icon, 26 );

		// Submenu entries share the parent slug and use the same capability.
		add_submenu_page( $this->plugin_name, self::MENU_TITLE, 'Club-Übersicht', 'manage_options', $this->plugin_name . '-overview', array( $this, 'display_plugin_admin_overview' ) );
		add_submenu_page( $this->plugin_name, self::MENU_TITLE, 'Einstellungen', 'manage_options', $this->plugin_name . '-settings', array( $this, 'display_plugin_admin_settings' ) );
		add_submenu_page( $this->plugin_name, self::MENU_TITLE, 'Liga', 'manage_options', $this->plugin_name . '-league', array( $this, 'display_plugin_admin_helper_league' ) );
		add_submenu_page( $this->plugin_name, self::MENU_TITLE, 'Clubs', 'manage_options', $this->plugin_name . '-teams', array( $this, 'display_plugin_admin_helper_teams' ) );
		add_submenu_page( $this->plugin_name, self::MENU_TITLE, 'Spiele', 'manage_options', $this->plugin_name . '-matches', array( $this, 'display_plugin_admin_matches' ) );
		add_submenu_page( $this->plugin_name, self::MENU_TITLE, 'Saison', 'manage_options', $this->plugin_name . '-seasons', array( $this, 'display_plugin_admin_helper_seasons' ) );
		add_submenu_page( $this->plugin_name, self::MENU_TITLE, 'Shortcodes', 'manage_options', $this->plugin_name . '-shortcodes', array( $this, 'display_plugin_admin_shortcodes' ) );
	}

	/**
	 * Return admin display page
	 *
	 * @since    1.0.0
	 */
	public function display_plugin_admin_dashboard() {
		require_once self::PARTIALS_DIR . 'swfl-admin-display.php';
	}

	/**
	 * Return the club overview page (teams and games of the club).
	 *
	 * @since    1.0.6
	 */
	public function display_plugin_admin_overview() {
		require_once self::PARTIALS_DIR . 'swfl-admin-overview-display.php';
	}

	/**
	 * Return admin display page settings
	 *
	 * @since    1.0.0
	 */
	public function display_plugin_admin_settings() {
		// set this var to be used in the settings-display view
		// Read-only admin navigation parameters, no state change.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['error_message'] ) ) {
			add_action( 'admin_notices', array( $this, 'settings_page_settings_messages' ) );
			do_action(
				'admin_notices', // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core hook, invoked on purpose to render the notice.
				absint( $_GET['error_message'] )
			);
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		require_once self::PARTIALS_DIR . 'swfl-admin-settings-display.php';
	}

	/**
	 * Return league helper display page
	 *
	 * @since    1.0.0
	 */
	public function display_plugin_admin_helper_league() {
		// set this var to be used in the settings-display view
		// Read-only admin navigation parameters, no state change.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['error_message'] ) ) {
			add_action( 'admin_notices', array( $this, 'settings_page_settings_messages' ) );
			do_action(
				'admin_notices', // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core hook, invoked on purpose to render the notice.
				absint( $_GET['error_message'] )
			);
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		require_once self::PARTIALS_DIR . 'swfl-admin-helper-league-display.php';
	}

	/**
	 * Return season helper display page
	 *
	 * @since    1.0.0
	 */
	public function display_plugin_admin_helper_seasons() {
		// set this var to be used in the settings-display view
		// Read-only admin navigation parameters, no state change.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['error_message'] ) ) {
			add_action( 'admin_notices', array( $this, 'settings_page_settings_messages' ) );
			do_action(
				'admin_notices', // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core hook, invoked on purpose to render the notice.
				absint( $_GET['error_message'] )
			);
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		require_once self::PARTIALS_DIR . 'swfl-admin-helper-seasons-display.php';
	}

	/**
	 * Return teams helper display page
	 *
	 * @since    1.0.0
	 */
	public function display_plugin_admin_helper_teams() {
		// set this var to be used in the settings-display view
		// Read-only admin navigation parameters, no state change.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['error_message'] ) ) {
			add_action( 'admin_notices', array( $this, 'settings_page_settings_messages' ) );
			do_action(
				'admin_notices', // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core hook, invoked on purpose to render the notice.
				absint( $_GET['error_message'] )
			);
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		require_once self::PARTIALS_DIR . 'swfl-admin-helper-teams-display.php';
	}

	/**
	 * Return shortcodes display page
	 *
	 * @since    1.0.0
	 */
	public function display_plugin_admin_shortcodes() {
		require_once self::PARTIALS_DIR . 'swfl-admin-shortcodes-display.php';
	}

	/**
	 * Return matches display page
	 *
	 * @since    1.0.0
	 */
	public function display_plugin_admin_matches() {
		require_once self::PARTIALS_DIR . 'swfl-admin-matches-display.php';
	}

	/**
	 * Show the error notice of a failed settings redirect.
	 *
	 * @since    1.0.0
	 */
	public function settings_page_settings_messages() {
		add_settings_error(
			'swfl_api_key',
			'swfl_api_key',
			__( 'There was an error adding this setting. Please try again.  If this persists, shoot us an email.', 'swiss-floorball-api' ),
			'error'
		);
	}

	/**
	 * Handle cache clearing request
	 *
	 * @since    1.0.0
	 */
	public function handle_clear_cache() {
		// Check nonce for security.
		if ( ! isset( $_POST['swfl_clear_cache_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['swfl_clear_cache_nonce'] ) ), 'swfl_clear_cache_action' ) ) {
			wp_die( esc_html__( 'Security check failed', 'swiss-floorball-api' ) );
		}

		// Check user permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action', 'swiss-floorball-api' ) );
		}

		// Clear all cached API data.
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Transient cleanup; query is already prepared.
		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				$wpdb->esc_like( '_transient_swfl_' ) . '%',
				$wpdb->esc_like( '_transient_timeout_swfl_' ) . '%'
			)
		);

		// Pass the result via a short-lived per-user transient instead of URL parameters.
		set_transient( 'swfl_cache_cleared_' . get_current_user_id(), (int) $deleted, 30 );

		// Redirect back to settings page.
		$redirect_url = add_query_arg(
			array(
				'page' => 'swiss-floorball-api-settings',
			),
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $redirect_url );
		exit;
	}
}
