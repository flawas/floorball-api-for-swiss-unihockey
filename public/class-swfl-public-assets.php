<?php
/**
 * Styles and scripts of the public side.
 *
 * @link       https://flaviowaser.ch
 * @since      2.0.1
 *
 * @package    SWFL
 * @subpackage SWFL_Plugin/public
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enqueues the public styles and scripts, only on pages that use a plugin shortcode.
 *
 * Split out of SWFL_Public, which keeps the shortcode callbacks.
 *
 * @since      2.0.1
 * @package    SWFL
 * @subpackage SWFL_Plugin/public
 * @author     Flavio Waser <kontakt@flawas.ch>
 */
class SWFL_Public_Assets {

	/**
	 * The ID of this plugin.
	 *
	 * @since 2.0.1
	 * @var   string
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since 2.0.1
	 * @var   string
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since 2.0.1
	 * @param string $plugin_name The name of this plugin.
	 * @param string $version     The current version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	private function page_has_shortcode() {
		// A sidebar widget is outside of the post content, so it is looked up on its own.
		if ( is_active_widget( false, false, SWFL_Game_Widget::ID_BASE, true ) ) {
			return true;
		}
		$post = get_post();
		if ( ! is_a( $post, 'WP_Post' ) ) {
			return false;
		}
		$shortcodes = array(
			'swfl-club-teams',
			'swfl-club-games',
			'swfl-team-games',
			'swfl-clubs',
			'swfl-calendars',
			'swfl-cups',
			'swfl-groups',
			'swfl-teams',
			'swfl-rankings',
			'swfl-player',
			'swfl-national-players',
			'swfl-topscorers',
			'swfl-game-events',
			'swfl-league-games',
			'swfl-club-team-games',
			'swfl-mobiliar-topscorer',
			'swfl-next-game',
			'swfl-last-result',
		);
		foreach ( $shortcodes as $shortcode ) {
			if ( has_shortcode( $post->post_content, $shortcode ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {
		if ( ! $this->page_has_shortcode() ) {
			return;
		}
		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/swfl-public.css', array(), $this->version, 'all' );
		$seed_css = SWFL_Theme::get_seed_css();
		if ( '' !== $seed_css ) {
			wp_add_inline_style( $this->plugin_name, wp_strip_all_tags( $seed_css ) );
		}
		$table_css = SWFL_Theme::get_table_css();
		if ( '' !== $table_css ) {
			wp_add_inline_style( $this->plugin_name, wp_strip_all_tags( $table_css ) );
		}
	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {
		if ( ! $this->page_has_shortcode() ) {
			return;
		}
		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/swfl-public.js', array( 'jquery' ), $this->version, false );
		wp_enqueue_script( $this->plugin_name . '-widgets', plugin_dir_url( __FILE__ ) . 'js/swfl-widgets.js', array(), $this->version, true );
		wp_localize_script(
			$this->plugin_name . '-widgets',
			'swflWidgets',
			array(
				'restUrl' => esc_url_raw( rest_url( 'swfl/v1/' ) ),
				'retry'   => __( 'The data could not be loaded just now. Please try again in a moment.', 'swiss-floorball-api' ),
				/* translators: 1: first game number, 2: last game number, 3: total number of games. */
				'range'   => __( '%1$d–%2$d of %3$d', 'swiss-floorball-api' ),
			)
		);
	}
}
