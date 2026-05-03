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
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;
		add_action('admin_menu', array( $this, 'addPluginAdminMenu' ), 9);   
		add_action('admin_init', array( $this, 'registerAndBuildFields' )); 
		add_action('admin_post_sfa_clear_cache', array( $this, 'handle_clear_cache' ));

	}
	
	

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {
		$page = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : '';
		if ( strpos( $page, $this->plugin_name ) === false ) {
			return;
		}
		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/swiss-floorball-api-admin.css', array(), $this->version, 'all' );
	}

	public function enqueue_scripts() {
		$page = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : '';
		if ( strpos( $page, $this->plugin_name ) === false ) {
			return;
		}
		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/swiss-floorball-api-admin.js', array( 'jquery' ), $this->version, false );
	}

	/**
	 * Register the sidebar for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function addPluginAdminMenu() {
		//add_menu_page( $page_title, $menu_title, $capability, $menu_slug, $function, $icon_url, $position );
		add_menu_page(  $this->plugin_name, 'Swiss Unihockey', 'administrator', $this->plugin_name, array( $this, 'displayPluginAdminDashboard' ), 'dashicons-database-import', 26 );
		
		//add_submenu_page( '$parent_slug, $page_title, $menu_title, $capability, $menu_slug, $function );
		add_submenu_page( $this->plugin_name, 'Swiss Unihockey', 'Einstellungen', 'administrator', $this->plugin_name.'-settings', array( $this, 'displayPluginAdminSettings' ));
		add_submenu_page( $this->plugin_name, 'Swiss Unihockey', 'Liga', 'administrator', $this->plugin_name.'-league', array( $this, 'displayPluginAdminHelperLeague' ));
		add_submenu_page( $this->plugin_name, 'Swiss Unihockey', 'Clubs', 'administrator', $this->plugin_name.'-teams', array( $this, 'displayPluginAdminHelperTeams' ));
		add_submenu_page( $this->plugin_name, 'Swiss Unihockey', 'Spiele', 'administrator', $this->plugin_name.'-matches', array( $this, 'displayPluginAdminMatches' ));
		add_submenu_page( $this->plugin_name, 'Swiss Unihockey', 'Saison', 'administrator', $this->plugin_name.'-seasons', array( $this, 'displayPluginAdminHelperSeasons' ));
		add_submenu_page( $this->plugin_name, 'Swiss Unihockey', 'Shortcodes', 'administrator', $this->plugin_name.'-shortcodes', array( $this, 'displayPluginAdminShortcodes' ));

	}

	/**
	 * Return admin display page
	 *
	 * @since    1.0.0
	 */
	public function displayPluginAdminDashboard() {
		require_once 'partials/'.$this->plugin_name.'-admin-display.php';
  	}

	/**
	 * Return admin display page settings
	 *
	 * @since    1.0.0
	 */
	public function displayPluginAdminSettings() {
		// set this var to be used in the settings-display view
		$active_tab = isset( $_GET[ 'tab' ] ) ? $_GET[ 'tab' ] : 'general';
		if(isset($_GET['error_message'])){
				add_action('admin_notices', array($this,'settingsPageSettingsMessages'));
				do_action( 'admin_notices', $_GET['error_message'] );
		}
		require_once 'partials/'.$this->plugin_name.'-admin-settings-display.php';
	}

	/**
	 * Return league helper display page
	 *
	 * @since    1.0.0
	 */
	public function displayPluginAdminHelperLeague() {
		// set this var to be used in the settings-display view
		$active_tab = isset( $_GET[ 'tab' ] ) ? $_GET[ 'tab' ] : 'general';
		if(isset($_GET['error_message'])){
				add_action('admin_notices', array($this,'settingsPageSettingsMessages'));
				do_action( 'admin_notices', $_GET['error_message'] );
		}
		require_once 'partials/'.$this->plugin_name.'-admin-helper-league-display.php';
	}

	/**
	 * Return season helper display page
	 *
	 * @since    1.0.0
	 */
	public function displayPluginAdminHelperSeasons() {
		// set this var to be used in the settings-display view
		$active_tab = isset( $_GET[ 'tab' ] ) ? $_GET[ 'tab' ] : 'general';
		if(isset($_GET['error_message'])){
				add_action('admin_notices', array($this,'settingsPageSettingsMessages'));
				do_action( 'admin_notices', $_GET['error_message'] );
		}
		require_once 'partials/'.$this->plugin_name.'-admin-helper-seasons-display.php';
	}

	/**
	 * Return teams helper display page
	 *
	 * @since    1.0.0
	 */
	public function displayPluginAdminHelperTeams() {
		// set this var to be used in the settings-display view
		$active_tab = isset( $_GET[ 'tab' ] ) ? $_GET[ 'tab' ] : 'general';
		if(isset($_GET['error_message'])){
				add_action('admin_notices', array($this,'settingsPageSettingsMessages'));
				do_action( 'admin_notices', $_GET['error_message'] );
		}
		require_once 'partials/'.$this->plugin_name.'-admin-helper-teams-display.php';
	}

	/**
	 * Return shortcodes display page
	 *
	 * @since    1.0.0
	 */
	public function displayPluginAdminShortcodes() {
		require_once 'partials/'.$this->plugin_name.'-admin-shortcodes-display.php';
	}

	/**
	 * Return matches display page
	 *
	 * @since    1.0.0
	 */
	public function displayPluginAdminMatches() {
		require_once 'partials/'.$this->plugin_name.'-admin-matches-display.php';
	}

	/**
	 * Return error while loading admin display page settings
	 *
	 * @since    1.0.0
	 */
	public function settingsPageSettingsMessages($error_message){
		switch ($error_message) {
				case '1':
						$message = __( 'There was an error adding this setting. Please try again.  If this persists, shoot us an email.', 'my-text-domain' );                 $err_code = esc_attr( 'swissfloorball_api_key' );                 $setting_field = 'swissfloorball_api_key';                 
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
 * @param    int    $club_number    The club number to sanitize
 * @return   int                    The sanitized club number
 */
public function sanitize_club_number( $club_number ) {
	// Sanitize the club number
	$club_number = absint( $club_number );
	
	// Get the old club number to check if it changed
	$old_club_number = get_option( 'swissfloorball_club_number' );
	
	// If club number changed, clear all cached API data
	if ( $old_club_number != $club_number && ! empty( $club_number ) ) {
		global $wpdb;
		$wpdb->query( $wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
			'_transient_sfa_%',
			'_transient_timeout_sfa_%'
		) );
	}
	
	// If club number is empty, return it as is
	if ( empty( $club_number ) ) {
		return $club_number;
	}
	
	// Fetch club details from API
	require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-swiss-floorball-api-client.php';
	$client = new Swiss_Floorball_API_Client();
	// Use short cache time (60 seconds) to ensure fresh data when club number changes
	// Note: The API doesn't have a /clubs/{id} endpoint, so we fetch all clubs and search
	$api_response = $client->fetch_data( 'clubs', array(), 60 );
	
	$club_name = '';
	
	// Check if we got a valid response
	if ( ! is_wp_error( $api_response ) && isset( $api_response['entries'] ) ) {
		// Search through all clubs to find the one with matching club_id
		foreach ( $api_response['entries'] as $entry ) {
			if ( isset( $entry['set_in_context']['club_id'] ) && $entry['set_in_context']['club_id'] == $club_number ) {
				$club_name = $entry['text'];
				break;
			}
		}
	}
	
	// Update the club name option
	if ( ! empty( $club_name ) ) {
		update_option( 'swissfloorball_club_name', sanitize_text_field( $club_name ) );
		// Add success notice
		add_settings_error(
			'swissfloorball_club_name',
			'club_name_updated',
			sprintf( __( 'Club name automatically set to: %s', 'swiss-floorball-api' ), $club_name ),
			'success'
		);
	} else {
		// If API call failed or no name found, clear the club name
		update_option( 'swissfloorball_club_name', '' );
		// Add error notice
		add_settings_error(
			'swissfloorball_club_name',
			'club_name_not_found',
			sprintf( __( 'Could not find club name for club ID: %s', 'swiss-floorball-api' ), $club_number ),
			'error'
		);
		// Log error for debugging
		error_log( 'Swiss Floorball API: Could not fetch club name for club ID ' . $club_number );
	}
	
	return $club_number;
}


/**
 * Handle cache clearing request
 *
 * @since    1.0.0
 */
public function handle_clear_cache() {
	// Check nonce for security
	if ( ! isset( $_POST['sfa_clear_cache_nonce'] ) || ! wp_verify_nonce( $_POST['sfa_clear_cache_nonce'], 'sfa_clear_cache_action' ) ) {
		wp_die( __( 'Security check failed', 'swiss-floorball-api' ) );
	}
	
	// Check user permissions
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( __( 'You do not have permission to perform this action', 'swiss-floorball-api' ) );
	}
	
	// Clear all cached API data
	global $wpdb;
	$deleted = $wpdb->query( $wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		'_transient_sfa_%',
		'_transient_timeout_sfa_%'
	) );
	
	// Redirect back to settings page with success message
	$redirect_url = add_query_arg(
		array(
			'page' => 'swiss-floorball-api-settings',
			'cache_cleared' => '1',
			'deleted_count' => $deleted
		),
		admin_url( 'admin.php' )
	);
	
	wp_redirect( $redirect_url );
	exit;
}

	/**
	 * Register and build fields
	 *
	 * @since    1.0.0
	 */
	public function registerAndBuildFields() {
		/**
		 * First, we add_settings_section. This is necessary since all future settings must belong to one.
		 * Second, add_settings_field
		 * Third, register_setting
		 */     
		add_settings_section(
			// ID used to identify this section and with which to register options
			'settings_page_general_section', 
			// Title to be displayed on the administration page
			'Einstellungen',  
			// Callback used to render the description of the section
				array( $this, 'settings_page_display_general_account' ),    
			// Page on which to add this section of options
			'settings_page_general_settings'                   
		);



		unset($args);
		$args = array (
							'type'      => 'input',
							'subtype'   => 'text',
							'id'    => 'swissfloorball_api_key',
							'name'      => 'swissfloorball_api_key',
							'required' => 'false',
							'get_options_list' => '',
							'value_type'=>'normal',
							'wp_data' => 'option'
					);
		add_settings_field(
			'swissfloorball_api_key',
			'Swiss Floorball API Key (Optional)',
			array( $this, 'settings_page_render_settings_field' ),
			'settings_page_general_settings',
			'settings_page_general_section',
			$args
		);

		register_setting(
			'settings_page_general_settings',
			'swissfloorball_api_key',
			'sanitize_text_field'
		);

		unset($args);
		$args = array (
							'type'      => 'input',
							'subtype'   => 'number',
							'id'    => 'swissfloorball_club_number',
							'name'      => 'swissfloorball_club_number',
							'required' => 'false',
							'get_options_list' => '',
							'value_type'=>'normal',
							'wp_data' => 'option'
					);
		add_settings_field(
			'swissfloorball_club_number',
			'Swiss Floorball Club Number',
			array( $this, 'settings_page_render_settings_field' ),
			'settings_page_general_settings',
			'settings_page_general_section',
			$args
		);

		register_setting(
			'settings_page_general_settings',
			'swissfloorball_club_number',
			array( $this, 'sanitize_club_number' )
		);

		unset($args);
		$args = array (
							'type'      => 'input',
							'subtype'   => 'text',
							'id'    => 'swissfloorball_club_name',
							'name'      => 'swissfloorball_club_name',
							'required' => 'false',
							'get_options_list' => '',
							'value_type'=>'normal',
							'wp_data' => 'option',
							'disabled' => true
					);
		add_settings_field(
			'swissfloorball_club_name',
			'Swiss Floorball Club Name',
			array( $this, 'settings_page_render_settings_field' ),
			'settings_page_general_settings',
			'settings_page_general_section',
			$args
		);

		unset($args);
		$args = array (
							'type'      => 'input',
							'subtype'   => 'number',
							'id'    => 'swissfloorball_actual_season',
							'name'      => 'swissfloorball_actual_season',
							'required' => 'false',
							'get_options_list' => '',
							'value_type'=>'normal',
							'wp_data' => 'option'
					);
		add_settings_field(
			'swissfloorball_actual_season',
			'Swiss Floorball Aktuelle Saison (Jahrzahl, z.B. 2023)',
			array( $this, 'settings_page_render_settings_field' ),
			'settings_page_general_settings',
			'settings_page_general_section',
			$args
		);

		register_setting(
			'settings_page_general_settings', 
			'swissfloorball_actual_season',
			'absint'
			);

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
	 */
	public function settings_page_render_settings_field($args) {
			/* EXAMPLE INPUT
								'type'      => 'input',
								'subtype'   => '',
								'id'    => $this->plugin_name.'_example_setting',
								'name'      => $this->plugin_name.'_example_setting',
								'required' => 'required="required"',
								'get_option_list' => "",
									'value_type' = serialized OR normal,
			'wp_data'=>(option or post_meta),
			'post_id' =>
			*/     
		if($args['wp_data'] == 'option'){
			$wp_data_value = get_option($args['name']);
		} elseif($args['wp_data'] == 'post_meta'){
			$wp_data_value = get_post_meta($args['post_id'], $args['name'], true );
		}

		switch ($args['type']) {

			case 'input':
					$value = ($args['value_type'] == 'serialized') ? serialize($wp_data_value) : $wp_data_value;
					if($args['subtype'] != 'checkbox'){
							$prependStart = (isset($args['prepend_value'])) ? '<div class="input-prepend"> <span class="add-on">'.$args['prepend_value'].'</span>' : '';
							$prependEnd = (isset($args['prepend_value'])) ? '</div>' : '';
							$step = (isset($args['step'])) ? 'step="'.$args['step'].'"' : '';
							$min = (isset($args['min'])) ? 'min="'.$args['min'].'"' : '';
							$max = (isset($args['max'])) ? 'max="'.$args['max'].'"' : '';
							if(isset($args['disabled'])){
									// hide the actual input bc if it was just a disabled input the info saved in the database would be wrong - bc it would pass empty values and wipe the actual information
									echo $prependStart.'<input type="'.$args['subtype'].'" id="'.$args['id'].'_disabled" '.$step.' '.$max.' '.$min.' name="'.$args['name'].'_disabled" size="40" disabled value="' . esc_attr($value) . '" /><input type="hidden" id="'.$args['id'].'" '.$step.' '.$max.' '.$min.' name="'.$args['name'].'" size="40" value="' . esc_attr($value) . '" />'.$prependEnd;
							} else {
									echo $prependStart.'<input type="'.$args['subtype'].'" id="'.$args['id'].'" "'.$args['required'].'" '.$step.' '.$max.' '.$min.' name="'.$args['name'].'" size="40" value="' . esc_attr($value) . '" />'.$prependEnd;
							}
							/*<input required="required" '.$disabled.' type="number" step="any" id="'.$this->plugin_name.'_cost2" name="'.$this->plugin_name.'_cost2" value="' . esc_attr( $cost ) . '" size="25" /><input type="hidden" id="'.$this->plugin_name.'_cost" step="any" name="'.$this->plugin_name.'_cost" value="' . esc_attr( $cost ) . '" />*/

					} else {
							$checked = ($value) ? 'checked' : '';
							echo '<input type="'.$args['subtype'].'" id="'.$args['id'].'" "'.$args['required'].'" name="'.$args['name'].'" size="40" value="1" '.$checked.' />';
					}
					break;
			default:
					# code...
					break;
		}
	}
}
