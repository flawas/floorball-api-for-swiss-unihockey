<?php
/**
 * Define the internationalization functionality
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
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
 * Define the internationalization functionality.
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @since      1.0.0
 * @package    SWFL
 * @subpackage SWFL_Plugin/includes
 * @author     Flavio Waser <kontakt@flawas.ch>
 */
class SWFL_I18n {


	/**
	 * Load the translation that ships with the plugin (languages/), unless WordPress has a language pack.
	 *
	 * WordPress loads language packs from translate.wordpress.org on its own, so the bundled file is only the
	 * fallback. The plugin ships German (de_CH, de_DE), French (fr_FR) and Italian (it_IT); regional locales use
	 * the file of their language: de_CH_informal → de_CH, de_AT or de_DE_formal → de_DE, fr_CA or fr_BE → fr_FR.
	 *
	 * @since    1.0.0
	 * @return void
	 */
	public function load_plugin_textdomain() {
		$locale = determine_locale();
		if ( file_exists( WP_LANG_DIR . '/plugins/swiss-floorball-api-' . $locale . '.mo' ) ) {
			return;
		}

		$fallbacks  = array(
			'de' => 0 === strpos( $locale, 'de_CH' ) ? 'de_CH' : 'de_DE',
			'fr' => 'fr_FR',
			'it' => 'it_IT',
		);
		$language   = substr( $locale, 0, 2 );
		$candidates = isset( $fallbacks[ $language ] ) ? array( $locale, $fallbacks[ $language ] ) : array( $locale );
		foreach ( $candidates as $candidate ) {
			$file = dirname( __DIR__ ) . '/languages/swiss-floorball-api-' . $candidate . '.mo';
			if ( file_exists( $file ) ) {
				load_textdomain( 'swiss-floorball-api', $file );
				return;
			}
		}
	}
}
