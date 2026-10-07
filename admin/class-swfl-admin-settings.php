<?php
/**
 * Settings page fields, sanitizers and field output.
 *
 * @link       https://flaviowaser.ch
 * @since      2.0.1
 *
 * @package    SWFL
 * @subpackage SWFL_Plugin/admin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the plugin settings with the WordPress Settings API.
 *
 * Split out of SWFL_Admin, which keeps menus, assets and page callbacks.
 *
 * @since      2.0.1
 * @package    SWFL
 * @subpackage SWFL_Plugin/admin
 * @author     Flavio Waser <kontakt@flawas.ch>
 */
class SWFL_Admin_Settings {

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

		$fields = array_merge(
			$this->get_connection_fields(),
			$this->get_appearance_fields(),
			$this->get_table_fields(),
			$this->get_request_fields()
		);
		foreach ( $fields as $field ) {
			add_settings_field(
				$field['args']['id'],
				$field['label'],
				array( $this, 'settings_page_render_settings_field' ),
				'swfl_general_settings',
				'swfl_general_section',
				$field['args']
			);

			// Fields without a sanitizer (the read-only club name) are not registered as options.
			if ( isset( $field['sanitize'] ) ) {
				$setting_args = $field['sanitize'];
				// A bare callback is wrapped so every setting is registered with an explicit sanitize_callback.
				if ( ! isset( $setting_args['sanitize_callback'] ) ) {
					$setting_args = array(
						'type'              => 'string',
						'sanitize_callback' => $setting_args,
					);
				}
				register_setting( 'swfl_general_settings', $field['args']['id'], $setting_args );
			}
		}
	}

	/**
	 * Build the definition of an input settings field.
	 *
	 * @since 2.0.1
	 * @param string $id      Option name, also used as field ID and name.
	 * @param string $subtype Input type (text, number, password, checkbox).
	 * @param array  $extra   Optional. Further field arguments (default, description, min, max, step, placeholder, required, disabled).
	 * @return array Field arguments for settings_page_render_settings_field().
	 */
	private function input_args( $id, $subtype, $extra = array() ) {
		return array_merge(
			array(
				'type'             => 'input',
				'subtype'          => $subtype,
				'id'               => $id,
				'name'             => $id,
				'required'         => '',
				'get_options_list' => '',
				'value_type'       => 'normal',
				'wp_data'          => 'option',
			),
			$extra
		);
	}

	/**
	 * Build the definition of a select settings field.
	 *
	 * @since 2.0.1
	 * @param string $id          Option name, also used as field ID and name.
	 * @param array  $options     Value => label pairs.
	 * @param string $default_value     Default value.
	 * @param string $description Help text below the field.
	 * @return array Field arguments for settings_page_render_settings_field().
	 */
	private function select_args( $id, $options, $default_value, $description ) {
		return array(
			'type'        => 'select',
			'id'          => $id,
			'name'        => $id,
			'wp_data'     => 'option',
			'default'     => $default_value,
			'options'     => $options,
			'description' => $description,
		);
	}

	/**
	 * Fields for the API source, the Partner API credentials, the club and the season.
	 *
	 * @since 2.0.1
	 * @return array[] Field definitions with label, args and optional sanitize callback.
	 */
	private function get_connection_fields() {
		return array(
			array(
				'label'    => __( 'API source', 'swiss-floorball-api' ),
				'args'     => $this->select_args(
					'swfl_api_source',
					array(
						SWFL_Client::SOURCE_FREE    => __( 'Free API (wc.swissunihockey.ch)', 'swiss-floorball-api' ),
						SWFL_Client::SOURCE_PARTNER => __( 'Partner API (API key and secret required)', 'swiss-floorball-api' ),
					),
					SWFL_Client::SOURCE_FREE,
					__( 'The free API needs no credentials but does not provide leagues, groups, topscorers, player profiles, national players and game events. Those shortcodes need the Partner API; the API key and secret fields appear once you select it.', 'swiss-floorball-api' )
				),
				'sanitize' => array( $this, 'sanitize_api_source' ),
			),
			array(
				'label'    => __( 'Partner API: API key', 'swiss-floorball-api' ),
				'args'     => $this->input_args(
					'swfl_api_key',
					'text',
					array( 'description' => __( 'The API key issued to you by Swiss Unihockey for the Partner API. Used together with the API secret to request a short-lived access token.', 'swiss-floorball-api' ) )
				),
				'sanitize' => array( $this, 'sanitize_partner_credential' ),
			),
			array(
				'label'    => __( 'Partner API: API secret', 'swiss-floorball-api' ),
				'args'     => $this->input_args(
					'swfl_api_secret',
					'password',
					array( 'description' => __( 'The API secret that belongs to the API key above. Both are required; it is stored in the database and never shown on the website.', 'swiss-floorball-api' ) )
				),
				'sanitize' => array( $this, 'sanitize_partner_secret' ),
			),
			array(
				'label'    => 'Swiss Floorball Club Number',
				'args'     => $this->input_args( 'swfl_club_number', 'number', array( 'required' => 'false' ) ),
				'sanitize' => array(
					'type'              => 'integer',
					'sanitize_callback' => array( $this, 'sanitize_club_number' ),
				),
			),
			array(
				'label' => 'Swiss Floorball Club Name',
				'args'  => $this->input_args(
					'swfl_club_name',
					'text',
					array(
						'required' => 'false',
						'disabled' => true,
					)
				),
			),
			array(
				'label'    => 'Swiss Floorball Aktuelle Saison (Jahrzahl, z.B. 2023)',
				'args'     => $this->input_args( 'swfl_actual_season', 'number', array( 'required' => 'false' ) ),
				'sanitize' => array(
					'type'              => 'integer',
					'sanitize_callback' => 'absint',
				),
			),
		);
	}

	/**
	 * Fields for icons, theme and seed colour.
	 *
	 * @since 2.0.1
	 * @return array[] Field definitions with label, args and sanitize callback.
	 */
	private function get_appearance_fields() {
		return array(
			array(
				'label'    => __( 'Show icons', 'swiss-floorball-api' ),
				'args'     => $this->input_args( 'swfl_show_icons', 'checkbox', array( 'default' => '1' ) ),
				'sanitize' => array( $this, 'sanitize_show_icons' ),
			),
			array(
				'label'    => __( 'Theme', 'swiss-floorball-api' ),
				'args'     => $this->select_args(
					'swfl_theme',
					array(
						'auto'  => __( 'Auto (follow system)', 'swiss-floorball-api' ),
						'light' => __( 'Light', 'swiss-floorball-api' ),
						'dark'  => __( 'Dark', 'swiss-floorball-api' ),
					),
					'auto',
					__( 'Controls the colour scheme of shortcodes and admin pages. Auto follows the visitor\'s system setting.', 'swiss-floorball-api' )
				),
				'sanitize' => array( $this, 'sanitize_theme' ),
			),
			array(
				'label'    => __( 'Seed colour', 'swiss-floorball-api' ),
				'args'     => $this->input_args(
					'swfl_seed_color',
					'text',
					array(
						'default'     => '',
						'placeholder' => '#0066cc',
						'description' => __( 'Optional brand colour (hex, e.g. #0066cc). The plugin derives its colour palette from it. Leave empty to use the default colours.', 'swiss-floorball-api' ),
					)
				),
				'sanitize' => array( $this, 'sanitize_seed_color' ),
			),
		);
	}

	/**
	 * Fields for the table style, the table colours and striped rows.
	 *
	 * @since 2.0.1
	 * @return array[] Field definitions with label, args and sanitize callback.
	 */
	private function get_table_fields() {
		$fields = array(
			array(
				'label'    => __( 'Table style', 'swiss-floorball-api' ),
				'args'     => $this->select_args(
					'swfl_table_style',
					array(
						'flat'    => __( 'Flat', 'swiss-floorball-api' ),
						'classic' => __( 'Classic (boxed, rounded)', 'swiss-floorball-api' ),
					),
					'flat',
					__( 'Flat tables have no border or shadow, thin row dividers and an accent line under the header.', 'swiss-floorball-api' )
				),
				'sanitize' => array( $this, 'sanitize_table_style' ),
			),
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
			$fields[] = array(
				'label'    => $color_labels[0],
				'args'     => $this->input_args(
					'swfl_table_' . $color_key . '_color',
					'text',
					array(
						'default'     => '',
						'description' => $color_labels[1],
					)
				),
				'sanitize' => array( $this, 'sanitize_seed_color' ),
			);
		}

		$fields[] = array(
			'label'    => __( 'Striped table rows', 'swiss-floorball-api' ),
			'args'     => $this->input_args( 'swfl_table_striped', 'checkbox', array( 'default' => '0' ) ),
			'sanitize' => array( $this, 'sanitize_show_icons' ),
		);

		return $fields;
	}

	/**
	 * Field for the API request timeout.
	 *
	 * @since 2.0.1
	 * @return array[] Field definitions with label, args and sanitize settings.
	 */
	private function get_request_fields() {
		return array(
			array(
				'label'    => __( 'API request timeout (seconds)', 'swiss-floorball-api' ),
				'args'     => $this->input_args(
					'swfl_request_timeout',
					'number',
					array(
						'min'         => '1',
						'max'         => '30',
						'step'        => '1',
						'default'     => '3',
						'description' => __( 'Maximum wait time for the Swiss Unihockey API in seconds (1-30, default 3). Can be overridden with the swfl_request_timeout filter.', 'swiss-floorball-api' ),
					)
				),
				'sanitize' => array(
					'type'              => 'integer',
					'sanitize_callback' => array( $this, 'sanitize_request_timeout' ),
					'default'           => 3,
				),
			),
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
		delete_transient( SWFL_Client::TOKEN_TRANSIENT );
		$sources = array(
			SWFL_Client::SOURCE_FREE,
			SWFL_Client::SOURCE_PARTNER,
		);
		return in_array( $value, $sources, true ) ? $value : SWFL_Client::SOURCE_FREE;
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
		delete_transient( SWFL_Client::TOKEN_TRANSIENT );
		return sanitize_text_field( $value );
	}

	/**
	 * Sanitize the Partner API secret and drop the cached token so a new secret is used.
	 *
	 * Unlike sanitize_text_field() this keeps every printable character (tags, percent sequences and inner
	 * whitespace can all be part of a valid secret). Only control characters and surrounding whitespace go,
	 * which no secret contains and which a copy and paste commonly adds. The value is never printed unescaped:
	 * it is sent in the body of the token request and shown through esc_attr().
	 *
	 * @since 2.0.2
	 *
	 * @param mixed $value Raw submitted value.
	 * @return string Secret without control characters and surrounding whitespace.
	 */
	public function sanitize_partner_secret( $value ) {
		delete_transient( SWFL_Client::TOKEN_TRANSIENT );
		if ( ! is_scalar( $value ) ) {
			return '';
		}
		return trim( preg_replace( '/[\x00-\x1F\x7F]/', '', (string) $value ) );
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
		$old_club_number = get_option( 'swfl_club_number' );
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
		if ( ! $club_changed && '' !== (string) get_option( 'swfl_club_name', '' ) ) {
			return $club_number;
		}

		// Fetch club details from API.
		require_once plugin_dir_path( __DIR__ ) . 'includes/class-swfl-client.php';
		$client = new SWFL_Client();
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
			update_option( 'swfl_club_name', sanitize_text_field( $club_name ) );
			// Add success notice.
			add_settings_error(
				'swfl_club_name',
				'club_name_updated',
				/* translators: %s: club name returned by the Swiss Unihockey API. */
				sprintf( __( 'Club name automatically set to: %s', 'swiss-floorball-api' ), $club_name ),
				'success'
			);
		} else {
			// If API call failed or no name found, clear the club name: it belongs to the previous club (or is already empty).
			update_option( 'swfl_club_name', '' );
			// Add error notice.
			add_settings_error(
				'swfl_club_name',
				'club_name_not_found',
				/* translators: %s: club ID that was entered in the settings. */
				sprintf( __( 'Could not find club name for club ID: %s', 'swiss-floorball-api' ), $club_number ),
				'error'
			);
		}

		return $club_number;
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
		$wp_data_value = null;
		if ( 'option' === $args['wp_data'] ) {
			$wp_data_value = get_option( $args['name'], isset( $args['default'] ) ? $args['default'] : false );
		} elseif ( 'post_meta' === $args['wp_data'] ) {
			$wp_data_value = get_post_meta( $args['post_id'], $args['name'], true );
		}

		if ( 'input' === $args['type'] ) {
			$this->render_input_field( $args, $wp_data_value );
		} elseif ( 'select' === $args['type'] ) {
			$this->render_select_field( $args, $wp_data_value );
		}
	}

	/**
	 * Render an input or checkbox settings field.
	 *
	 * @since 2.0.1
	 * @param array $args  Field definition.
	 * @param mixed $value Stored value.
	 */
	private function render_input_field( $args, $value ) {
		if ( 'serialized' === $args['value_type'] ) {
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- Display only; never unserialized.
			$value = serialize( $value );
		}

		if ( 'checkbox' === $args['subtype'] ) {
			printf(
				'<input type="%1$s" id="%2$s" %3$s name="%4$s" size="40" value="1" %5$s />',
				esc_attr( $args['subtype'] ),
				esc_attr( $args['id'] ),
				esc_attr( $args['required'] ),
				esc_attr( $args['name'] ),
				esc_attr( $value ? 'checked' : '' )
			);
		} else {
			$this->render_text_input( $args, $value );
		}

		if ( ! empty( $args['description'] ) ) {
			echo '<p class="description">' . esc_html( $args['description'] ) . '</p>';
		}
	}

	/**
	 * Render a text-like input, optionally with a prepended label and a hidden twin for disabled fields.
	 *
	 * @since 2.0.1
	 * @param array $args  Field definition.
	 * @param mixed $value Stored value.
	 */
	private function render_text_input( $args, $value ) {
		$extras = array();
		foreach ( array( 'step', 'max', 'min', 'placeholder' ) as $attribute ) {
			if ( isset( $args[ $attribute ] ) ) {
				$extras[] = $attribute . '="' . esc_attr( $args[ $attribute ] ) . '"';
			}
		}
		$extras = implode( ' ', $extras );

		if ( isset( $args['disabled'] ) ) {
			// Hide the actual input: a plain disabled input would submit nothing and wipe the stored value.
			$input = sprintf(
				'<input type="%1$s" id="%2$s_disabled" %3$s name="%4$s_disabled" size="40" disabled value="%5$s" /><input type="hidden" id="%2$s" %3$s name="%4$s" size="40" value="%5$s" />',
				esc_attr( $args['subtype'] ),
				esc_attr( $args['id'] ),
				$extras,
				esc_attr( $args['name'] ),
				esc_attr( $value )
			);
		} else {
			$input = sprintf(
				'<input type="%1$s" id="%2$s" %3$s %4$s name="%5$s" size="40" value="%6$s" />',
				esc_attr( $args['subtype'] ),
				esc_attr( $args['id'] ),
				esc_attr( $args['required'] ),
				$extras,
				esc_attr( $args['name'] ),
				esc_attr( $value )
			);
		}

		if ( isset( $args['prepend_value'] ) ) {
			$input = '<div class="input-prepend"> <span class="add-on">' . esc_html( $args['prepend_value'] ) . '</span>' . $input . '</div>';
		}
		echo $input; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Every dynamic part is escaped above.
	}

	/**
	 * Render a select settings field.
	 *
	 * @since 2.0.1
	 * @param array $args  Field definition.
	 * @param mixed $value Stored value.
	 */
	private function render_select_field( $args, $value ) {
		echo '<select id="' . esc_attr( $args['id'] ) . '" name="' . esc_attr( $args['name'] ) . '">';
		foreach ( $args['options'] as $option_value => $option_label ) {
			echo '<option value="' . esc_attr( $option_value ) . '"' . selected( $value, $option_value, false ) . '>' . esc_html( $option_label ) . '</option>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '</select>';
		if ( ! empty( $args['description'] ) ) {
			echo '<p class="description">' . esc_html( $args['description'] ) . '</p>';
		}
	}
}
