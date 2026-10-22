<?php
/**
 * Tests of SWFL_Cache_Entry: envelopes, error markers and the error classes.
 *
 * @package SWFL
 */

swfl_test(
	'cache entry: classify (permanent, transient, never)',
	function () {
		$make  = function ( $code, $status = null ) {
			return new WP_Error( $code, 'message', null === $status ? '' : array( 'upstream_status' => $status ) );
		};
		$cases = array(
			array( $make( 'api_error', 404 ), 'permanent' ),
			array( $make( 'api_error', 400 ), 'permanent' ),
			array( $make( 'api_error', 410 ), 'permanent' ),
			array( $make( 'api_error', 408 ), 'transient' ),
			array( $make( 'api_error', 429 ), 'transient' ),
			array( $make( 'api_error', 500 ), 'transient' ),
			array( $make( 'api_error', 503 ), 'transient' ),
			array( $make( 'api_error', 204 ), 'transient' ),
			array( $make( 'api_error' ), 'transient' ),
			array( $make( 'api_error', 401 ), 'never' ),
			array( $make( 'api_error', 403 ), 'never' ),
			array( $make( 'swfl_endpoint_unavailable' ), 'permanent' ),
			array( $make( 'swfl_partner_auth' ), 'never' ),
			array( $make( 'swfl_partner_credentials' ), 'never' ),
			array( $make( 'swfl_budget_exceeded' ), 'never' ),
			array( $make( 'http_request_failed' ), 'transient' ),
			array( $make( 'json_error' ), 'transient' ),
		);
		foreach ( $cases as $index => $case ) {
			swfl_same( $case[1], SWFL_Cache_Entry::classify( $case[0] ), 'case ' . $index . ' ' . $case[0]->get_error_code() );
		}
	}
);

swfl_test(
	'cache entry: error lifetime and the swfl_error_cache_ttl filter',
	function () {
		$permanent = new WP_Error( 'api_error', 'm', array( 'upstream_status' => 404 ) );
		$transient = new WP_Error( 'http_request_failed', 'm' );
		$auth      = new WP_Error( 'swfl_partner_auth', 'm' );
		swfl_same( 300, SWFL_Cache_Entry::get_error_ttl( $permanent, 'url' ) );
		swfl_same( 60, SWFL_Cache_Entry::get_error_ttl( $transient, 'url' ), 'transient failures are capped at 60 s' );
		swfl_same( 0, SWFL_Cache_Entry::get_error_ttl( $auth, 'url' ) );

		add_filter(
			'swfl_error_cache_ttl',
			function () {
				return 3600;
			}
		);
		swfl_same( 3600, SWFL_Cache_Entry::get_error_ttl( $permanent, 'url' ) );
		swfl_same( 60, SWFL_Cache_Entry::get_error_ttl( $transient, 'url' ), 'the cap holds after the filter' );
		swfl_same( 0, SWFL_Cache_Entry::get_error_ttl( $auth, 'url' ), 'auth failures are never cached' );

		$GLOBALS['swfl_t']['filters'] = array();
		add_filter(
			'swfl_error_cache_ttl',
			function () {
				return 10;
			}
		);
		swfl_same( 10, SWFL_Cache_Entry::get_error_ttl( $transient, 'url' ) );

		$GLOBALS['swfl_t']['filters'] = array();
		add_filter(
			'swfl_error_cache_ttl',
			function () {
				return -5;
			}
		);
		swfl_same( 0, SWFL_Cache_Entry::get_error_ttl( $permanent, 'url' ), 'zero or negative turns it off' );
	}
);

swfl_test(
	'cache entry: wrap and read, expired payload, legacy payload, unknown version',
	function () {
		$payload = array( 'data' => array( 'regions' => array() ) );
		$entry   = SWFL_Cache_Entry::wrap( $payload, 100, 1000 );
		swfl_same(
			array(
				'type'  => 'data',
				'fresh' => true,
				'data'  => $payload,
			),
			SWFL_Cache_Entry::read( $entry, 1099 )
		);
		swfl_same( false, SWFL_Cache_Entry::read( $entry, 1100 )['fresh'], 'fresh_until is exclusive' );
		swfl_same( $payload, SWFL_Cache_Entry::read( $entry, 9000 )['data'], 'an expired entry still carries the payload' );
		swfl_same( null, SWFL_Cache_Entry::read( false ), 'a miss' );
		swfl_same(
			array(
				'type'  => 'data',
				'fresh' => true,
				'data'  => $payload,
			),
			SWFL_Cache_Entry::read( $payload ),
			'a raw payload written by 2.1.2 is fresh'
		);
		swfl_same( 'data', SWFL_Cache_Entry::read( array( 'swfl_entry' => 2 ) )['type'], 'an unknown version is treated as a raw payload' );
		swfl_same( false, SWFL_Cache_Entry::read( array( 'swfl_entry' => 1 ), 10 )['fresh'], 'an envelope without fresh_until is expired' );
	}
);

swfl_test(
	'cache entry: an error survives the round trip, long text is cut, junk data is dropped',
	function () {
		$error = new WP_Error(
			'api_error',
			'API returned status code 404',
			array(
				'api_message'     => str_repeat( 'é', 500 ),
				'upstream_status' => 404,
				'junk'            => array( 1 ),
			)
		);
		$read  = SWFL_Cache_Entry::read( SWFL_Cache_Entry::wrap_error( $error ) );
		swfl_same( 'error', $read['type'] );
		$back = SWFL_Cache_Entry::to_error( $read['error'] );
		swfl_same( 'api_error', $back->get_error_code() );
		swfl_same( 'API returned status code 404', $back->get_error_message() );
		swfl_same( 404, $back->get_error_data()['upstream_status'] );
		swfl_same( 200, mb_strlen( $back->get_error_data()['api_message'] ) );
		swfl_same( false, isset( $back->get_error_data()['junk'] ) );
		$plain = SWFL_Cache_Entry::to_error( SWFL_Cache_Entry::wrap_error( new WP_Error( 'swfl_endpoint_unavailable', 'x' ) )['error'] );
		swfl_same( null, $plain->get_error_data(), 'no data stays no data' );
	}
);

swfl_test(
	'cache entry: an answer without rows is recognised (empty table, empty regions, empty list)',
	function () {
		$row = array( 'cells' => array( array( 'text' => array( 'a' ) ) ) );
		$yes = array(
			array( 'data' => array( 'regions' => array() ) ),
			array( 'data' => array( 'regions' => array( array( 'rows' => array() ) ) ) ),
			array( 'data' => array( 'regions' => array( array( 'rows' => array() ), array() ) ) ),
			array( 'entries' => array() ),
		);
		$no  = array(
			array( 'data' => array( 'regions' => array( array( 'rows' => array( $row ) ) ) ) ),
			array( 'data' => array( 'regions' => array( array( 'rows' => array() ), array( 'rows' => array( $row ) ) ) ) ),
			array( 'entries' => array( array( 'text' => 'Gruppe 1' ) ) ),
			array( 'data' => array( 'title' => 'no table at all' ) ),
			array( 'data' => 'text' ),
			array( 'entries' => 'text' ),
			array(),
			'text',
			null,
			false,
		);
		foreach ( $yes as $index => $payload ) {
			swfl_same( true, SWFL_Cache_Entry::is_empty_payload( $payload ), 'empty case ' . $index );
		}
		foreach ( $no as $index => $payload ) {
			swfl_same( false, SWFL_Cache_Entry::is_empty_payload( $payload ), 'filled case ' . $index );
		}
	}
);

swfl_test(
	'cache entry: the lifetime of an empty answer follows swfl_error_cache_ttl, 0 means cached like any other answer',
	function () {
		swfl_same( 300, SWFL_Cache_Entry::get_empty_ttl( 'url' ), 'default: like a permanent failure' );
		swfl_same( 'permanent', SWFL_Cache_Entry::classify( new WP_Error( SWFL_Cache_Entry::EMPTY_CODE, 'm' ) ) );
		add_filter(
			'swfl_error_cache_ttl',
			function ( $ttl, $error ) {
				return SWFL_Cache_Entry::EMPTY_CODE === $error->get_error_code() ? 120 : $ttl;
			},
			10,
			2
		);
		swfl_same( 120, SWFL_Cache_Entry::get_empty_ttl( 'url' ), 'the filter sees the pseudo error' );
		$GLOBALS['swfl_t']['filters'] = array();
		add_filter(
			'swfl_error_cache_ttl',
			function () {
				return 0;
			}
		);
		swfl_same( 0, SWFL_Cache_Entry::get_empty_ttl( 'url' ) );
	}
);

swfl_test(
	'cache entry: swfl_stale_ttl sets how long an expired copy is kept, 0 and negative values keep none',
	function () {
		swfl_same( 3600, SWFL_Cache_Entry::get_stale_ttl( 'url' ) );
		foreach ( array( 0 => 0, -5 => 0, 86400 => 86400 ) as $filtered => $expected ) {
			$GLOBALS['swfl_t']['filters'] = array();
			add_filter(
				'swfl_stale_ttl',
				function () use ( $filtered ) {
					return $filtered;
				}
			);
			swfl_same( $expected, SWFL_Cache_Entry::get_stale_ttl( 'url' ), 'filtered to ' . $filtered );
		}
	}
);
