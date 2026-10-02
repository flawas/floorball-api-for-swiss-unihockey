<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://flaviowaser.ch
 * @since      1.0.0
 *
 * @package    Swiss_Floorball_Api
 * @subpackage Swiss_Floorball_Api/admin
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
 * @package    Swiss_Floorball_Api
 * @subpackage Swiss_Floorball_Api/admin
 * @author     Flavio Waser <kontakt@flawas.ch>
 */
class Swiss_Floorball_Api_Admin {

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
		add_action( 'admin_init', array( $this, 'register_and_build_fields' ) );
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
		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/floorball-api-for-swiss-unihockey-admin.css', array(), $this->version, 'all' );
		$seed_css = Swiss_Floorball_API_Display::get_seed_css();
		if ( '' !== $seed_css ) {
			wp_add_inline_style( $this->plugin_name, $seed_css );
		}
		$table_css = Swiss_Floorball_API_Display::get_table_css();
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
		$script_path    = plugin_dir_path( __FILE__ ) . 'js/floorball-api-for-swiss-unihockey-admin.js';
		$script_version = file_exists( $script_path ) ? (string) filemtime( $script_path ) : $this->version;
		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/floorball-api-for-swiss-unihockey-admin.js', array( 'jquery', 'wp-color-picker' ), $script_version, false );
		wp_localize_script(
			$this->plugin_name,
			'sfaAutosave',
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
		add_menu_page( $this->plugin_name, 'Swiss Floorball', 'manage_options', $this->plugin_name, array( $this, 'display_plugin_admin_dashboard' ), $menu_icon, 26 );

		// Submenu entries share the parent slug and use the same capability.
		add_submenu_page( $this->plugin_name, 'Swiss Floorball', 'Club-Übersicht', 'manage_options', $this->plugin_name . '-overview', array( $this, 'display_plugin_admin_overview' ) );
		add_submenu_page( $this->plugin_name, 'Swiss Floorball', 'Einstellungen', 'manage_options', $this->plugin_name . '-settings', array( $this, 'display_plugin_admin_settings' ) );
		add_submenu_page( $this->plugin_name, 'Swiss Floorball', 'Liga', 'manage_options', $this->plugin_name . '-league', array( $this, 'display_plugin_admin_helper_league' ) );
		add_submenu_page( $this->plugin_name, 'Swiss Floorball', 'Clubs', 'manage_options', $this->plugin_name . '-teams', array( $this, 'display_plugin_admin_helper_teams' ) );
		add_submenu_page( $this->plugin_name, 'Swiss Floorball', 'Spiele', 'manage_options', $this->plugin_name . '-matches', array( $this, 'display_plugin_admin_matches' ) );
		add_submenu_page( $this->plugin_name, 'Swiss Floorball', 'Saison', 'manage_options', $this->plugin_name . '-seasons', array( $this, 'display_plugin_admin_helper_seasons' ) );
		add_submenu_page( $this->plugin_name, 'Swiss Floorball', 'Shortcodes', 'manage_options', $this->plugin_name . '-shortcodes', array( $this, 'display_plugin_admin_shortcodes' ) );
	}

	/**
	 * Return admin display page
	 *
	 * @since    1.0.0
	 */
	public function display_plugin_admin_dashboard() {
		require_once 'partials/' . $this->plugin_name . '-admin-display.php';
	}

	/**
	 * Return the club overview page (teams and games of the club).
	 *
	 * @since    1.0.6
	 */
	public function display_plugin_admin_overview() {
		require_once 'partials/' . $this->plugin_name . '-admin-overview-display.php';
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
		require_once 'partials/' . $this->plugin_name . '-admin-settings-display.php';
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
		require_once 'partials/' . $this->plugin_name . '-admin-helper-league-display.php';
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
		require_once 'partials/' . $this->plugin_name . '-admin-helper-seasons-display.php';
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
		require_once 'partials/' . $this->plugin_name . '-admin-helper-teams-display.php';
	}

	/**
	 * Return shortcodes display page
	 *
	 * @since    1.0.0
	 */
	public function display_plugin_admin_shortcodes() {
		require_once 'partials/' . $this->plugin_name . '-admin-shortcodes-display.php';
	}

	/**
	 * Return matches display page
	 *
	 * @since    1.0.0
	 */
	public function display_plugin_admin_matches() {
		require_once 'partials/' . $this->plugin_name . '-admin-matches-display.php';
	}

	/**
	 * Return error while loading admin display page settings
	 *
	 * @since    1.0.0
	 * @param    string $error_message    Error code passed through from the settings redirect.
	 */
	public function settings_page_settings_messages( $error_message ) {
		switch ( $error_message ) {
			case '1':
					$message   = __( 'There was an error adding this setting. Please try again.  If this persists, shoot us an email.', 'swiss-floorball-api' );
				$err_code      = esc_attr( 'swissfloorball_api_key' );
				$setting_field = 'swissfloorball_api_key';
				break;
		}
		$type = 'error';
		add_settings_error(
			$setting_field,
			$err_code,
			$message,
			$type
		);
	}

	/**
	 * Sanitize club number and auto-fetch club name
	 *
	 * @since    1.0.0
	 * @param    int $club_number    The club number to sanitize.
	 * @return   int                    The sanitized club number
	 */
	public function sanitize_club_number( $club_number ) {
		// Sanitize the club number.
		$club_number = absint( $club_number );

		// Get the old club number to check if it changed.
		$old_club_number = get_option( 'swissfloorball_club_number' );
		$club_changed    = absint( $old_club_number ) !== $club_number;

		// If club number changed, clear all cached API data.
		if ( $club_changed && ! empty( $club_number ) ) {
			global $wpdb;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Transient cleanup; query is already prepared.
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
					$wpdb->esc_like( '_transient_swfl_' ) . '%',
					$wpdb->esc_like( '_transient_timeout_swfl_' ) . '%'
				)
			);
		}

		// If club number is empty, return it as is.
		if ( empty( $club_number ) ) {
			return $club_number;
		}

		// The settings form saves itself on every change, so this runs for unrelated fields too. Only look the name up when the club changed or no name is stored yet.
		if ( ! $club_changed && '' !== (string) get_option( 'swissfloorball_club_name', '' ) ) {
			return $club_number;
		}

		// Fetch club details from API.
		require_once plugin_dir_path( __DIR__ ) . 'includes/class-floorball-api-for-swiss-unihockey-client.php';
		$client = new Swiss_Floorball_API_Client();
		// Use short cache time (60 seconds) to ensure fresh data when club number changes
		// Note: The API doesn't have a /clubs/{id} endpoint, so we fetch all clubs and search.
		$api_response = $client->fetch_data( 'clubs', array(), 60 );

		$club_name = '';

		// Check if we got a valid response.
		if ( ! is_wp_error( $api_response ) && isset( $api_response['entries'] ) ) {
			// Search through all clubs to find the one with matching club_id.
			foreach ( $api_response['entries'] as $entry ) {
				if ( isset( $entry['set_in_context']['club_id'] ) && absint( $entry['set_in_context']['club_id'] ) === $club_number ) {
					$club_name = $entry['text'];
					break;
				}
			}
		}

		// Update the club name option.
		if ( ! empty( $club_name ) ) {
			update_option( 'swissfloorball_club_name', sanitize_text_field( $club_name ) );
			// Add success notice.
			add_settings_error(
				'swissfloorball_club_name',
				'club_name_updated',
				/* translators: %s: club name returned by the Swiss Unihockey API. */
				sprintf( __( 'Club name automatically set to: %s', 'swiss-floorball-api' ), $club_name ),
				'success'
			);
		} else {
			// If API call failed or no name found, clear the club name: it belongs to the previous club (or is already empty).
			update_option( 'swissfloorball_club_name', '' );
			// Add error notice.
			add_settings_error(
				'swissfloorball_club_name',
				'club_name_not_found',
				/* translators: %s: club ID that was entered in the settings. */
				sprintf( __( 'Could not find club name for club ID: %s', 'swiss-floorball-api' ), $club_number ),
				'error'
			);
		}

		return $club_number;
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
				'page' => 'floorball-api-for-swiss-unihockey-settings',
			),
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Register and build fields
	 *
	 * @since    1.0.0
	 */
	public function register_and_build_fields() {
		/**
		 * First, we add_settings_section. This is necessary since all future settings must belong to one.
		 * Second, add_settings_field
		 * Third, register_setting
		 */
		add_settings_section(
			// ID used to identify this section and with which to register options.
			'swfl_general_section',
			// Title to be displayed on the administration page.
			'Einstellungen',
			// Callback used to render the description of the section.
				array( $this, 'settings_page_display_general_account' ),
			// Page on which to add this section of options.
			'swfl_general_settings'
		);

		unset( $args );
		$args = array(
			'type'        => 'select',
			'id'          => 'swissfloorball_api_source',
			'name'        => 'swissfloorball_api_source',
			'wp_data'     => 'option',
			'default'     => Swiss_Floorball_API_Client::SOURCE_FREE,
			'options'     => array(
				Swiss_Floorball_API_Client::SOURCE_FREE    => __( 'Free API (wc.swissunihockey.ch)', 'swiss-floorball-api' ),
				Swiss_Floorball_API_Client::SOURCE_PARTNER => __( 'Partner API (API key and secret required)', 'swiss-floorball-api' ),
			),
			'description' => __( 'The free API needs no credentials but does not provide leagues, groups, topscorers, player profiles, national players and game events. Those shortcodes need the Partner API; the API key and secret fields appear once you select it.', 'swiss-floorball-api' ),
		);
		add_settings_field(
			'swissfloorball_api_source',
			__( 'API source', 'swiss-floorball-api' ),
			array( $this, 'settings_page_render_settings_field' ),
			'swfl_general_settings',
			'swfl_general_section',
			$args
		);

		register_setting(
			'swfl_general_settings',
			'swissfloorball_api_source',
			array( $this, 'sanitize_api_source' )
		);

		unset( $args );
		$args = array(
			'type'             => 'input',
			'subtype'          => 'text',
			'id'               => 'swissfloorball_api_key',
			'name'             => 'swissfloorball_api_key',
			'required'         => '',
			'get_options_list' => '',
			'value_type'       => 'normal',
			'wp_data'          => 'option',
			'description'      => __( 'The API key issued to you by Swiss Unihockey for the Partner API. Used together with the API secret to request a short-lived access token.', 'swiss-floorball-api' ),
		);
		add_settings_field(
			'swissfloorball_api_key',
			__( 'Partner API: API key', 'swiss-floorball-api' ),
			array( $this, 'settings_page_render_settings_field' ),
			'swfl_general_settings',
			'swfl_general_section',
			$args
		);

		register_setting(
			'swfl_general_settings',
			'swissfloorball_api_key',
			array( $this, 'sanitize_partner_credential' )
		);

		unset( $args );
		$args = array(
			'type'             => 'input',
			'subtype'          => 'password',
			'id'               => 'swissfloorball_api_secret',
			'name'             => 'swissfloorball_api_secret',
			'required'         => '',
			'get_options_list' => '',
			'value_type'       => 'normal',
			'wp_data'          => 'option',
			'description'      => __( 'The API secret that belongs to the API key above. Both are required; it is stored in the database and never shown on the website.', 'swiss-floorball-api' ),
		);
		add_settings_field(
			'swissfloorball_api_secret',
			__( 'Partner API: API secret', 'swiss-floorball-api' ),
			array( $this, 'settings_page_render_settings_field' ),
			'swfl_general_settings',
			'swfl_general_section',
			$args
		);

		register_setting(
			'swfl_general_settings',
			'swissfloorball_api_secret',
			array( $this, 'sanitize_partner_credential' )
		);

		unset( $args );
		$args = array(
			'type'             => 'input',
			'subtype'          => 'number',
			'id'               => 'swissfloorball_club_number',
			'name'             => 'swissfloorball_club_number',
			'required'         => 'false',
			'get_options_list' => '',
			'value_type'       => 'normal',
			'wp_data'          => 'option',
		);
		add_settings_field(
			'swissfloorball_club_number',
			'Swiss Floorball Club Number',
			array( $this, 'settings_page_render_settings_field' ),
			'swfl_general_settings',
			'swfl_general_section',
			$args
		);

		register_setting(
			'swfl_general_settings',
			'swissfloorball_club_number',
			array( $this, 'sanitize_club_number' )
		);

		unset( $args );
		$args = array(
			'type'             => 'input',
			'subtype'          => 'text',
			'id'               => 'swissfloorball_club_name',
			'name'             => 'swissfloorball_club_name',
			'required'         => 'false',
			'get_options_list' => '',
			'value_type'       => 'normal',
			'wp_data'          => 'option',
			'disabled'         => true,
		);
		add_settings_field(
			'swissfloorball_club_name',
			'Swiss Floorball Club Name',
			array( $this, 'settings_page_render_settings_field' ),
			'swfl_general_settings',
			'swfl_general_section',
			$args
		);

		unset( $args );
		$args = array(
			'type'             => 'input',
			'subtype'          => 'number',
			'id'               => 'swissfloorball_actual_season',
			'name'             => 'swissfloorball_actual_season',
			'required'         => 'false',
			'get_options_list' => '',
			'value_type'       => 'normal',
			'wp_data'          => 'option',
		);
		add_settings_field(
			'swissfloorball_actual_season',
			'Swiss Floorball Aktuelle Saison (Jahrzahl, z.B. 2023)',
			array( $this, 'settings_page_render_settings_field' ),
			'swfl_general_settings',
			'swfl_general_section',
			$args
		);

		register_setting(
			'swfl_general_settings',
			'swissfloorball_actual_season',
			'absint'
		);

		unset( $args );
		$args = array(
			'type'             => 'input',
			'subtype'          => 'checkbox',
			'id'               => 'swissfloorball_show_icons',
			'name'             => 'swissfloorball_show_icons',
			'required'         => '',
			'get_options_list' => '',
			'value_type'       => 'normal',
			'wp_data'          => 'option',
			'default'          => '1',
		);
		add_settings_field(
			'swissfloorball_show_icons',
			__( 'Show icons', 'swiss-floorball-api' ),
			array( $this, 'settings_page_render_settings_field' ),
			'swfl_general_settings',
			'swfl_general_section',
			$args
		);

		register_setting(
			'swfl_general_settings',
			'swissfloorball_show_icons',
			array( $this, 'sanitize_show_icons' )
		);

		unset( $args );
		$args = array(
			'type'        => 'select',
			'id'          => 'swissfloorball_theme',
			'name'        => 'swissfloorball_theme',
			'wp_data'     => 'option',
			'default'     => 'auto',
			'options'     => array(
				'auto'  => __( 'Auto (follow system)', 'swiss-floorball-api' ),
				'light' => __( 'Light', 'swiss-floorball-api' ),
				'dark'  => __( 'Dark', 'swiss-floorball-api' ),
			),
			'description' => __( 'Controls the colour scheme of shortcodes and admin pages. Auto follows the visitor\'s system setting.', 'swiss-floorball-api' ),
		);
		add_settings_field(
			'swissfloorball_theme',
			__( 'Theme', 'swiss-floorball-api' ),
			array( $this, 'settings_page_render_settings_field' ),
			'swfl_general_settings',
			'swfl_general_section',
			$args
		);

		register_setting(
			'swfl_general_settings',
			'swissfloorball_theme',
			array( $this, 'sanitize_theme' )
		);

		unset( $args );
		$args = array(
			'type'             => 'input',
			'subtype'          => 'text',
			'id'               => 'swissfloorball_seed_color',
			'name'             => 'swissfloorball_seed_color',
			'required'         => '',
			'get_options_list' => '',
			'value_type'       => 'normal',
			'wp_data'          => 'option',
			'default'          => '',
			'placeholder'      => '#0066cc',
			'description'      => __( 'Optional brand colour (hex, e.g. #0066cc). The plugin derives its colour palette from it. Leave empty to use the default colours.', 'swiss-floorball-api' ),
		);
		add_settings_field(
			'swissfloorball_seed_color',
			__( 'Seed colour', 'swiss-floorball-api' ),
			array( $this, 'settings_page_render_settings_field' ),
			'swfl_general_settings',
			'swfl_general_section',
			$args
		);

		register_setting(
			'swfl_general_settings',
			'swissfloorball_seed_color',
			array( $this, 'sanitize_seed_color' )
		);

		unset( $args );
		$args = array(
			'type'        => 'select',
			'id'          => 'swissfloorball_table_style',
			'name'        => 'swissfloorball_table_style',
			'wp_data'     => 'option',
			'default'     => 'flat',
			'options'     => array(
				'flat'    => __( 'Flat', 'swiss-floorball-api' ),
				'classic' => __( 'Classic (boxed, rounded)', 'swiss-floorball-api' ),
			),
			'description' => __( 'Flat tables have no border or shadow, thin row dividers and an accent line under the header.', 'swiss-floorball-api' ),
		);
		add_settings_field(
			'swissfloorball_table_style',
			__( 'Table style', 'swiss-floorball-api' ),
			array( $this, 'settings_page_render_settings_field' ),
			'swfl_general_settings',
			'swfl_general_section',
			$args
		);

		register_setting(
			'swfl_general_settings',
			'swissfloorball_table_style',
			array( $this, 'sanitize_table_style' )
		);

		$table_colors = array(
			'accent'    => array(
				__( 'Table accent colour', 'swiss-floorball-api' ),
				__( 'Line under the table header. Empty uses the seed or primary colour.', 'swiss-floorball-api' ),
			),
			'header'    => array(
				__( 'Table header text colour', 'swiss-floorball-api' ),
				__( 'Empty uses the normal text colour.', 'swiss-floorball-api' ),
			),
			'divider'   => array(
				__( 'Table row divider colour', 'swiss-floorball-api' ),
				__( 'Thin line between rows. Empty uses the default outline colour.', 'swiss-floorball-api' ),
			),
			'highlight' => array(
				__( 'Table row highlight colour', 'swiss-floorball-api' ),
				__( 'Background of the hovered row. Empty is derived from the accent colour.', 'swiss-floorball-api' ),
			),
		);
		foreach ( $table_colors as $color_key => $color_labels ) {
			$option_name = 'swissfloorball_table_' . $color_key . '_color';
			unset( $args );
			$args = array(
				'type'             => 'input',
				'subtype'          => 'text',
				'id'               => $option_name,
				'name'             => $option_name,
				'required'         => '',
				'get_options_list' => '',
				'value_type'       => 'normal',
				'wp_data'          => 'option',
				'default'          => '',
				'description'      => $color_labels[1],
			);
			add_settings_field(
				$option_name,
				$color_labels[0],
				array( $this, 'settings_page_render_settings_field' ),
				'swfl_general_settings',
				'swfl_general_section',
				$args
			);

			register_setting(
				'swfl_general_settings',
				$option_name,
				array( $this, 'sanitize_seed_color' )
			);
		}

		unset( $args );
		$args = array(
			'type'             => 'input',
			'subtype'          => 'checkbox',
			'id'               => 'swissfloorball_table_striped',
			'name'             => 'swissfloorball_table_striped',
			'required'         => '',
			'get_options_list' => '',
			'value_type'       => 'normal',
			'wp_data'          => 'option',
			'default'          => '0',
		);
		add_settings_field(
			'swissfloorball_table_striped',
			__( 'Striped table rows', 'swiss-floorball-api' ),
			array( $this, 'settings_page_render_settings_field' ),
			'swfl_general_settings',
			'swfl_general_section',
			$args
		);

		register_setting(
			'swfl_general_settings',
			'swissfloorball_table_striped',
			array( $this, 'sanitize_show_icons' )
		);

		unset( $args );
		$args = array(
			'type'             => 'input',
			'subtype'          => 'number',
			'id'               => 'swissfloorball_request_timeout',
			'name'             => 'swissfloorball_request_timeout',
			'required'         => '',
			'get_options_list' => '',
			'value_type'       => 'normal',
			'wp_data'          => 'option',
			'min'              => '1',
			'max'              => '30',
			'step'             => '1',
			'default'          => '3',
			'description'      => __( 'Maximum wait time for the Swiss Unihockey API in seconds (1-30, default 3). Can be overridden with the swfl_request_timeout filter.', 'swiss-floorball-api' ),
		);
		add_settings_field(
			'swissfloorball_request_timeout',
			__( 'API request timeout (seconds)', 'swiss-floorball-api' ),
			array( $this, 'settings_page_render_settings_field' ),
			'swfl_general_settings',
			'swfl_general_section',
			$args
		);

		register_setting(
			'swfl_general_settings',
			'swissfloorball_request_timeout',
			array(
				'type'              => 'integer',
				'sanitize_callback' => array( $this, 'sanitize_request_timeout' ),
				'default'           => 3,
			)
		);
	}

	/**
	 * Sanitize the API request timeout: integer seconds limited to 1-30, invalid values fall back to 3.
	 *
	 * @since 1.0.7
	 *
	 * @param mixed $value Raw submitted value.
	 * @return int Timeout in seconds.
	 */
	public function sanitize_request_timeout( $value ) {
		$value = absint( $value );
		if ( 0 === $value ) {
			return 3;
		}
		return min( 30, $value );
	}

	/**
	 * Sanitize the "show icons" checkbox: checked => '1', unchecked/missing => '0'.
	 *
	 * @param mixed $value Raw submitted value.
	 * @return string '1' or '0'.
	 */
	public function sanitize_show_icons( $value ) {
		return empty( $value ) ? '0' : '1';
	}

	/**
	 * Sanitize the theme setting against its whitelist.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value Raw submitted value.
	 * @return string 'auto', 'light' or 'dark'.
	 */
	public function sanitize_theme( $value ) {
		return in_array( $value, array( 'auto', 'light', 'dark' ), true ) ? $value : 'auto';
	}

	/**
	 * Sanitize the API source: one of the known sources, otherwise the free API.
	 *
	 * A changed source invalidates the cached partner token.
	 *
	 * @since 1.1.0
	 *
	 * @param mixed $value Raw submitted value.
	 * @return string Source slug.
	 */
	public function sanitize_api_source( $value ) {
		delete_transient( Swiss_Floorball_API_Client::TOKEN_TRANSIENT );
		$sources = array(
			Swiss_Floorball_API_Client::SOURCE_FREE,
			Swiss_Floorball_API_Client::SOURCE_PARTNER,
		);
		return in_array( $value, $sources, true ) ? $value : Swiss_Floorball_API_Client::SOURCE_FREE;
	}

	/**
	 * Sanitize a Partner API credential and drop the cached token so new credentials are used.
	 *
	 * @since 1.1.0
	 *
	 * @param mixed $value Raw submitted value.
	 * @return string Sanitized credential.
	 */
	public function sanitize_partner_credential( $value ) {
		delete_transient( Swiss_Floorball_API_Client::TOKEN_TRANSIENT );
		return sanitize_text_field( $value );
	}

	/**
	 * Sanitize the table style against its whitelist.
	 *
	 * @since 1.0.6
	 *
	 * @param mixed $value Raw submitted value.
	 * @return string 'flat' or 'classic'.
	 */
	public function sanitize_table_style( $value ) {
		return in_array( $value, array( 'flat', 'classic' ), true ) ? $value : 'flat';
	}

	/**
	 * Sanitize the seed colour: a valid hex colour or an empty string.
	 *
	 * @since 1.0.6
	 *
	 * @param mixed $value Raw submitted value.
	 * @return string Sanitized hex colour, or '' to use the default colours.
	 */
	public function sanitize_seed_color( $value ) {
		$color = sanitize_hex_color( trim( (string) $value ) );
		return empty( $color ) ? '' : $color;
	}

	/**
	 * Return admin display header slug
	 *
	 * @since    1.0.0
	 */
	public function settings_page_display_general_account() {
		echo '<p>Damit die Funktionalität des Plugins gewährleistet werden kann, müssen folgende Informationen ausgefüllt werden:</p>';
	}

	/**
	 * Render page and settings fields
	 *
	 * @since    1.0.0
	 * @param    array $args    Field definition (type, subtype, id, name, wp_data, value_type and optional extras).
	 */
	public function settings_page_render_settings_field( $args ) {
		// Expected $args keys: type, subtype, id, name, required, get_option_list, value_type (serialized or normal), wp_data (option or post_meta) and post_id.
		if ( 'option' === $args['wp_data'] ) {
			$wp_data_value = get_option( $args['name'], isset( $args['default'] ) ? $args['default'] : false );
		} elseif ( 'post_meta' === $args['wp_data'] ) {
			$wp_data_value = get_post_meta( $args['post_id'], $args['name'], true );
		}

		switch ( $args['type'] ) {

			case 'input':
					// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- Display only; never unserialized.
					$value = ( 'serialized' === $args['value_type'] ) ? serialize( $wp_data_value ) : $wp_data_value;
				if ( 'checkbox' !== $args['subtype'] ) {
						$prepend_start = ( isset( $args['prepend_value'] ) ) ? '<div class="input-prepend"> <span class="add-on">' . esc_html( $args['prepend_value'] ) . '</span>' : '';
						$prepend_end   = ( isset( $args['prepend_value'] ) ) ? '</div>' : '';
						$step          = ( isset( $args['step'] ) ) ? 'step="' . esc_attr( $args['step'] ) . '"' : '';
						$min           = ( isset( $args['min'] ) ) ? 'min="' . esc_attr( $args['min'] ) . '"' : '';
						$max           = ( isset( $args['max'] ) ) ? 'max="' . esc_attr( $args['max'] ) . '"' : '';
						$placeholder   = ( isset( $args['placeholder'] ) ) ? 'placeholder="' . esc_attr( $args['placeholder'] ) . '"' : '';
					if ( isset( $args['disabled'] ) ) {
						// hide the actual input bc if it was just a disabled input the info saved in the database would be wrong - bc it would pass empty values and wipe the actual information.
						echo $prepend_start . '<input type="' . esc_attr( $args['subtype'] ) . '" id="' . esc_attr( $args['id'] ) . '_disabled" ' . $step . ' ' . $max . ' ' . $min . ' name="' . esc_attr( $args['name'] ) . '_disabled" size="40" disabled value="' . esc_attr( $value ) . '" /><input type="hidden" id="' . esc_attr( $args['id'] ) . '" ' . $step . ' ' . $max . ' ' . $min . ' name="' . esc_attr( $args['name'] ) . '" size="40" value="' . esc_attr( $value ) . '" />' . $prepend_end; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					} else {
						echo $prepend_start . '<input type="' . esc_attr( $args['subtype'] ) . '" id="' . esc_attr( $args['id'] ) . '" ' . esc_attr( $args['required'] ) . ' ' . $step . ' ' . $max . ' ' . $min . ' ' . $placeholder . ' name="' . esc_attr( $args['name'] ) . '" size="40" value="' . esc_attr( $value ) . '" />' . $prepend_end; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					}
				} else {
						$checked = ( $value ) ? 'checked' : '';
						echo '<input type="' . esc_attr( $args['subtype'] ) . '" id="' . esc_attr( $args['id'] ) . '" ' . esc_attr( $args['required'] ) . ' name="' . esc_attr( $args['name'] ) . '" size="40" value="1" ' . esc_attr( $checked ) . ' />';
				}
				if ( ! empty( $args['description'] ) ) {
					echo '<p class="description">' . esc_html( $args['description'] ) . '</p>';
				}
				break;
			case 'select':
				echo '<select id="' . esc_attr( $args['id'] ) . '" name="' . esc_attr( $args['name'] ) . '">';
				foreach ( $args['options'] as $option_value => $option_label ) {
					echo '<option value="' . esc_attr( $option_value ) . '"' . selected( $wp_data_value, $option_value, false ) . '>' . esc_html( $option_label ) . '</option>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
				echo '</select>';
				if ( ! empty( $args['description'] ) ) {
					echo '<p class="description">' . esc_html( $args['description'] ) . '</p>';
				}
				break;
			default:
					// code...
				break;
		}
	}
}
