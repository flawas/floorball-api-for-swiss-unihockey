<?php
/**
 * Sidebar widget with the next game and the latest result.
 *
 * @link       https://flaviowaser.ch
 * @since      2.0.4
 *
 * @package    SWFL
 * @subpackage SWFL_Plugin/includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Widget that shows the next game, the latest result, or both, of the configured club or one team.
 *
 * @since      2.0.4
 * @package    SWFL
 * @subpackage SWFL_Plugin/includes
 * @author     Flavio Waser <kontakt@flawas.ch>
 */
class SWFL_Game_Widget extends WP_Widget {

	/**
	 * Widget id base, also used to detect whether the widget is in use.
	 *
	 * @since 2.0.4
	 */
	const ID_BASE = 'swfl_game_widget';

	/**
	 * Register the widget with WordPress.
	 *
	 * @since 2.0.4
	 * @return void
	 */
	public static function register() {
		register_widget( __CLASS__ );
	}

	/**
	 * Set up the widget.
	 *
	 * @since 2.0.4
	 */
	public function __construct() {
		parent::__construct(
			self::ID_BASE,
			__( 'Swiss Floorball: Nächstes Spiel und letztes Resultat', 'swiss-floorball-api' ),
			array(
				'description'                 => __( 'Zeigt das nächste Spiel und das letzte Resultat des Vereins oder eines Teams.', 'swiss-floorball-api' ),
				'customize_selective_refresh' => true,
			)
		);
	}

	/**
	 * Allowed values of the display mode.
	 *
	 * @since 2.0.4
	 * @return string[] Display modes.
	 */
	private static function get_modes() {
		return array( 'both', 'next', 'last' );
	}

	/**
	 * Output the widget on the site.
	 *
	 * @since 2.0.4
	 * @param array $args     Widget area arguments (before/after markup).
	 * @param array $instance Saved widget settings.
	 * @return void
	 */
	public function widget( $args, $instance ) {
		$mode    = isset( $instance['display_mode'] ) && in_array( $instance['display_mode'], self::get_modes(), true ) ? $instance['display_mode'] : 'both';
		$team_id = isset( $instance['team_id'] ) ? absint( $instance['team_id'] ) : 0;
		$title   = isset( $instance['title'] ) ? (string) $instance['title'] : '';
		$club_id = absint( get_option( 'swfl_club_number' ) );
		$season  = absint( SWFL_Display::get_current_season() );

		// The wrapper class carries the plugin styles, which a sidebar is outside of otherwise.
		$classes = 'swiss-floorball-plugin sfa-table--' . SWFL_Theme::get_table_style();
		if ( SWFL_Theme::is_table_striped() ) {
			$classes .= ' sfa-table--striped';
		}

		// Widget area markup comes from the theme, as WordPress core does for its own widgets.
		echo wp_kses_post( $args['before_widget'] );
		if ( '' !== $title ) {
			echo wp_kses_post( $args['before_title'] ) . esc_html( apply_filters( 'widget_title', $title, $instance, $this->id_base ) ) . wp_kses_post( $args['after_title'] );
		}
		echo '<div class="' . esc_attr( $classes ) . '" data-sfa-theme="' . esc_attr( SWFL_Theme::get_theme() ) . '">';
		if ( 'last' !== $mode ) {
			SWFL_Highlights::render_next_game( $club_id, $season, $team_id );
		}
		if ( 'next' !== $mode ) {
			SWFL_Highlights::render_last_result( $club_id, $season, $team_id );
		}
		echo '</div>';
		echo wp_kses_post( $args['after_widget'] );
	}

	/**
	 * Output the settings form in the widget screen.
	 *
	 * @since 2.0.4
	 * @param array $instance Saved widget settings.
	 * @return void
	 */
	public function form( $instance ) {
		$title   = isset( $instance['title'] ) ? $instance['title'] : '';
		$mode    = isset( $instance['display_mode'] ) ? $instance['display_mode'] : 'both';
		$team_id = isset( $instance['team_id'] ) ? absint( $instance['team_id'] ) : 0;
		$labels  = array(
			'both' => __( 'Nächstes Spiel und letztes Resultat', 'swiss-floorball-api' ),
			'next' => __( 'Nur nächstes Spiel', 'swiss-floorball-api' ),
			'last' => __( 'Nur letztes Resultat', 'swiss-floorball-api' ),
		);
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Titel:', 'swiss-floorball-api' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" />
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'display_mode' ) ); ?>"><?php esc_html_e( 'Anzeige:', 'swiss-floorball-api' ); ?></label>
			<select class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'display_mode' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'display_mode' ) ); ?>">
				<?php foreach ( $labels as $value => $label ) : ?>
					<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $mode, $value ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'team_id' ) ); ?>"><?php esc_html_e( 'Team-ID (optional, sonst der ganze Verein):', 'swiss-floorball-api' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'team_id' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'team_id' ) ); ?>" type="number" min="0" step="1" value="<?php echo esc_attr( $team_id > 0 ? $team_id : '' ); ?>" />
		</p>
		<?php
	}

	/**
	 * Sanitize the settings when the widget is saved.
	 *
	 * @since 2.0.4
	 * @param array $new_instance New settings.
	 * @param array $old_instance Previous settings.
	 * @return array Sanitized settings.
	 */
	public function update( $new_instance, $old_instance ) {
		unset( $old_instance );
		$mode = isset( $new_instance['display_mode'] ) ? sanitize_key( $new_instance['display_mode'] ) : 'both';

		return array(
			'title'        => isset( $new_instance['title'] ) ? sanitize_text_field( $new_instance['title'] ) : '',
			'display_mode' => in_array( $mode, self::get_modes(), true ) ? $mode : 'both',
			'team_id'      => isset( $new_instance['team_id'] ) ? absint( $new_instance['team_id'] ) : 0,
		);
	}
}
