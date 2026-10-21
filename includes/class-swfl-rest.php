<?php
/**
 * Read-only JSON REST routes for games, rankings and topscorers.
 *
 * @link       https://flaviowaser.ch
 * @since      2.1.0
 *
 * @package    SWFL
 * @subpackage SWFL_Plugin/includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Exposes the Swiss Unihockey data as JSON for headless and decoupled front-ends.
 *
 * The routes proxy the shared API client, so they use the same transient cache as the shortcodes and
 * are protected by the same rate limit as the widget routes (see SWFL_Request_Guard).
 *
 * @since      2.1.0
 * @package    SWFL
 * @subpackage SWFL_Plugin/includes
 * @author     Flavio Waser <kontakt@flawas.ch>
 */
class SWFL_Rest {

	/**
	 * Max-age of the Cache-Control header, equal to the default cache time of the API client.
	 *
	 * @since 2.1.0
	 * @var   int
	 */
	const CACHE_MAX_AGE = 3600;

	/**
	 * Max-age of the Cache-Control header while an expired copy is served because the upstream budget is used up.
	 *
	 * @since 2.2.0
	 * @var   int
	 */
	const STALE_MAX_AGE = 60;

	/**
	 * Register the JSON routes.
	 *
	 * @since 2.1.0
	 * @return void
	 */
	public static function register_routes() {
		$game_args = array(
			'team_id'    => SWFL_Request_Guard::int_arg(),
			'club_id'    => SWFL_Request_Guard::int_arg(),
			'season'     => SWFL_Request_Guard::season_arg(),
			'league'     => SWFL_Request_Guard::int_arg(),
			'game_class' => SWFL_Request_Guard::int_arg(),
			'group'      => SWFL_Request_Guard::group_arg(),
		);

		self::add_route( '/games', 'rest_games', $game_args );
		self::add_route( '/next-game', 'rest_next_game', $game_args );
		self::add_route( '/last-result', 'rest_last_result', $game_args );
		self::add_route(
			'/rankings',
			'rest_rankings',
			array(
				'league'     => SWFL_Request_Guard::int_arg( true ),
				'game_class' => SWFL_Request_Guard::int_arg( true ),
				'season'     => SWFL_Request_Guard::season_arg(),
				'group'      => SWFL_Request_Guard::group_arg(),
			)
		);
		self::add_route(
			'/topscorers',
			'rest_topscorers',
			array(
				'club_id'    => SWFL_Request_Guard::int_arg(),
				'season'     => SWFL_Request_Guard::season_arg(),
				'league'     => SWFL_Request_Guard::int_arg(),
				'game_class' => SWFL_Request_Guard::int_arg(),
			)
		);
	}

	/**
	 * Register one public, read-only route.
	 *
	 * @since 2.1.0
	 * @param string $route    Route below the namespace, with leading slash.
	 * @param string $callback Name of the static callback method of this class.
	 * @param array  $args     Argument definitions.
	 * @return void
	 */
	private static function add_route( $route, $callback, $args ) {
		register_rest_route(
			SWFL_Widgets::REST_NAMESPACE,
			$route,
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, $callback ),
				'permission_callback' => array( 'SWFL_Request_Guard', 'check_rate_limit' ),
				'args'                => $args,
			)
		);
	}

	/**
	 * REST callback: all games of a team, a club or a group.
	 *
	 * @since 2.1.0
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error Games, or an error.
	 */
	public static function rest_games( $request ) {
		$games = self::load_games( $request );
		if ( is_wp_error( $games ) ) {
			return $games;
		}

		return self::respond(
			array(
				'title' => $games['title'],
				'games' => $games['games'],
			)
		);
	}

	/**
	 * REST callback: the next game that has no result yet.
	 *
	 * @since 2.1.0
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error The game, `null` when there is none, or an error.
	 */
	public static function rest_next_game( $request ) {
		$games = self::load_games( $request );
		if ( is_wp_error( $games ) ) {
			return $games;
		}

		$today = current_time( 'Y-m-d' );
		$next  = null;
		foreach ( $games['games'] as $game ) {
			if ( '' !== $game['date'] && $game['date'] >= $today && '' === $game['result'] ) {
				$next = $game;
				break;
			}
		}

		return self::respond(
			array(
				'title' => $games['title'],
				'game'  => $next,
			)
		);
	}

	/**
	 * REST callback: the most recent game that has a result.
	 *
	 * @since 2.1.0
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error The game, `null` when there is none, or an error.
	 */
	public static function rest_last_result( $request ) {
		$games = self::load_games( $request );
		if ( is_wp_error( $games ) ) {
			return $games;
		}

		$last = null;
		foreach ( $games['games'] as $game ) {
			if ( '' !== $game['result'] ) {
				$last = $game;
			}
		}

		return self::respond(
			array(
				'title' => $games['title'],
				'game'  => $last,
			)
		);
	}

	/**
	 * REST callback: ranking of a league group.
	 *
	 * @since 2.1.0
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error Ranking table, or an error.
	 */
	public static function rest_rankings( $request ) {
		$params = array(
			'season'     => self::get_season( $request ),
			'league'     => absint( $request->get_param( 'league' ) ),
			'game_class' => absint( $request->get_param( 'game_class' ) ),
			'group'      => SWFL_Request_Guard::sanitize_group( $request->get_param( 'group' ) ),
			'view'       => 'full',
		);

		$response = SWFL_Widgets::get_client()->fetch_data( 'rankings', SWFL_Table_Data::clean_params( $params ) );
		if ( is_wp_error( $response ) || ! isset( $response['data']['regions'] ) ) {
			return self::upstream_error( $response );
		}

		return self::respond( self::table_payload( $response['data'] ) );
	}

	/**
	 * REST callback: topscorers of a league (`league` + `game_class`) or the Mobiliar topscorers.
	 *
	 * @since 2.1.0
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error Topscorers, or an error.
	 */
	public static function rest_topscorers( $request ) {
		$season     = self::get_season( $request );
		$league     = absint( $request->get_param( 'league' ) );
		$game_class = absint( $request->get_param( 'game_class' ) );

		if ( $league && $game_class ) {
			$response = SWFL_Widgets::get_client()->fetch_data(
				'topscorers/su',
				array(
					'season'     => $season,
					'league'     => $league,
					'game_class' => $game_class,
				)
			);
		} else {
			$response = SWFL_Widgets::get_client()->fetch_data(
				'topscorers/mobiliar-highlight',
				SWFL_Table_Data::clean_params(
					array(
						'season'    => $season,
						'club_id'   => absint( $request->get_param( 'club_id' ) ),
						'view_type' => 'table',
					)
				)
			);
		}

		if ( is_wp_error( $response ) || ! isset( $response['data']['regions'] ) ) {
			return self::upstream_error( $response );
		}

		if ( $league && $game_class ) {
			return self::respond( self::table_payload( $response['data'] ) );
		}

		return self::respond(
			array(
				'title'   => self::get_title( $response['data'], 'Topscorer' ),
				'players' => SWFL_Widgets::collect_topscorers( SWFL_Table_Data::get_rows( $response['data'] ) ),
			)
		);
	}

	/**
	 * Load and normalise the games of a team, a club or a league group.
	 *
	 * @since 2.1.0
	 * @param WP_REST_Request $request Request with team_id, club_id or league + game_class (+ group), and season.
	 * @return array|WP_Error Array with `title` and `games` (sorted by date, cancelled games dropped), or an error.
	 */
	private static function load_games( $request ) {
		$params = self::get_game_params( $request );
		if ( is_wp_error( $params ) ) {
			return $params;
		}

		$result = SWFL_Table_Data::fetch_all_pages( 'games', SWFL_Table_Data::clean_params( $params ) );
		if ( is_wp_error( $result ) ) {
			return self::upstream_error( $result );
		}

		$columns = self::map_columns( isset( $result['data']['headers'] ) ? $result['data']['headers'] : array() );
		$games   = array();
		foreach ( SWFL_Table_Data::prepare_game_rows( $result['rows'] ) as $row ) {
			$games[] = self::build_game( $row, $columns );
		}

		return array(
			'title' => self::get_title( $result['data'], 'Swiss Floorball' ),
			'games' => $games,
		);
	}

	/**
	 * Build the games query from the request, in the order team, club, league group.
	 *
	 * @since 2.1.0
	 * @param WP_REST_Request $request Request.
	 * @return array|WP_Error Query parameters, or a 400 error when no filter is given.
	 */
	private static function get_game_params( $request ) {
		$params     = array( 'season' => self::get_season( $request ) );
		$team_id    = absint( $request->get_param( 'team_id' ) );
		$club_id    = absint( $request->get_param( 'club_id' ) );
		$league     = absint( $request->get_param( 'league' ) );
		$game_class = absint( $request->get_param( 'game_class' ) );

		if ( $team_id ) {
			$params['mode']    = 'team';
			$params['team_id'] = $team_id;
		} elseif ( $club_id ) {
			$params['mode']    = 'club';
			$params['club_id'] = $club_id;
		} elseif ( $league && $game_class ) {
			$params['mode']       = 'list';
			$params['league']     = $league;
			$params['game_class'] = $game_class;
			$params['group']      = SWFL_Request_Guard::sanitize_group( $request->get_param( 'group' ) );
		} else {
			return new WP_Error( 'swfl_missing_params', 'team_id, club_id or league and game_class are required', array( 'status' => 400 ) );
		}

		return $params;
	}

	/**
	 * Season of the request, the current season when none is given.
	 *
	 * @since 2.1.0
	 * @param WP_REST_Request $request Request.
	 * @return int Season (start year).
	 */
	private static function get_season( $request ) {
		$season = absint( $request->get_param( 'season' ) );

		return $season ? $season : (int) SWFL_Display::get_current_season();
	}

	/**
	 * Find the columns of a games table by their header text.
	 *
	 * @since 2.1.0
	 * @param array $headers Table headers.
	 * @return array Column indexes per key (date, place, home, away, result, league).
	 */
	private static function map_columns( $headers ) {
		$patterns = array(
			'date'   => '/datum|zeit/iu',
			'place'  => '/^ort/iu',
			'home'   => '/heim/iu',
			'away'   => '/gast/iu',
			'result' => '/resultat|ergebnis/iu',
			'league' => '/liga|gruppe/iu',
		);
		$columns  = array();
		foreach ( $headers as $index => $header ) {
			$text = isset( $header['text'] ) ? (string) $header['text'] : '';
			foreach ( $patterns as $key => $pattern ) {
				if ( ! isset( $columns[ $key ] ) && preg_match( $pattern, $text ) ) {
					$columns[ $key ] = $index;
				}
			}
		}

		return $columns;
	}

	/**
	 * Turn a game row into a flat game object.
	 *
	 * @since 2.1.0
	 * @param array $row     Prepared game row (with `sfa_date`).
	 * @param array $columns Column indexes per key, see map_columns().
	 * @return array Game with game_id, date, time, place, home, away, result, league, link and cells.
	 */
	private static function build_game( $row, $columns ) {
		$cells = isset( $row['cells'] ) && is_array( $row['cells'] ) ? $row['cells'] : array();
		$texts = array_map( array( 'SWFL_Table_Data', 'get_cell_text' ), $cells );
		$field = static function ( $key ) use ( $columns, $texts ) {
			return isset( $columns[ $key ], $texts[ $columns[ $key ] ] ) ? $texts[ $columns[ $key ] ] : '';
		};

		$result = $field( 'result' );
		$time   = '';
		if ( preg_match( '/\b(\d{1,2}):(\d{2})\b/', $field( 'date' ), $match ) ) {
			$time = sprintf( '%02d:%02d', $match[1], $match[2] );
		}
		$game_id = SWFL_Table_Data::get_game_id( $row );

		return array(
			'game_id' => $game_id,
			'date'    => $row['sfa_date'],
			'time'    => $time,
			'place'   => $field( 'place' ),
			'home'    => $field( 'home' ),
			'away'    => $field( 'away' ),
			// Upcoming games carry a placeholder such as "-:-"; only a real score counts as a result.
			'result'  => preg_match( '/\d+\s*:\s*\d+/', $result ) ? $result : '',
			'league'  => $field( 'league' ),
			'link'    => $game_id ? SWFL_Widgets::GAME_LINK_BASE . $game_id : '',
			'cells'   => array_values( $texts ),
		);
	}

	/**
	 * Convert a generic table response into a JSON payload with header labels and text rows.
	 *
	 * @since 2.1.0
	 * @param array $data The `data` part of an API response.
	 * @return array Payload with title, headers and rows.
	 */
	private static function table_payload( $data ) {
		$headers = array();
		if ( isset( $data['headers'] ) && is_array( $data['headers'] ) ) {
			foreach ( $data['headers'] as $header ) {
				$headers[] = isset( $header['text'] ) ? (string) $header['text'] : '';
			}
		}

		$rows = array();
		foreach ( SWFL_Table_Data::get_rows( $data ) as $row ) {
			$cells  = isset( $row['cells'] ) && is_array( $row['cells'] ) ? $row['cells'] : array();
			$rows[] = array_values( array_map( array( 'SWFL_Table_Data', 'get_cell_text' ), $cells ) );
		}

		return array(
			'title'   => self::get_title( $data, '' ),
			'headers' => $headers,
			'rows'    => $rows,
		);
	}

	/**
	 * Title of an API response.
	 *
	 * @since 2.1.0
	 * @param array  $data     The `data` part of an API response.
	 * @param string $fallback Title used when the response has none.
	 * @return string Title.
	 */
	private static function get_title( $data, $fallback ) {
		return isset( $data['title'] ) && is_string( $data['title'] ) ? $data['title'] : $fallback;
	}

	/**
	 * Wrap a payload in a cacheable response.
	 *
	 * @since 2.1.0
	 * @param array $payload Response data.
	 * @return WP_REST_Response Response with a public Cache-Control header, a short one for an expired copy.
	 */
	private static function respond( $payload ) {
		$response = new WP_REST_Response( $payload, 200 );
		$response->header( 'Cache-Control', 'public, max-age=' . ( SWFL_Upstream_Budget::was_stale_served() ? self::STALE_MAX_AGE : self::CACHE_MAX_AGE ) );

		return $response;
	}

	/**
	 * Error for a failed upstream request, without the upstream detail for visitors.
	 *
	 * @since 2.1.0
	 * @param array|WP_Error $response Response returned by the client.
	 * @return WP_Error 502 error, or the 503 error of a request the upstream budget refused.
	 */
	private static function upstream_error( $response ) {
		if ( SWFL_Upstream_Budget::is_budget_error( $response ) ) {
			return $response;
		}
		if ( is_wp_error( $response ) && current_user_can( 'manage_options' ) ) {
			$response->add_data( array( 'status' => 502 ) );
			return $response;
		}

		return new WP_Error( 'api_error', 'The data could not be loaded', array( 'status' => 502 ) );
	}
}
