<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://flaviowaser.ch
 * @since      1.0.0
 *
 * @package    Swiss_Floorball_Api
 * @subpackage Swiss_Floorball_Api/public
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    Swiss_Floorball_Api
 * @subpackage Swiss_Floorball_Api/public
 * @author     Flavio Waser <kontakt@flawas.ch>
 */
class Swiss_Floorball_Api_Public {

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
	 * @param      string    $plugin_name       The name of the plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;
		$this->register_shortcodes();
		$this->load_dependencies();
	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	private function page_has_shortcode() {
		$post = get_post();
		if ( ! is_a( $post, 'WP_Post' ) ) {
			return false;
		}
		$shortcodes = array(
			'swfl-club-teams', 'swfl-club-games', 'swfl-team-games', 'swfl-clubs',
			'swfl-calendars', 'swfl-cups', 'swfl-groups', 'swfl-teams',
			'swfl-rankings', 'swfl-player', 'swfl-national-players',
			'swfl-topscorers', 'swfl-game-events',
		);
		foreach ( $shortcodes as $shortcode ) {
			if ( has_shortcode( $post->post_content, $shortcode ) ) {
				return true;
			}
		}
		return false;
	}

	public function enqueue_styles() {
		if ( ! $this->page_has_shortcode() ) {
			return;
		}
		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/floorball-api-for-swiss-unihockey-public.css', array(), $this->version, 'all' );
	}

	public function enqueue_scripts() {
		if ( ! $this->page_has_shortcode() ) {
			return;
		}
		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/floorball-api-for-swiss-unihockey-public.js', array( 'jquery' ), $this->version, false );
	}

	public function load_dependencies() {
		// Functions are now loaded via the main plugin class and Swiss_Floorball_API_Display
	}

	/**
	 * Registers all shortcodes at once
	 *
	 * @return [type] [description]
	 */
	public function register_shortcodes() {

		add_shortcode( 'swfl-club-teams', array( $this, 'get_club_teams_func' ) );
		add_shortcode( 'swfl-club-games', array( $this, 'get_club_games_func' ) );
		add_shortcode( 'swfl-team-games', array( $this, 'get_team_games_func' ) );
        add_shortcode( 'swfl-clubs', array( $this, 'get_clubs_func' ) );
        add_shortcode( 'swfl-calendars', array( $this, 'get_calendars_func' ) );
        add_shortcode( 'swfl-cups', array( $this, 'get_cups_func' ) );
        add_shortcode( 'swfl-groups', array( $this, 'get_groups_func' ) );
        add_shortcode( 'swfl-teams', array( $this, 'get_teams_func' ) );
        add_shortcode( 'swfl-rankings', array( $this, 'get_rankings_func' ) );
        add_shortcode( 'swfl-player', array( $this, 'get_player_func' ) );
        add_shortcode( 'swfl-national-players', array( $this, 'get_national_players_func' ) );
        add_shortcode( 'swfl-topscorers', array( $this, 'get_topscorers_func' ) );
        add_shortcode( 'swfl-game-events', array( $this, 'get_game_events_func' ) );

	} // register_shortcodes()

	public function get_club_teams_func() {
		ob_start();
		echo '<div class="swiss-floorball-plugin">';
		Swiss_Floorball_API_Display::render_club_teams(get_option('swissfloorball_club_number'));
		echo '</div>';
		$output = ob_get_contents();
		ob_end_clean();
		return $output;
	}

	public function get_club_games_func(){
		ob_start();
		echo '<div class="swiss-floorball-plugin">';
		Swiss_Floorball_API_Display::render_club_games(get_option('swissfloorball_club_number'), get_option('swissfloorball_actual_season'));
		echo '</div>';
		$output = ob_get_contents();
		ob_end_clean();
		return $output;
	}

	public function get_team_games_func( $atts ){
		$a = shortcode_atts( array(
			'team_id' => '',
		), $atts );

		ob_start();
		echo '<div class="swiss-floorball-plugin">';
		Swiss_Floorball_API_Display::render_team_games( absint( $a['team_id'] ), get_option('swissfloorball_actual_season') );
		echo '</div>';
		$output = ob_get_contents();
		ob_end_clean();
		return $output;
	}

    public function get_clubs_func() {
        ob_start();
        echo '<div class="swiss-floorball-plugin">';
        Swiss_Floorball_API_Display::render_clubs();
        echo '</div>';
        $output = ob_get_contents();
        ob_end_clean();
        return $output;
    }

    public function get_calendars_func( $atts ) {
        $a = shortcode_atts( array(
            'team_id' => '',
            'club_id' => '',
            'season' => '',
            'league' => '',
            'game_class' => '',
            'group' => '',
        ), $atts );

        ob_start();
        echo '<div class="swiss-floorball-plugin">';
        Swiss_Floorball_API_Display::render_calendars(
            $a['team_id']    ? absint( $a['team_id'] )    : null,
            $a['club_id']    ? absint( $a['club_id'] )    : null,
            $a['season']     ? absint( $a['season'] )     : null,
            $a['league']     ? absint( $a['league'] )     : null,
            $a['game_class'] ? absint( $a['game_class'] ) : null,
            $a['group']      ? absint( $a['group'] )      : null
        );
        echo '</div>';
        $output = ob_get_contents();
        ob_end_clean();
        return $output;
    }

    public function get_cups_func() {
        ob_start();
        echo '<div class="swiss-floorball-plugin">';
        Swiss_Floorball_API_Display::render_cups();
        echo '</div>';
        $output = ob_get_contents();
        ob_end_clean();
        return $output;
    }

    public function get_groups_func( $atts ) {
        $a = shortcode_atts( array(
            'season' => get_option('swissfloorball_actual_season'),
            'league' => '',
            'game_class' => '',
        ), $atts );

        ob_start();
        echo '<div class="swiss-floorball-plugin">';
        Swiss_Floorball_API_Display::render_groups( absint( $a['season'] ), absint( $a['league'] ), absint( $a['game_class'] ) );
        echo '</div>';
        $output = ob_get_contents();
        ob_end_clean();
        return $output;
    }

    public function get_teams_func() {
        ob_start();
        echo '<div class="swiss-floorball-plugin">';
        Swiss_Floorball_API_Display::render_teams();
        echo '</div>';
        $output = ob_get_contents();
        ob_end_clean();
        return $output;
    }

    public function get_rankings_func( $atts ) {
        $a = shortcode_atts( array(
            'season' => get_option('swissfloorball_actual_season'),
            'league' => '',
            'game_class' => '',
            'group' => '',
        ), $atts );

        ob_start();
        echo '<div class="swiss-floorball-plugin">';
        Swiss_Floorball_API_Display::render_rankings( absint( $a['season'] ), absint( $a['league'] ), absint( $a['game_class'] ), absint( $a['group'] ) );
        echo '</div>';
        $output = ob_get_contents();
        ob_end_clean();
        return $output;
    }

    public function get_player_func( $atts ) {
        $a = shortcode_atts( array(
            'player_id' => '',
        ), $atts );

        ob_start();
        echo '<div class="swiss-floorball-plugin">';
        Swiss_Floorball_API_Display::render_player( absint( $a['player_id'] ) );
        echo '</div>';
        $output = ob_get_contents();
        ob_end_clean();
        return $output;
    }

    public function get_national_players_func() {
        ob_start();
        echo '<div class="swiss-floorball-plugin">';
        Swiss_Floorball_API_Display::render_national_players();
        echo '</div>';
        $output = ob_get_contents();
        ob_end_clean();
        return $output;
    }

    public function get_topscorers_func( $atts ) {
        $a = shortcode_atts( array(
            'season' => get_option('swissfloorball_actual_season'),
            'league' => '',
            'game_class' => '',
            'group' => '',
        ), $atts );

        ob_start();
        echo '<div class="swiss-floorball-plugin">';
        Swiss_Floorball_API_Display::render_topscorers( absint( $a['season'] ), absint( $a['league'] ), absint( $a['game_class'] ), absint( $a['group'] ) );
        echo '</div>';
        $output = ob_get_contents();
        ob_end_clean();
        return $output;
    }

    public function get_game_events_func( $atts ) {
        $a = shortcode_atts( array(
            'game_id' => '',
        ), $atts );

        ob_start();
        echo '<div class="swiss-floorball-plugin">';
        Swiss_Floorball_API_Display::render_game_events( absint( $a['game_id'] ) );
        echo '</div>';
        $output = ob_get_contents();
        ob_end_clean();
        return $output;
    }

}
