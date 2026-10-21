<?php
/**
 * Offline test runner: php scripts/tests/run.php [name-filter]
 *
 * Loads bootstrap.php and every test-*.php next to it, then runs the tests whose name contains the optional filter.
 *
 * @package SWFL
 */

if ( 'cli' !== PHP_SAPI ) {
	exit;
}

require __DIR__ . '/bootstrap.php';

foreach ( glob( __DIR__ . '/test-*.php' ) as $swfl_test_file ) {
	require $swfl_test_file;
}

if ( isset( $argv[1] ) ) {
	$swfl_filter = $argv[1];
	foreach ( array_keys( $GLOBALS['swfl_tests'] ) as $swfl_name ) {
		if ( false === stripos( $swfl_name, $swfl_filter ) ) {
			unset( $GLOBALS['swfl_tests'][ $swfl_name ] );
		}
	}
}

swfl_run();
