<?php
/**
 * Tests of SWFL_Warmup against the cache of SWFL_Client: a failed refresh keeps what is cached, a good one heals a marker.
 *
 * @package SWFL
 */

/**
 * Cache key of a free-API request.
 *
 * @param string $path Endpoint with query string.
 * @return string Transient name.
 */
function swfl_test_warmup_key( $path ) {
	return 'swfl_' . hash( 'sha256', 'https://wc.swissunihockey.ch/' . $path );
}

/**
 * Configure a club and a season, and script the answers of one complete warm-up run.
 *
 * SWFL_Warmup::run() asks for the club games, the club topscorers, the teams, the games of the one team, and the
 * ranking and the topscorers of the league group that team plays in.
 *
 * @return void
 */
function swfl_test_warmup_start() {
	$GLOBALS['swfl_t']['options']['swfl_club_number']   = 1234;
	$GLOBALS['swfl_t']['options']['swfl_actual_season'] = 2026;
	swfl_test_http( ...swfl_test_warmup_answers() );
}

/**
 * The scripted answers of one complete run.
 *
 * @return array[] Answers for swfl_test_http().
 */
function swfl_test_warmup_answers() {
	$table      = array(
		'code' => 200,
		'body' => '{"data":{"regions":[{"rows":[{"cells":[{"text":["a"]}]}]}]}}',
	);
	$teams      = array(
		'code' => 200,
		'body' => '{"entries":[{"text":"T1","set_in_context":{"team_id":77}}]}',
	);
	$team_games = array(
		'code' => 200,
		'body' => '{"data":{"tabs":[{"link":{"ids":[2026,2,11,"Gruppe 1"]}}],"regions":[{"rows":[{"cells":[{"text":["a"]}]}]}]}}',
	);

	return array( $table, $table, $teams, $team_games, $table, $table );
}

/**
 * Failed answers for every request of a run.
 *
 * @return array[] Answers for swfl_test_http().
 */
function swfl_test_warmup_failures() {
	return array_fill( 0, 6, array( 'code' => 500, 'body' => '' ) );
}

/**
 * The cache rows (value, expiry and lifetime) by name, without the lock.
 *
 * @return string[] Serialized rows.
 */
function swfl_test_cache_rows() {
	$rows = array();
	foreach ( $GLOBALS['swfl_t']['transients'] as $key => $row ) {
		if ( SWFL_Warmup::LOCK_TRANSIENT !== $key ) {
			$rows[ $key ] = serialize( $row );
		}
	}
	ksort( $rows );

	return $rows;
}

/**
 * Names written with set_transient(), without the lock.
 *
 * @return string[] Transient names.
 */
function swfl_test_cache_writes() {
	return array_values( array_diff( $GLOBALS['swfl_t']['writes'], array( SWFL_Warmup::LOCK_TRANSIENT ) ) );
}

/**
 * Let every cached payload run past its fresh time, as if the cache lifetime had elapsed.
 *
 * @return void
 */
function swfl_test_expire_envelopes() {
	foreach ( $GLOBALS['swfl_t']['transients'] as $key => $row ) {
		if ( is_array( $row[0] ) && isset( $row[0]['fresh_until'] ) ) {
			$GLOBALS['swfl_t']['transients'][ $key ][0]['fresh_until'] = time() - 10;
		}
	}
}

/**
 * Forget the requests and writes so far, then script new answers.
 *
 * @param array[] $answers Answers for swfl_test_http().
 * @return void
 */
function swfl_test_warmup_again( $answers ) {
	$GLOBALS['swfl_t']['http_calls'] = array();
	$GLOBALS['swfl_t']['http_queue'] = array();
	$GLOBALS['swfl_t']['writes']     = array();
	swfl_test_http( ...$answers );
}

swfl_test(
	'warm-up: a cold run caches the club, team and league group requests under the keys the shortcodes use',
	function () {
		swfl_test_warmup_start();
		swfl_same( 6, SWFL_Warmup::run() );
		swfl_same( 6, count( swfl_test_cache_rows() ) );
		foreach ( array( 'games?mode=club&club_id=1234&season=2026', 'rankings?season=2026&league=2&game_class=11&group=Gruppe%201&view=full', 'topscorers/su?season=2026&league=2&game_class=11' ) as $path ) {
			swfl_same( true, isset( $GLOBALS['swfl_t']['transients'][ swfl_test_warmup_key( $path ) ] ), $path );
		}
		swfl_same( false, get_transient( SWFL_Warmup::LOCK_TRANSIENT ), 'the lock is released after a run' );
	}
);

swfl_test(
	'warm-up: a run in which every request fails leaves fresh and expired entries as they are',
	function () {
		swfl_test_warmup_start();
		SWFL_Warmup::run();

		foreach ( array( 'fresh', 'expired' ) as $state ) {
			if ( 'expired' === $state ) {
				swfl_test_expire_envelopes();
			}
			$before = swfl_test_cache_rows();
			swfl_test_warmup_again( swfl_test_warmup_failures() );
			SWFL_Warmup::run();
			swfl_same( true, count( $GLOBALS['swfl_t']['http_calls'] ) > 0, $state . ': the run asked the API' );
			swfl_same( array(), swfl_test_cache_writes(), $state . ': nothing was written to a cache key' );
			swfl_same( $before, swfl_test_cache_rows(), $state . ': values and lifetimes are unchanged' );
		}
	}
);

swfl_test(
	'warm-up: when only the ranking and topscorer requests of the league group fail, their entries stay',
	function () {
		swfl_test_warmup_start();
		SWFL_Warmup::run();
		$keys = array(
			swfl_test_warmup_key( 'rankings?season=2026&league=2&game_class=11&group=Gruppe%201&view=full' ),
			swfl_test_warmup_key( 'topscorers/su?season=2026&league=2&game_class=11' ),
		);

		foreach ( array( 'fresh', 'expired' ) as $state ) {
			if ( 'expired' === $state ) {
				swfl_test_expire_envelopes();
			}
			$before = array(
				$GLOBALS['swfl_t']['transients'][ $keys[0] ],
				$GLOBALS['swfl_t']['transients'][ $keys[1] ],
			);
			$answers = swfl_test_warmup_answers();
			$failure = array(
				'code' => 500,
				'body' => '',
			);
			// Club games, club topscorers, teams and team games succeed; ranking and topscorers fail.
			swfl_test_warmup_again( array( $answers[0], $answers[1], $answers[2], $answers[3], $failure, $failure ) );
			SWFL_Warmup::run();
			swfl_same( false, in_array( $keys[0], $GLOBALS['swfl_t']['writes'], true ) || in_array( $keys[1], $GLOBALS['swfl_t']['writes'], true ), $state . ': no write to the group keys' );
			swfl_same( $before, array( $GLOBALS['swfl_t']['transients'][ $keys[0] ], $GLOBALS['swfl_t']['transients'][ $keys[1] ] ), $state . ': the group entries are unchanged' );
		}
	}
);

swfl_test(
	'warm-up: a good run replaces a failure marker, and a held lock blocks a run',
	function () {
		swfl_test_warmup_start();
		$key = swfl_test_warmup_key( 'games?mode=club&club_id=1234&season=2026' );
		// What a visitor's failed request leaves behind.
		set_transient( $key, SWFL_Cache_Entry::wrap_error( new WP_Error( 'api_error', 'status 500', array( 'upstream_status' => 500 ) ) ), 60 );
		SWFL_Warmup::run();
		swfl_same( true, isset( $GLOBALS['swfl_t']['transients'][ $key ][0]['data'] ), 'the forced refresh stored the data' );
		swfl_same( false, isset( $GLOBALS['swfl_t']['transients'][ $key ][0]['error'] ), 'and the marker is gone' );

		set_transient( SWFL_Warmup::LOCK_TRANSIENT, 1, 600 );
		swfl_test_warmup_again( swfl_test_warmup_answers() );
		swfl_same( 0, SWFL_Warmup::run(), 'a held lock blocks the run' );
		swfl_same( array(), $GLOBALS['swfl_t']['http_calls'] );
	}
);
