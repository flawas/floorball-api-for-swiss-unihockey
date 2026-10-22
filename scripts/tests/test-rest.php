<?php
/**
 * Tests of the REST layer: route arguments (Issue 113) and the 503 answers of the budget (Issue 114).
 *
 * @package SWFL
 */

/**
 * Records the routes the plugin registers.
 *
 * @param string $namespace Namespace.
 * @param string $route     Route.
 * @param array  $args      Route arguments.
 */
function register_rest_route( $namespace, $route, $args ) {
	$GLOBALS['swfl_t']['routes'][ $route ] = $args;
}

swfl_test(
	'routes: every argument of every public route is validated and sanitized',
	function () {
		$GLOBALS['swfl_t']['routes'] = array();
		SWFL_Widgets::register_routes();
		SWFL_Rest::register_routes();
		$required = array(
			'/team-games'   => array( 'team_id' ),
			'/league-games' => array( 'game_class', 'league' ),
			'/rankings'     => array( 'league', 'game_class' ),
		);
		$expected = array(
			'/team-games'   => array( 'team_id', 'season', 'page_size' ),
			'/league-games' => array( 'game_class', 'league', 'season', 'group', 'round' ),
			'/calendar'     => array( 'team_id', 'club_id', 'season', 'league', 'game_class', 'group' ),
			'/games'        => array( 'team_id', 'club_id', 'season', 'league', 'game_class', 'group' ),
			'/next-game'    => array( 'team_id', 'club_id', 'season', 'league', 'game_class', 'group' ),
			'/last-result'  => array( 'team_id', 'club_id', 'season', 'league', 'game_class', 'group' ),
			'/rankings'     => array( 'league', 'game_class', 'season', 'group' ),
			'/topscorers'   => array( 'club_id', 'season', 'league', 'game_class' ),
		);
		swfl_same( array_keys( $expected ), array_keys( array_intersect_key( $GLOBALS['swfl_t']['routes'], $expected ) ), 'all routes are registered' );
		foreach ( $expected as $route => $names ) {
			$route_args = $GLOBALS['swfl_t']['routes'][ $route ];
			swfl_same( array( 'SWFL_Request_Guard', 'check_rate_limit' ), $route_args['permission_callback'], $route );
			swfl_same( $names, array_keys( $route_args['args'] ), $route );
			// Every argument must be exactly the definition of its helper: a season checked as a plain ID would accept 1 or 99999.
			foreach ( $route_args['args'] as $name => $definition ) {
				if ( 'season' === $name ) {
					$want = SWFL_Request_Guard::season_arg();
				} elseif ( 'group' === $name ) {
					$want = SWFL_Request_Guard::group_arg();
				} else {
					$want = SWFL_Request_Guard::int_arg( isset( $required[ $route ] ) && in_array( $name, $required[ $route ], true ) );
				}
				swfl_same( $want, $definition, $route . ' ' . $name );
			}
		}
	}
);

/**
 * Emulates how WordPress core handles a REST request: validate every argument, then sanitize, then the permission
 * callback, then the route callback. Enough to show that a bad value never reaches the API.
 *
 * @param string $route  Route, e.g. `/games`.
 * @param array  $params Query parameters.
 * @return mixed WP_Error with status 400 for an invalid parameter, otherwise what the route callback returns.
 */
function swfl_test_dispatch( $route, $params ) {
	$definition = $GLOBALS['swfl_t']['routes'][ $route ];
	foreach ( $definition['args'] as $name => $arg ) {
		if ( ! array_key_exists( $name, $params ) ) {
			if ( ! empty( $arg['required'] ) ) {
				return new WP_Error( 'rest_missing_callback_param', $name, array( 'status' => 400 ) );
			}
			continue;
		}
		$valid = call_user_func( $arg['validate_callback'], $params[ $name ] );
		if ( true !== $valid ) {
			return new WP_Error( 'rest_invalid_param', $name, array( 'status' => 400 ) );
		}
		$params[ $name ] = call_user_func( $arg['sanitize_callback'], $params[ $name ] );
	}
	$request = new WP_REST_Request( $params );
	$allowed = call_user_func( $definition['permission_callback'], $request );
	return true === $allowed ? call_user_func( $definition['callback'], $request ) : $allowed;
}

swfl_test(
	'routes: the requests of Issue 113 are answered with 400 rest_invalid_param and never reach the API',
	function () {
		$GLOBALS['swfl_t']['year']   = '2026';
		$GLOBALS['swfl_t']['routes'] = array();
		SWFL_Widgets::register_routes();
		SWFL_Rest::register_routes();
		$bad = array(
			'/games'        => array( array( 'season' => '1' ), array( 'season' => '99999' ), array( 'team_id' => '-5' ), array( 'team_id' => 'abc' ), array( 'group' => '<b>x</b>' ) ),
			'/team-games'   => array( array( 'team_id' => '5', 'season' => '1' ), array( 'team_id' => '5', 'page_size' => '-1' ) ),
			'/league-games' => array( array( 'league' => '2', 'game_class' => '11', 'season' => '99999' ), array( 'league' => '2', 'game_class' => '11', 'round' => '1e3' ) ),
			'/calendar'     => array( array( 'club_id' => '0x10' ), array( 'club_id' => '5', 'season' => '1995' ) ),
			'/rankings'     => array( array( 'league' => '2', 'game_class' => '11', 'season' => '1' ) ),
			'/topscorers'   => array( array( 'season' => '99999' ), array( 'club_id' => '5.0' ) ),
			'/next-game'    => array( array( 'team_id' => ' 5' ) ),
			'/last-result'  => array( array( 'league' => array( 2 ) ) ),
		);
		foreach ( $bad as $route => $cases ) {
			foreach ( $cases as $params ) {
				$result = swfl_test_dispatch( $route, $params );
				$label  = $route . '?' . http_build_query( $params );
				swfl_same( true, is_wp_error( $result ), $label );
				swfl_same( 'rest_invalid_param', $result->get_error_code(), $label );
			}
		}
		swfl_same( array(), $GLOBALS['swfl_t']['http_calls'], 'no upstream request' );

		$ok = swfl_test_dispatch( '/games', array( 'team_id' => '5', 'season' => '2025' ) );
		swfl_same( false, is_wp_error( $ok ), 'a valid request still works' );
		swfl_same( 1, count( $GLOBALS['swfl_t']['http_calls'] ) );
	}
);

swfl_test(
	'routes: the values the plugin front end sends are valid (page_size 0, season 0, group empty)',
	function () {
		$GLOBALS['swfl_t']['year'] = '2026';
		swfl_same( true, SWFL_Request_Guard::validate_optional_id( '0' ), 'page_size=0' );
		swfl_same( true, SWFL_Request_Guard::validate_season( '0' ), 'season=0' );
		swfl_same( true, SWFL_Request_Guard::validate_group( '' ), 'group=' );
		swfl_same( true, SWFL_Request_Guard::validate_optional_id( '116970' ), 'round from the API' );
		swfl_same( true, SWFL_Request_Guard::validate_required_id( '429626' ), 'team_id from the select' );
	}
);

swfl_test(
	'rest: a request the budget refused is a 503 for every JSON route, an expired copy is a short-lived 200',
	function () {
		$GLOBALS['swfl_t']['options']['swfl_upstream_budget'] = 1;
		SWFL_Upstream_Budget::enter_public_request();
		SWFL_Counter::take( 'upstream', 1, 60 );
		foreach ( array( 'rest_games', 'rest_next_game', 'rest_last_result', 'rest_rankings', 'rest_topscorers' ) as $callback ) {
			$params = array(
				'team_id'    => 5,
				'league'     => 2,
				'game_class' => 11,
				'season'     => 2025,
			);
			$result = SWFL_Rest::$callback( new WP_REST_Request( $params ) );
			swfl_same( true, is_wp_error( $result ), $callback );
			swfl_same( 'swfl_budget_exceeded', $result->get_error_code(), $callback );
			swfl_same( 503, $result->get_error_data()['status'], $callback );
		}
	}
);

swfl_test(
	'rest: responses are cached for an hour, for a minute when an expired copy was served',
	function () {
		$response = SWFL_Rest::rest_games( new WP_REST_Request( array( 'team_id' => 5, 'season' => 2025 ) ) );
		swfl_same( 'public, max-age=3600', $response->headers['Cache-Control'] );

		// Age the stored first page, use up the budget and ask again.
		foreach ( array_keys( $GLOBALS['swfl_t']['transients'] ) as $key ) {
			$GLOBALS['swfl_t']['transients'][ $key ][0]['fresh_until'] = time() - 5;
		}
		$GLOBALS['swfl_t']['options']['swfl_upstream_budget'] = 1;
		SWFL_Upstream_Budget::enter_public_request();
		SWFL_Counter::take( 'upstream', 1, 60 );
		$stale = SWFL_Rest::rest_games( new WP_REST_Request( array( 'team_id' => 5, 'season' => 2025 ) ) );
		swfl_same( 'public, max-age=60', $stale->headers['Cache-Control'] );
		swfl_same( $response->data, $stale->data, 'the same games, from the expired copy' );
	}
);

swfl_test(
	'rest: an administrator still sees the upstream detail, visitors a neutral 502',
	function () {
		swfl_test_http( array( 'code' => 404, 'body' => '{"error":"Team not found"}' ) );
		$visitor = SWFL_Rest::rest_games( new WP_REST_Request( array( 'team_id' => 9999999, 'season' => 2025 ) ) );
		swfl_same( 'api_error', $visitor->get_error_code() );
		swfl_same( 502, $visitor->get_error_data()['status'] );
		swfl_same( 'The data could not be loaded.', $visitor->get_error_message() );

		$GLOBALS['swfl_t']['user_can'] = true;
		$admin = SWFL_Rest::rest_games( new WP_REST_Request( array( 'team_id' => 9999999, 'season' => 2025 ) ) );
		swfl_same( 'API returned status code 404', $admin->get_error_message(), 'the cached marker rebuilds the original error' );
	}
);

swfl_test(
	'rest: the HTML routes and the calendar answer 503 instead of a notice or a partial list',
	function () {
		$GLOBALS['swfl_t']['options']['swfl_upstream_budget'] = 1;
		SWFL_Upstream_Budget::enter_public_request();
		SWFL_Counter::take( 'upstream', 1, 60 );

		$team = SWFL_Widgets::rest_team_games( new WP_REST_Request( array( 'team_id' => 5, 'season' => 2025 ) ) );
		swfl_same( 'swfl_budget_exceeded', $team->get_error_code() );
		$league = SWFL_Widgets::rest_league_games( new WP_REST_Request( array( 'game_class' => 11, 'league' => 2, 'season' => 2025 ) ) );
		swfl_same( 'swfl_budget_exceeded', $league->get_error_code() );
		$calendar = SWFL_Calendar::rest_calendar( new WP_REST_Request( array( 'team_id' => 5, 'season' => 2025 ) ) );
		swfl_same( 'swfl_budget_exceeded', $calendar->get_error_code() );
		swfl_same( 503, $calendar->get_error_data()['status'] );

		// Without a refusal the HTML route returns the markup.
		SWFL_Upstream_Budget::reset();
		$GLOBALS['swfl_t']['options']['swfl_upstream_budget'] = 0;
		$ok = SWFL_Widgets::rest_team_games( new WP_REST_Request( array( 'team_id' => 5, 'season' => 2025 ) ) );
		swfl_same( true, isset( $ok['html'] ) && false !== strpos( $ok['html'], 'sfa-widget' ) );
	}
);
