<?php
/**
 * The PHP versions the plugin may run on.
 *
 * @link       https://flaviowaser.ch
 * @since      2.2.0
 *
 * @package    SWFL
 * @subpackage SWFL_Plugin/includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tells whether the running PHP version may run the plugin.
 *
 * PHP 8.2.35, 8.3.36, 8.4.27 and 8.5.12 fix two critical remote code execution vulnerabilities
 * (CVE-2026-109618, CVE-2026-109619). The plugin runs on these releases and on every newer release of their
 * branch, and on newer branches; older releases and the branches 8.1 and older (no fixes at all) pause it. The
 * "Requires PHP" header can only hold one minimum, so the plugin checks the minimum of the running branch itself.
 *
 * Kept free of newer syntax: this file has to load on old PHP versions to show the notice instead of an error.
 *
 * @since      2.2.0
 * @package    SWFL
 * @subpackage SWFL_Plugin/includes
 * @author     Flavio Waser <kontakt@flawas.ch>
 */
class SWFL_Php_Requirement {

	/**
	 * First allowed release per branch, oldest branch first. Branches newer than the last one are allowed.
	 *
	 * @since 2.2.0
	 * @var   string[]
	 */
	const MINIMUMS = array(
		'8.2' => '8.2.35',
		'8.3' => '8.3.36',
		'8.4' => '8.4.27',
		'8.5' => '8.5.12',
	);

	/**
	 * Constant a site defines in wp-config.php to run the plugin on an older version anyway, e.g. a distribution
	 * build that has the fixes under an older version number (backports).
	 *
	 * @since 2.2.0
	 * @var   string
	 */
	const OVERRIDE = 'SWFL_ALLOW_OTHER_PHP';

	/**
	 * Whether the plugin may run.
	 *
	 * @since 2.2.0
	 * @param string|null $version Optional. PHP version as major.minor.release, for tests. Default: the running one.
	 * @return bool True for an allowed version, or when the site set the override.
	 */
	public static function is_met( $version = null ) {
		return ( defined( self::OVERRIDE ) && constant( self::OVERRIDE ) ) || null === self::get_required( null === $version ? self::get_running() : (string) $version );
	}

	/**
	 * The release a PHP version has to be updated to.
	 *
	 * @since 2.2.0
	 * @param string $version PHP version as major.minor.release.
	 * @return string|null Release to update to, null when the version is allowed.
	 */
	public static function get_required( $version ) {
		$minimums = self::MINIMUMS;
		$branch   = implode( '.', array_slice( explode( '.', $version ), 0, 2 ) );
		$required = null;
		if ( isset( $minimums[ $branch ] ) ) {
			$required = version_compare( $version, $minimums[ $branch ], '>=' ) ? null : $minimums[ $branch ];
		} elseif ( version_compare( $branch, (string) key( $minimums ), '<' ) ) {
			// Older branches get no fixes: the oldest allowed branch is the way out.
			$required = reset( $minimums );
		}

		return $required;
	}

	/**
	 * The running PHP version as major.minor.release.
	 *
	 * PHP_VERSION of distribution builds carries suffixes such as "-1+ubuntu24.04.1", so the version is built
	 * from the numeric parts.
	 *
	 * @since 2.2.0
	 * @return string Version, e.g. `8.3.36`.
	 */
	public static function get_running() {
		return PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION . '.' . PHP_RELEASE_VERSION;
	}

	/**
	 * Admin notice while the plugin is paused.
	 *
	 * @since 2.2.0
	 * @return void
	 */
	public static function render_notice() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		echo '<div class="notice notice-error"><p>' . esc_html( self::get_message() ) . '</p></div>';
	}

	/**
	 * Refuse to activate the plugin on a version that is not allowed.
	 *
	 * @since 2.2.0
	 * @return void
	 */
	public static function refuse_activation() {
		if ( ! self::is_met() ) {
			wp_die( esc_html( self::get_message() ), esc_html__( 'Plugin could not be activated', 'swiss-floorball-api' ), array( 'back_link' => true ) );
		}
	}

	/**
	 * Explanation for administrators.
	 *
	 * @since 2.2.0
	 * @return string Message.
	 */
	public static function get_message() {
		return sprintf(
			/* translators: 1: running PHP version, 2: version to update to, 3: list of the first allowed versions, 4: name of the wp-config.php constant. */
			__( 'Swiss Floorball API is paused: PHP %1$s has critical security vulnerabilities (CVE-2026-109618, CVE-2026-109619). Update PHP to %2$s or newer; the plugin runs on %3$s and on every newer version. If your host has applied the fixes without changing the version number, define %4$s in wp-config.php.', 'swiss-floorball-api' ),
			self::get_running(),
			(string) self::get_required( self::get_running() ),
			implode( ', ', self::MINIMUMS ),
			self::OVERRIDE
		);
	}
}
