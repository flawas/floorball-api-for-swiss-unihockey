<?php
/**
 * Tests of SWFL_Client: the cache envelope, the short-lived error markers and the upstream budget gate.
 *
 * @package SWFL
 */

/**
 * Cache key of a free-API request, as built by SWFL_Client::fetch_data().
 *
 * @param string $path Endpoint with query string, e.g. `games?team_id=5`.
 * @param string $base Base URL of the API source.
 * @return string Transient name.
 */
function swfl_test_key( $path, $base = 'https://wc.swissunihockey.ch/' ) {
	return 'swfl_' . hash( 'sha256', $base . $path );
}

swfl_test(
	'client: a success is stored as an envelope that outlives its fresh time by an hour',
	function () {
		$client = new SWFL_Client();
		$first  = $client->fetch_data( 'games', array( 'team_id' => 5 ) );
		$second = $client->fetch_data( 'games', array( 'team_id' => 5 ) );
		swfl_same( $first, $second );
		swfl_same( 1, count( $GLOBALS['swfl_t']['http_calls'] ), 'the second call is a cache hit' );
		$row = $GLOBALS['swfl_t']['transients'][ swfl_test_key( 'games?team_id=5' ) ];
		swfl_same( 1, $row[0]['swfl_entry'] );
		swfl_same( 3600 + 3600, $row[2], 'games lifetime + stale retention' );
	}
);

swfl_test(
	'client: an unknown ID is answered from the cache the second time (Issue 112)',
	function () {
		swfl_test_http( array( 'code' => 404, 'body' => '{"error":"Team not found"}' ) );
		$client = new SWFL_Client();
		$first  = $client->fetch_data( 'games', array( 'team_id' => 9999999 ) );
		$second = $client->fetch_data( 'games', array( 'team_id' => 9999999 ) );
		swfl_same( 1, count( $GLOBALS['swfl_t']['http_calls'] ), 'no second upstream request' );
		swfl_same( $first->get_error_code(), $second->get_error_code() );
		swfl_same( $first->get_error_message(), $second->get_error_message() );
		swfl_same( $first->get_error_data(), $second->get_error_data(), 'api_message and status survive' );
		swfl_same( 'Team not found', $second->get_error_data()['api_message'] );
		swfl_same( 300, $GLOBALS['swfl_t']['transients'][ swfl_test_key( 'games?team_id=9999999' ) ][2] );
	}
);

swfl_test(
	'client: failures that pass by themselves are cached for at most 60 seconds',
	function () {
		$client = new SWFL_Client();
		swfl_test_http( array( 'code' => 503, 'body' => '' ) );
		$client->fetch_data( 'games', array( 'team_id' => 1 ) );
		swfl_same( 60, $GLOBALS['swfl_t']['transients'][ swfl_test_key( 'games?team_id=1' ) ][2] );
		swfl_test_http( new WP_Error( 'http_request_failed', 'cURL error 28' ) );
		$client->fetch_data( 'games', array( 'team_id' => 2 ) );
		swfl_same( 60, $GLOBALS['swfl_t']['transients'][ swfl_test_key( 'games?team_id=2' ) ][2] );
		swfl_same( 'http_request_failed', $client->fetch_data( 'games', array( 'team_id' => 2 ) )->get_error_code() );
		swfl_same( 2, count( $GLOBALS['swfl_t']['http_calls'] ) );
	}
);

swfl_test(
	'client: swfl_error_cache_ttl 0 turns the caching of failures off',
	function () {
		add_filter(
			'swfl_error_cache_ttl',
			function () {
				return 0;
			}
		);
		swfl_test_http( array( 'code' => 404, 'body' => '' ), array( 'code' => 404, 'body' => '' ) );
		$client = new SWFL_Client();
		$client->fetch_data( 'games', array( 'team_id' => 3 ) );
		$client->fetch_data( 'games', array( 'team_id' => 3 ) );
		swfl_same( 2, count( $GLOBALS['swfl_t']['http_calls'] ) );
	}
);

swfl_test(
	'client: a gated endpoint of the free API is cached as permanent',
	function () {
		swfl_test_http( array( 'code' => 403, 'body' => '{"error":"Endpoint nicht erlaubt"}' ) );
		$client = new SWFL_Client();
		$client->fetch_data( 'leagues' );
		swfl_same( 'swfl_endpoint_unavailable', $client->fetch_data( 'leagues' )->get_error_code() );
		swfl_same( 1, count( $GLOBALS['swfl_t']['http_calls'] ) );
	}
);

swfl_test(
	'client (partner): missing credentials and a rejected token are never cached, a good token still works',
	function () {
		$GLOBALS['swfl_t']['options']['swfl_api_source'] = 'partner';
		$client = new SWFL_Client();
		swfl_same( 'swfl_partner_credentials', $client->fetch_data( 'games', array( 'team_id' => 1 ) )->get_error_code() );
		swfl_same( array(), $GLOBALS['swfl_t']['transients'] );

		$GLOBALS['swfl_t']['options']['swfl_api_key']    = 'key';
		$GLOBALS['swfl_t']['options']['swfl_api_secret'] = 'secret';
		swfl_test_http( array( 'code' => 401, 'body' => '' ), array( 'code' => 401, 'body' => '' ) );
		$rejected = $client->fetch_data( 'games', array( 'team_id' => 1 ) );
		swfl_same( 'api_error', $rejected->get_error_code() );
		swfl_same( 401, $rejected->get_error_data()['upstream_status'] );
		$partner_key = swfl_test_key( 'games?team_id=1', 'https://office.swissunihockey.ch/api/legacy/' );
		swfl_same( false, isset( $GLOBALS['swfl_t']['transients'][ $partner_key ] ), 'no marker for 401' );

		swfl_test_http( array( 'code' => 200, 'body' => '{"data":{"regions":[]}}' ) );
		swfl_same( false, is_wp_error( $client->fetch_data( 'games', array( 'team_id' => 1 ) ) ), 'the next request works' );
	}
);

swfl_test(
	'client: force_refresh skips and heals a marker, and a failed refresh never writes one',
	function () {
		$client = new SWFL_Client();
		swfl_test_http( array( 'code' => 404, 'body' => '{"error":"x"}' ) );
		$client->fetch_data( 'games', array( 'team_id' => 4 ) );
		swfl_same( false, is_wp_error( $client->fetch_data( 'games', array( 'team_id' => 4 ), null, true ) ), 'forced refresh asks again' );
		swfl_same( 2, count( $GLOBALS['swfl_t']['http_calls'] ) );
		swfl_same( false, is_wp_error( $client->fetch_data( 'games', array( 'team_id' => 4 ) ) ), 'the marker was replaced by data' );

		swfl_test_http( array( 'code' => 500, 'body' => '' ) );
		$client->fetch_data( 'games', array( 'team_id' => 4 ), null, true );
		swfl_same( false, is_wp_error( $client->fetch_data( 'games', array( 'team_id' => 4 ) ) ), 'good data survives a failed forced refresh' );
		swfl_same( 3, count( $GLOBALS['swfl_t']['http_calls'] ) );
	}
);

swfl_test(
	'client: a failed refresh of an expired payload keeps the payload',
	function () {
		$client = new SWFL_Client();
		$client->fetch_data( 'games', array( 'team_id' => 6 ) );
		$key = swfl_test_key( 'games?team_id=6' );
		$GLOBALS['swfl_t']['transients'][ $key ][0]['fresh_until'] = time() - 5;
		swfl_test_http( array( 'code' => 500, 'body' => '' ) );
		swfl_same( true, is_wp_error( $client->fetch_data( 'games', array( 'team_id' => 6 ) ) ), 'the failure is returned' );
		swfl_same( true, isset( $GLOBALS['swfl_t']['transients'][ $key ][0]['data'] ), 'the payload stays for the budget fallback' );
	}
);

swfl_test(
	'client: a raw payload written by 2.1.2 is a fresh hit',
	function () {
		$payload = array( 'data' => array( 'regions' => array() ) );
		set_transient( swfl_test_key( 'games?team_id=7' ), $payload, 3600 );
		swfl_same( $payload, ( new SWFL_Client() )->fetch_data( 'games', array( 'team_id' => 7 ) ) );
		swfl_same( 0, count( $GLOBALS['swfl_t']['http_calls'] ) );
	}
);

swfl_test(
	'client: requests of public REST routes are limited, cache hits and other requests are not',
	function () {
		$GLOBALS['swfl_t']['options']['swfl_upstream_budget'] = 2;
		$client = new SWFL_Client();
		for ( $id = 1; $id <= 4; $id++ ) {
			$client->fetch_data( 'games', array( 'team_id' => $id ) );
		}
		swfl_same( 4, count( $GLOBALS['swfl_t']['http_calls'] ), 'not a public request: unlimited' );

		SWFL_Upstream_Budget::enter_public_request();
		$GLOBALS['swfl_t']['http_calls'] = array();
		$client->fetch_data( 'games', array( 'team_id' => 1 ) );
		$client->fetch_data( 'games', array( 'team_id' => 11 ) );
		$client->fetch_data( 'games', array( 'team_id' => 12 ) );
		$refused = $client->fetch_data( 'games', array( 'team_id' => 13 ) );
		swfl_same( 2, count( $GLOBALS['swfl_t']['http_calls'] ), 'a cache hit costs nothing, two units were used' );
		swfl_same( true, SWFL_Upstream_Budget::is_budget_error( $refused ) );
		swfl_same( 503, $refused->get_error_data()['status'] );
		swfl_same( true, $refused->get_error_data()['retry_after'] >= 1 && $refused->get_error_data()['retry_after'] <= 60 );
		swfl_same( $refused, SWFL_Upstream_Budget::get_denial() );
		swfl_same( false, isset( $GLOBALS['swfl_t']['transients'][ swfl_test_key( 'games?team_id=13' ) ] ), 'a refusal is not cached' );
		swfl_same( false, is_wp_error( $client->fetch_data( 'games', array( 'team_id' => 11 ) ) ), 'cached data is unaffected' );
	}
);

swfl_test(
	'client: an expired copy is served when the budget refuses the refresh',
	function () {
		$client = new SWFL_Client();
		$fresh  = $client->fetch_data( 'games', array( 'team_id' => 21 ) );
		$GLOBALS['swfl_t']['transients'][ swfl_test_key( 'games?team_id=21' ) ][0]['fresh_until'] = time() - 5;
		$GLOBALS['swfl_t']['options']['swfl_upstream_budget'] = 1;
		SWFL_Upstream_Budget::enter_public_request();
		SWFL_Counter::take( 'upstream', 1, 60 );
		$GLOBALS['swfl_t']['http_calls'] = array();

		swfl_same( $fresh, $client->fetch_data( 'games', array( 'team_id' => 21 ) ), 'the stale payload' );
		swfl_same( true, SWFL_Upstream_Budget::was_stale_served() );
		swfl_same( null, SWFL_Upstream_Budget::get_denial(), 'serving a copy is not a refusal' );
		swfl_same( 0, count( $GLOBALS['swfl_t']['http_calls'] ) );
		swfl_same( true, SWFL_Upstream_Budget::is_budget_error( $client->fetch_data( 'games', array( 'team_id' => 22 ) ) ), 'without a copy: 503' );
	}
);

swfl_test(
	'client: an answer without rows is remembered briefly and no expired copy is kept (Issue 112, empty regions)',
	function () {
		$client = new SWFL_Client();
		swfl_test_http(
			array( 'code' => 200, 'body' => '{"data":{"regions":[{"rows":[]}]}}' ),
			array( 'code' => 200, 'body' => '{"entries":[]}' )
		);
		$empty_table = $client->fetch_data( 'games', array( 'round' => 1 ) );
		$empty_list  = $client->fetch_data( 'teams', array( 'club_id' => 1 ) );
		swfl_same( false, is_wp_error( $empty_table ), 'still a success for the caller' );
		swfl_same( false, is_wp_error( $empty_list ) );
		foreach ( array( 'games?round=1', 'teams?club_id=1' ) as $path ) {
			$row = $GLOBALS['swfl_t']['transients'][ swfl_test_key( $path ) ];
			swfl_same( 300, $row[2], $path . ': 5 minutes instead of the endpoint lifetime, and no stale retention' );
			swfl_same( 1, $row[0]['swfl_entry'] );
		}
		$client->fetch_data( 'games', array( 'round' => 1 ) );
		swfl_same( 2, count( $GLOBALS['swfl_t']['http_calls'] ), 'the second request is a cache hit' );
	}
);

swfl_test(
	'client: an empty answer never lives longer than the cache lifetime of its endpoint',
	function () {
		swfl_test_http( array( 'code' => 200, 'body' => '{"data":{"regions":[]}}' ) );
		( new SWFL_Client() )->fetch_data( 'game_events/5' );
		swfl_same( 120, $GLOBALS['swfl_t']['transients'][ swfl_test_key( 'game_events/5' ) ][2], 'game events are fresh for 120 s' );
	}
);

swfl_test(
	'client: swfl_error_cache_ttl 0 caches empty answers like any other answer again',
	function () {
		add_filter(
			'swfl_error_cache_ttl',
			function () {
				return 0;
			}
		);
		swfl_test_http( array( 'code' => 200, 'body' => '{"data":{"regions":[]}}' ) );
		( new SWFL_Client() )->fetch_data( 'games', array( 'round' => 2 ) );
		swfl_same( 3600 + 3600, $GLOBALS['swfl_t']['transients'][ swfl_test_key( 'games?round=2' ) ][2] );
	}
);

swfl_test(
	'client: an answer with rows keeps its lifetime, swfl_stale_ttl 0 keeps no expired copy',
	function () {
		$client = new SWFL_Client();
		$client->fetch_data( 'games', array( 'team_id' => 31 ) );
		swfl_same( 3600 + 3600, $GLOBALS['swfl_t']['transients'][ swfl_test_key( 'games?team_id=31' ) ][2], 'rows: unchanged' );
		add_filter(
			'swfl_stale_ttl',
			function () {
				return 0;
			}
		);
		$client->fetch_data( 'games', array( 'team_id' => 32 ) );
		swfl_same( 3600, $GLOBALS['swfl_t']['transients'][ swfl_test_key( 'games?team_id=32' ) ][2], 'no copy after the fresh time' );
	}
);

swfl_test(
	'client: a failure never replaces data that another request stored while this one waited for the API',
	function () {
		$GLOBALS['swfl_t']['process'] = true;
		$key                          = swfl_test_key( 'games?team_id=7' );
		// While this request waits for the API, another one stores its answer.
		add_filter(
			'swfl_request_timeout',
			function ( $timeout ) use ( $key ) {
				swfl_test_db_write( $key, SWFL_Cache_Entry::wrap( array( 'data' => 'stored by the other request' ), 3600 ), 7200 );
				return $timeout;
			}
		);
		swfl_test_http( array( 'code' => 500, 'body' => '' ) );
		$client = new SWFL_Client();
		swfl_same( 'api_error', $client->fetch_data( 'games', array( 'team_id' => 7 ) )->get_error_code(), 'this request reports its own failure' );
		swfl_same( true, isset( $GLOBALS['swfl_t']['transients'][ $key ][0]['data'] ), 'the other answer is still stored' );
		swfl_same( array( 'data' => 'stored by the other request' ), $client->fetch_data( 'games', array( 'team_id' => 7 ) ), 'and served' );
		swfl_same( 1, count( $GLOBALS['swfl_t']['http_calls'] ) );
	}
);

swfl_test(
	'client: a failure is remembered no longer than the cache lifetime of its endpoint (game_events: 120 s)',
	function () {
		$client = new SWFL_Client();
		swfl_test_http( array( 'code' => 404, 'body' => '{"error":"x"}' ), array( 'code' => 500, 'body' => '' ) );
		$client->fetch_data( 'game_events/5' );
		swfl_same( 120, $GLOBALS['swfl_t']['transients'][ swfl_test_key( 'game_events/5' ) ][2], 'a 404 alone would be remembered for 300 s' );
		$GLOBALS['swfl_t']['options']['swfl_cache_ttl_game_events'] = 30;
		$client->fetch_data( 'game_events/6' );
		swfl_same( 30, $GLOBALS['swfl_t']['transients'][ swfl_test_key( 'game_events/6' ) ][2], 'a 500 alone would be remembered for 60 s' );
	}
);
