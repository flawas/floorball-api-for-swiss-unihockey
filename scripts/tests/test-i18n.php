<?php
/**
 * Tests of SWFL_I18n: which bundled translation is loaded.
 *
 * @package SWFL
 */

swfl_test(
	'translations: every locale of a shipped language finds its file, other languages nothing',
	function () {
		$cases = array(
			'de_CH'          => 'swiss-floorball-api-de_CH.mo',
			'de_CH_informal' => 'swiss-floorball-api-de_CH.mo',
			'de_DE'          => 'swiss-floorball-api-de_DE.mo',
			'de_DE_formal'   => 'swiss-floorball-api-de_DE.mo',
			'de_AT'          => 'swiss-floorball-api-de_DE.mo',
			'fr_FR'          => 'swiss-floorball-api-fr_FR.mo',
			'fr_CA'          => 'swiss-floorball-api-fr_FR.mo',
			'fr_BE'          => 'swiss-floorball-api-fr_FR.mo',
			'it_IT'          => 'swiss-floorball-api-it_IT.mo',
			'en_US'          => null,
			'es_ES'          => null,
		);
		foreach ( $cases as $locale => $file ) {
			$GLOBALS['swfl_t']['locale']     = $locale;
			$GLOBALS['swfl_t']['textdomain'] = array();
			( new SWFL_I18n() )->load_plugin_textdomain();
			swfl_same( null === $file ? array() : array( array( 'swiss-floorball-api', $file ) ), $GLOBALS['swfl_t']['textdomain'], $locale );
		}
	}
);

swfl_test(
	'translations: a language pack from translate.wordpress.org wins over the bundled file',
	function () {
		$dir = WP_LANG_DIR . '/plugins';
		if ( ! is_dir( $dir ) ) {
			mkdir( $dir, 0777, true );
		}
		touch( $dir . '/swiss-floorball-api-de_CH.mo' );
		$GLOBALS['swfl_t']['locale'] = 'de_CH';
		( new SWFL_I18n() )->load_plugin_textdomain();
		unlink( $dir . '/swiss-floorball-api-de_CH.mo' );
		rmdir( $dir );
		rmdir( WP_LANG_DIR );
		swfl_same( array(), $GLOBALS['swfl_t']['textdomain'], 'WordPress loads the pack itself' );
	}
);

swfl_test(
	'translations: German, French and Italian ship and translate every string of the template',
	function () {
		$dir = ABSPATH . 'languages/';
		preg_match_all( '/^msgid "/m', (string) file_get_contents( $dir . 'swiss-floorball-api.pot' ), $template );
		foreach ( array( 'de_CH', 'de_DE', 'fr_FR', 'it_IT' ) as $locale ) {
			swfl_same( true, is_file( $dir . 'swiss-floorball-api-' . $locale . '.mo' ), $locale . '.mo' );
			$po = (string) file_get_contents( $dir . 'swiss-floorball-api-' . $locale . '.po' );
			preg_match_all( '/^msgid "/m', $po, $ids );
			swfl_same( count( $template[0] ), count( $ids[0] ), $locale . ' has every string of the template' );
			swfl_same( 0, preg_match_all( "/^msgstr(\\[\\d\\])? \"\"\n(?:\n|\z)/m", $po ), $locale . " has no empty translation" );
		}
	}
);
