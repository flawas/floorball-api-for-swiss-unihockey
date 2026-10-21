<?php
/**
 * Tests of SWFL_Request_Guard: REST argument validation (Issue 113) and the rate limit (Issue 115).
 *
 * @package SWFL
 */

swfl_test(
	'guard: a required ID is a whole number from 1 to 10 million',
	function () {
		foreach ( array( '1', '10000000', '005', 5, 10000000, '0000000001' ) as $value ) {
			swfl_same( true, SWFL_Request_Guard::validate_required_id( $value ), 'accepts ' . var_export( $value, true ) );
		}
		$rejected = array( '0', '', '-5', 'abc', '10000001', '1e3', '5.0', ' 5', '5 ', '+5', '0x1A', array( 5 ), -5, 0, 5.0, true, null, '99999999999', "5\n", '５' );
		foreach ( $rejected as $value ) {
			swfl_same( true, is_wp_error( SWFL_Request_Guard::validate_required_id( $value ) ), 'rejects ' . var_export( $value, true ) );
		}
	}
);

swfl_test(
	'guard: an optional ID may also be empty or 0, which both mean "not set"',
	function () {
		foreach ( array( '', '0', 0, '1', '10000000', '00' ) as $value ) {
			swfl_same( true, SWFL_Request_Guard::validate_optional_id( $value ), 'accepts ' . var_export( $value, true ) );
		}
		foreach ( array( '-5', 'abc', '10000001', '1e3', '5.0', ' 5', array(), -1, null, false ) as $value ) {
			swfl_same( true, is_wp_error( SWFL_Request_Guard::validate_optional_id( $value ) ), 'rejects ' . var_export( $value, true ) );
		}
	}
);

swfl_test(
	'guard: season boundaries in 2026',
	function () {
		$GLOBALS['swfl_t']['year'] = '2026';
		swfl_same( 2027, SWFL_Request_Guard::get_max_season() );
		foreach ( array( '', '0', '1996', '2000', '2025', '2026', '2027', 2026 ) as $value ) {
			swfl_same( true, SWFL_Request_Guard::validate_season( $value ), 'accepts ' . var_export( $value, true ) );
		}
		foreach ( array( '1', '1995', '2028', '99999', '-1', 'abc', '2025.5', ' 2025', '2e3', array() ) as $value ) {
			swfl_same( true, is_wp_error( SWFL_Request_Guard::validate_season( $value ) ), 'rejects ' . var_export( $value, true ) );
		}
	}
);

swfl_test(
	'guard: the season limit follows the later of the configured season and the current year',
	function () {
		$GLOBALS['swfl_t']['year'] = '2026';
		$GLOBALS['swfl_t']['options']['swfl_actual_season'] = '2025';
		swfl_same( 2027, SWFL_Request_Guard::get_max_season(), 'pinned to last season' );
		$GLOBALS['swfl_t']['options']['swfl_actual_season'] = '2020';
		swfl_same( true, SWFL_Request_Guard::validate_season( '2027' ), 'an old pin does not block newer seasons' );
		$GLOBALS['swfl_t']['options']['swfl_actual_season'] = '2029';
		swfl_same( 2030, SWFL_Request_Guard::get_max_season(), 'pinned ahead' );
		$GLOBALS['swfl_t']['options']['swfl_actual_season'] = '';
		$GLOBALS['swfl_t']['year']                          = '2027';
		swfl_same( 2028, SWFL_Request_Guard::get_max_season(), 'the next calendar year' );
	}
);

swfl_test(
	'guard: a group is empty or up to 40 letters, digits, spaces and . _ / -',
	function () {
		foreach ( array( '', 'Gruppe 1', 'Gr. A/B-2', 'Über_Liga', str_repeat( 'a', 40 ), '   ' ) as $value ) {
			swfl_same( true, SWFL_Request_Guard::validate_group( $value ), 'accepts ' . var_export( $value, true ) );
		}
		$rejected = array( str_repeat( 'a', 41 ), '<script>', 'a&b=c', "a\nb", "a\0b", array( 'x' ), "\xff\xfe", 'x%20y', 'a;b', "abc\n" );
		foreach ( $rejected as $value ) {
			swfl_same( true, is_wp_error( SWFL_Request_Guard::validate_group( $value ) ), 'rejects ' . var_export( $value, true ) );
		}
	}
);

swfl_test(
	'guard: argument definitions carry callable validators and sanitizers',
	function () {
		$required = SWFL_Request_Guard::int_arg( true );
		swfl_same( true, $required['required'] );
		swfl_same( array( 'SWFL_Request_Guard', 'validate_required_id' ), $required['validate_callback'] );
		swfl_same( 'absint', $required['sanitize_callback'] );
		swfl_same( false, SWFL_Request_Guard::int_arg()['required'] );
		swfl_same( array( 'SWFL_Request_Guard', 'validate_optional_id' ), SWFL_Request_Guard::int_arg()['validate_callback'] );
		swfl_same( array( 'SWFL_Request_Guard', 'validate_season' ), SWFL_Request_Guard::season_arg()['validate_callback'] );
		swfl_same( array( 'SWFL_Request_Guard', 'sanitize_group' ), SWFL_Request_Guard::group_arg()['sanitize_callback'] );
		foreach ( array( SWFL_Request_Guard::int_arg(), SWFL_Request_Guard::season_arg(), SWFL_Request_Guard::group_arg() ) as $definition ) {
			swfl_same( true, is_callable( $definition['validate_callback'] ) && is_callable( $definition['sanitize_callback'] ) );
		}
	}
);

/**
 * Pretend the next request comes from an address.
 *
 * @param string      $remote_addr REMOTE_ADDR.
 * @param string|null $forwarded   X-Forwarded-For, null for none.
 */
function swfl_test_visitor( $remote_addr, $forwarded = null ) {
	$_SERVER['REMOTE_ADDR'] = $remote_addr;
	unset( $_SERVER['HTTP_X_FORWARDED_FOR'] );
	if ( null !== $forwarded ) {
		$_SERVER['HTTP_X_FORWARDED_FOR'] = $forwarded;
	}
}

swfl_test(
	'rate limit: 429 with retry_after after the limit, per IPv4 address',
	function () {
		$GLOBALS['swfl_t']['options']['swfl_rest_rate_limit'] = 3;
		swfl_test_visitor( '203.0.113.7' );
		for ( $i = 0; $i < 3; $i++ ) {
			swfl_same( true, SWFL_Request_Guard::check_rate_limit( new WP_REST_Request() ) );
		}
		$error = SWFL_Request_Guard::check_rate_limit( new WP_REST_Request() );
		swfl_same( 'swfl_rate_limited', $error->get_error_code() );
		swfl_same( 429, $error->get_error_data()['status'] );
		swfl_same( true, $error->get_error_data()['retry_after'] >= 1 && $error->get_error_data()['retry_after'] <= 60 );
		swfl_test_visitor( '203.0.113.8' );
		swfl_same( true, SWFL_Request_Guard::check_rate_limit( new WP_REST_Request() ), 'another address has its own budget' );
	}
);

swfl_test(
	'rate limit: the addresses of one IPv6 /64 share a budget (Issue 115)',
	function () {
		$GLOBALS['swfl_t']['options']['swfl_rest_rate_limit'] = 2;
		swfl_test_visitor( '2001:db8:1:2::1' );
		swfl_same( true, SWFL_Request_Guard::check_rate_limit( new WP_REST_Request() ) );
		swfl_test_visitor( '2001:db8:1:2:ffff:eeee:dddd:cccc' );
		swfl_same( true, SWFL_Request_Guard::check_rate_limit( new WP_REST_Request() ) );
		swfl_test_visitor( '2001:db8:1:2:abcd::9' );
		swfl_same( true, is_wp_error( SWFL_Request_Guard::check_rate_limit( new WP_REST_Request() ) ), 'the third address of the /64 is refused' );
		swfl_test_visitor( '2001:db8:1:3::1' );
		swfl_same( true, SWFL_Request_Guard::check_rate_limit( new WP_REST_Request() ), 'the next /64 is separate' );
		$counters = array_keys( $GLOBALS['swfl_t']['rows'] );
		swfl_same( 2, count( $counters ), 'two counters, not four' );
	}
);

swfl_test(
	'rate limit: WordPress asking again for the same request counts once',
	function () {
		$GLOBALS['swfl_t']['options']['swfl_rest_rate_limit'] = 2;
		swfl_test_visitor( '203.0.113.7' );
		$request = new WP_REST_Request();
		swfl_same( true, SWFL_Request_Guard::check_rate_limit( $request ) );
		swfl_same( true, SWFL_Request_Guard::check_rate_limit( $request ) );
		swfl_same( true, SWFL_Request_Guard::check_rate_limit( $request ) );
		swfl_same( true, SWFL_Request_Guard::check_rate_limit( new WP_REST_Request() ) );
		swfl_same( true, is_wp_error( SWFL_Request_Guard::check_rate_limit( new WP_REST_Request() ) ) );
	}
);

swfl_test(
	'rate limit: forwarded headers are ignored by default (Issue 115)',
	function () {
		$GLOBALS['swfl_t']['options']['swfl_rest_rate_limit'] = 1;
		swfl_test_visitor( '10.0.0.5', '198.51.100.1' );
		swfl_same( true, SWFL_Request_Guard::check_rate_limit( new WP_REST_Request() ) );
		swfl_test_visitor( '10.0.0.5', '198.51.100.2' );
		swfl_same( true, is_wp_error( SWFL_Request_Guard::check_rate_limit( new WP_REST_Request() ) ), 'a header must not buy a new budget' );
	}
);

swfl_test(
	'rate limit: with trusted proxies the visitor comes from X-Forwarded-For, spoofed hops do not help',
	function () {
		$GLOBALS['swfl_t']['options']['swfl_rest_rate_limit'] = 1;
		$GLOBALS['swfl_t']['options']['swfl_trusted_proxies'] = "10.0.0.0/8\n# cloudflare\n173.245.48.0/20";
		swfl_test_visitor( '10.0.0.5', '198.51.100.1' );
		swfl_same( true, SWFL_Request_Guard::check_rate_limit( new WP_REST_Request() ) );
		swfl_test_visitor( '10.0.0.5', '198.51.100.2' );
		swfl_same( true, SWFL_Request_Guard::check_rate_limit( new WP_REST_Request() ), 'another visitor behind the proxy' );
		swfl_test_visitor( '10.0.0.5', '6.6.6.6, 198.51.100.2' );
		swfl_same( true, is_wp_error( SWFL_Request_Guard::check_rate_limit( new WP_REST_Request() ) ), 'a forged left hop is ignored' );
		swfl_test_visitor( '198.51.100.99', '1.2.3.4' );
		swfl_same( true, SWFL_Request_Guard::check_rate_limit( new WP_REST_Request() ), 'an untrusted peer: the header is ignored' );
		swfl_test_visitor( '198.51.100.99', '1.2.3.5' );
		swfl_same( true, is_wp_error( SWFL_Request_Guard::check_rate_limit( new WP_REST_Request() ) ) );
	}
);

swfl_test(
	'rate limit 0: unlimited, but the request still enters the budget state',
	function () {
		$GLOBALS['swfl_t']['options']['swfl_rest_rate_limit'] = 0;
		$GLOBALS['swfl_t']['options']['swfl_upstream_budget'] = 1;
		swfl_test_visitor( '203.0.113.7' );
		for ( $i = 0; $i < 5; $i++ ) {
			swfl_same( true, SWFL_Request_Guard::check_rate_limit( new WP_REST_Request() ) );
		}
		swfl_same( true, SWFL_Upstream_Budget::acquire() );
		swfl_same( true, SWFL_Upstream_Budget::is_budget_error( SWFL_Upstream_Budget::acquire() ), 'the guard set the flag' );
	}
);

swfl_test(
	'rate limit: an administrator counts for the limiter but not for the budget',
	function () {
		$GLOBALS['swfl_t']['options']['swfl_rest_rate_limit'] = 1;
		$GLOBALS['swfl_t']['options']['swfl_upstream_budget'] = 1;
		$GLOBALS['swfl_t']['user_can']                         = true;
		swfl_test_visitor( '203.0.113.7' );
		SWFL_Request_Guard::check_rate_limit( new WP_REST_Request() );
		swfl_same( true, is_wp_error( SWFL_Request_Guard::check_rate_limit( new WP_REST_Request() ) ) );
		swfl_same( true, SWFL_Upstream_Budget::acquire() );
		swfl_same( true, SWFL_Upstream_Budget::acquire() );
	}
);

swfl_test(
	'retry-after: added to the 429 and 503 answers of the plugin only',
	function () {
		$make = function ( $code, $retry ) {
			return new WP_REST_Response(
				array(
					'code'    => $code,
					'message' => 'x',
					'data'    => array( 'status' => 429, 'retry_after' => $retry ),
				),
				429
			);
		};
		$response = SWFL_Request_Guard::add_retry_after( $make( 'swfl_rate_limited', 37 ) );
		swfl_same( '37', $response->headers['Retry-After'] );
		swfl_same( 'no-store', $response->headers['Cache-Control'] );
		swfl_same( '9', SWFL_Request_Guard::add_retry_after( $make( 'swfl_budget_exceeded', 9 ) )->headers['Retry-After'] );
		swfl_same( array(), SWFL_Request_Guard::add_retry_after( $make( 'rest_forbidden', 9 ) )->headers );
		swfl_same( array(), SWFL_Request_Guard::add_retry_after( new WP_REST_Response( array( 'games' => array() ), 200 ) )->headers );
	}
);

swfl_test(
	'rate limit: the counter of a visitor lives for the rest of the minute only',
	function () {
		$GLOBALS['swfl_t']['options']['swfl_rest_rate_limit'] = 5;
		$_SERVER['REMOTE_ADDR']                               = '198.51.100.20';
		SWFL_Request_Guard::check_rate_limit( new WP_REST_Request() );
		$rows = array_keys( $GLOBALS['swfl_t']['rows'] );
		swfl_same( 1, count( $rows ) );
		swfl_same( 1, preg_match( '/^swfl_count_(\d{10})_rl_[0-9a-f]{32}$/', $rows[0], $match ), $rows[0] );
		$left = (int) $match[1] - time();
		swfl_same( true, $left >= 1 && $left <= MINUTE_IN_SECONDS, 'the window ends in ' . $left . ' s' );
	}
);
