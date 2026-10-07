<?php
/**
 * Moves the settings stored under the legacy option prefix to the current one.
 *
 * @link       https://flaviowaser.ch
 * @since      2.0.2
 *
 * @package    SWFL
 * @subpackage SWFL_Plugin/includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renames the legacy `swissfloorball_*` options to `swfl_*`.
 *
 * WordPress.org requires one unique prefix of at least four characters for all globals, so the
 * options were renamed. Existing installations keep their settings because the values are copied
 * once and the old rows are removed afterwards.
 *
 * @since      2.0.2
 * @package    SWFL
 * @subpackage SWFL_Plugin/includes
 * @author     Flavio Waser <kontakt@flawas.ch>
 */
class SWFL_Migrator {

	/**
	 * Option that remembers that the legacy options have been moved on this site.
	 *
	 * @since 2.0.2
	 * @var   string
	 */
	const DONE_OPTION = 'swfl_options_migrated';

	/**
	 * Option names without the prefix.
	 *
	 * @since 2.0.2
	 * @var   string[]
	 */
	const OPTION_SUFFIXES = array(
		'api_key',
		'api_secret',
		'api_source',
		'club_number',
		'club_name',
		'actual_season',
		'request_timeout',
		'show_icons',
		'theme',
		'seed_color',
		'table_style',
		'table_striped',
		'table_accent_color',
		'table_header_color',
		'table_divider_color',
		'table_highlight_color',
	);

	/**
	 * Move the legacy options once per site.
	 *
	 * An already stored new value always wins over the legacy one, and a legacy row is only deleted
	 * once the new row is confirmed, so a failed write never loses a setting. The done flag is set
	 * only when every option has been moved, otherwise the next request tries again.
	 *
	 * @since 2.0.2
	 * @return void
	 */
	public static function maybe_migrate() {
		if ( get_option( self::DONE_OPTION ) ) {
			return;
		}

		$missing  = new stdClass();
		$complete = true;

		foreach ( self::OPTION_SUFFIXES as $suffix ) {
			$legacy_name = 'swissfloorball_' . $suffix;
			$value       = get_option( $legacy_name, $missing );

			if ( $missing === $value ) {
				continue;
			}

			// add_option() leaves an existing new value untouched.
			add_option( 'swfl_' . $suffix, $value );

			if ( get_option( 'swfl_' . $suffix, $missing ) === $missing ) {
				$complete = false;
				continue;
			}

			delete_option( $legacy_name );
		}

		if ( $complete ) {
			update_option( self::DONE_OPTION, '1' );
		}
	}
}
