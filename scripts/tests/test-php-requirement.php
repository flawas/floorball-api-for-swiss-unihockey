<?php
/**
 * Tests of SWFL_Php_Requirement: the PHP versions the plugin may run on.
 *
 * @package SWFL
 */

swfl_test(
	'php requirement: 8.2.35, 8.3.36, 8.4.27, 8.5.12 and newer run, older releases and branches are paused',
	function () {
		$cases = array(
			'8.2.35' => null,
			'8.2.36' => null,
			'8.2.34' => '8.2.35',
			'8.2.0'  => '8.2.35',
			'8.3.36' => null,
			'8.3.40' => null,
			'8.3.35' => '8.3.36',
			'8.4.27' => null,
			'8.4.28' => null,
			'8.4.26' => '8.4.27',
			'8.5.12' => null,
			'8.5.13' => null,
			'8.5.11' => '8.5.12',
			'8.6.0'  => null,
			'9.0.0'  => null,
			'8.1.33' => '8.2.35',
			'8.0.30' => '8.2.35',
			'7.4.33' => '8.2.35',
		);
		foreach ( $cases as $version => $required ) {
			swfl_same( $required, SWFL_Php_Requirement::get_required( (string) $version ), (string) $version );
			swfl_same( null === $required, SWFL_Php_Requirement::is_met( (string) $version ), 'is_met ' . $version );
		}
		swfl_same( PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION . '.' . PHP_RELEASE_VERSION, SWFL_Php_Requirement::get_running() );
	}
);

swfl_test(
	'php requirement: the message names the running version, the allowed versions and the override',
	function () {
		$message = SWFL_Php_Requirement::get_message();
		swfl_same( true, false !== strpos( $message, 'PHP ' . SWFL_Php_Requirement::get_running() . ' has' ) );
		swfl_same( true, false !== strpos( $message, '8.2.35, 8.3.36, 8.4.27, 8.5.12 and on every newer version' ) );
		swfl_same( true, false !== strpos( $message, 'CVE-2026-109618' ) && false !== strpos( $message, SWFL_Php_Requirement::OVERRIDE ) );
	}
);

swfl_test(
	'php requirement: the wp-config.php constant lets an older version run',
	function () {
		swfl_same( false, SWFL_Php_Requirement::is_met( '8.3.20' ) );
		define( 'SWFL_ALLOW_OTHER_PHP', true );
		swfl_same( true, SWFL_Php_Requirement::is_met( '8.3.20' ) );
	}
);
