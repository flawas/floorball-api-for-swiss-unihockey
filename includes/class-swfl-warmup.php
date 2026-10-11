<?php
/**
 * Scheduled cache warm-up.
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
 * Refreshes the most used API responses of the configured club in the background.
 *
 * Without it the first visitor after a transient expired pays the full API round trip. A WP-Cron
 * event re-fetches games, rankings and topscorers with the same requests the shortcodes send, so
 * the cache keys match and the visitors always hit a warm cache.
 *
 * @since      2.1.0
 * @package    SWFL
 * @subpackage SWFL_Plugin/includes
 * @author     Flavio Waser <kontakt@flawas.ch>
 */
class SWFL_Warmup {

	/**
	 * Recurring cron hook that runs the warm-up.
	 *
	 * @since 2.1.0
	 * @var   string
	 */
	const HOOK = 'swfl_cache_warmup';

	/**
	 * Single-event cron hook for an immediate warm-up (after the cache was cleared by hand).
	 *
	 * @since 2.1.0
	 * @var   string
	 */
	const NOW_HOOK = 'swfl_cache_warmup_now';

	/**
	 * Name of the custom cron schedule.
	 *
	 * @since 2.1.0
	 * @var   string
	 */
	const SCHEDULE = 'swfl_cache_warmup_interval';

	/**
	 * Default interval in minutes.
	 *
	 * @since 2.1.0
	 * @var   int
	 */
	const DEFAULT_INTERVAL = 30;

	/**
	 * Longest interval in minutes (one day).
	 *
	 * @since 2.1.0
	 * @var   int
	 */
	const MAX_INTERVAL = 1440;

	/**
	 * Transient that keeps two runs from overlapping.
	 *
	 * @since 2.1.0
	 * @var   string
	 */
	const LOCK_TRANSIENT = 'swfl_warmup_lock';

	/**
	 * Register the cron hooks. Runs in every request, as cron callbacks load without an admin screen.
	 *
	 * @since 2.1.0
	 * @param SWFL_Loader $loader Hook loader.
	 * @return void
	 */
	public static function register_hooks( $loader ) {
		$loader->add_filter( 'cron_schedules', __CLASS__, 'add_schedule' );
		$loader->add_action( self::HOOK, __CLASS__, 'run' );
		$loader->add_action( self::NOW_HOOK, __CLASS__, 'run' );
		$loader->add_action( 'init', __CLASS__, 'maybe_schedule' );
		$loader->add_action( 'add_option_swfl_cache_interval', __CLASS__, 'schedule' );
		$loader->add_action( 'update_option_swfl_cache_interval', __CLASS__, 'schedule' );
	}

	/**
	 * Get the configured interval.
	 *
	 * @since 2.1.0
	 * @return int Minutes between two runs, 0 when the warm-up is turned off.
	 */
	public static function get_interval() {
		$value = get_option( 'swfl_cache_interval', self::DEFAULT_INTERVAL );
		if ( ! is_scalar( $value ) || ! is_numeric( $value ) ) {
			return self::DEFAULT_INTERVAL;
		}
		return min( self::MAX_INTERVAL, absint( $value ) );
	}

	/**
	 * Add the warm-up interval to the cron schedules.
	 *
	 * @since 2.1.0
	 * @param array $schedules Registered schedules.
	 * @return array Schedules including the warm-up interval.
	 */
	public static function add_schedule( $schedules ) {
		$minutes = max( 1, self::get_interval() );

		// The interval is the configured number of minutes, so it is not a literal.
		// phpcs:ignore WordPress.WP.CronInterval.ChangeDetected
		$schedules[ self::SCHEDULE ] = array(
			'interval' => $minutes * MINUTE_IN_SECONDS,
			/* translators: %d: number of minutes between two cache warm-ups. */
			'display'  => sprintf( __( 'Swiss Floorball API cache warm-up (every %d minutes)', 'swiss-floorball-api' ), $minutes ),
		);

		return $schedules;
	}

	/**
	 * (Re)create the recurring event for the configured interval; an interval of 0 only removes it.
	 *
	 * @since 2.1.0
	 * @return void
	 */
	public static function schedule() {
		self::unschedule();

		if ( self::get_interval() < 1 ) {
			return;
		}

		// The activation request may run before the plugin hooks, and an unknown schedule is rejected.
		if ( ! has_filter( 'cron_schedules', array( __CLASS__, 'add_schedule' ) ) ) {
			add_filter( 'cron_schedules', array( __CLASS__, 'add_schedule' ) );
		}

		wp_schedule_event( time(), self::SCHEDULE, self::HOOK );
	}

	/**
	 * Remove the recurring and the pending single event.
	 *
	 * @since 2.1.0
	 * @return void
	 */
	public static function unschedule() {
		wp_clear_scheduled_hook( self::HOOK );
		wp_clear_scheduled_hook( self::NOW_HOOK );
	}

	/**
	 * Make sure the event exists while the warm-up is on, for sites that were updated and not re-activated.
	 *
	 * @since 2.1.0
	 * @return void
	 */
	public static function maybe_schedule() {
		$scheduled = false !== wp_next_scheduled( self::HOOK );
		$enabled   = self::get_interval() > 0;

		if ( $enabled && ! $scheduled ) {
			self::schedule();
		} elseif ( ! $enabled && $scheduled ) {
			self::unschedule();
		}
	}

	/**
	 * Queue one warm-up run right away, in the background.
	 *
	 * @since 2.1.0
	 * @return void
	 */
	public static function queue_now() {
		if ( false === wp_next_scheduled( self::NOW_HOOK ) ) {
			wp_schedule_single_event( time(), self::NOW_HOOK );
		}
		spawn_cron();
	}

	/**
	 * Refresh games, rankings and topscorers of the configured club.
	 *
	 * Every request is forced past the cache and written back, so a failed request keeps the old data.
	 *
	 * @since 2.1.0
	 * @return int Number of refreshed responses.
	 */
	public static function run() {
		$club_id = absint( get_option( 'swfl_club_number', 0 ) );
		if ( 0 === $club_id || false !== get_transient( self::LOCK_TRANSIENT ) ) {
			return 0;
		}

		// Longer than a run takes, short enough that a crashed run does not block the next ones for long.
		set_transient( self::LOCK_TRANSIENT, 1, 10 * MINUTE_IN_SECONDS );

		$season    = SWFL_Display::get_current_season();
		$refreshed = 0;
		$groups    = array();

		foreach ( self::get_club_requests( $club_id, $season ) as $request ) {
			if ( ! self::refresh( $request, $refreshed ) ) {
				delete_transient( self::LOCK_TRANSIENT );
				return $refreshed;
			}
		}

		foreach ( self::get_team_ids( $club_id, $season, $refreshed ) as $team_id ) {
			$result = SWFL_Table_Data::fetch_all_pages(
				'games',
				array(
					'mode'    => 'team',
					'team_id' => $team_id,
					'season'  => $season,
				),
				true
			);
			if ( is_wp_error( $result ) ) {
				continue;
			}
			++$refreshed;

			$ids = isset( $result['data']['tabs'][0]['link']['ids'] ) ? $result['data']['tabs'][0]['link']['ids'] : array();
			// IDs format: [season, league, game_class, group].
			if ( is_array( $ids ) && count( $ids ) >= 4 ) {
				$groups[ implode( '|', array_slice( $ids, 1, 3 ) ) ] = array_slice( $ids, 1, 3 );
			}
		}

		foreach ( self::get_group_requests( $groups, $season ) as $request ) {
			self::refresh( $request, $refreshed );
		}

		delete_transient( self::LOCK_TRANSIENT );

		/**
		 * Fires after a cache warm-up run.
		 *
		 * @since 2.1.0
		 * @param int $refreshed Number of refreshed responses.
		 */
		do_action( 'swfl_cache_warmup_done', $refreshed );

		return $refreshed;
	}

	/**
	 * Requests that only depend on the club: its games and the Mobiliar topscorers.
	 *
	 * The parameters match the shortcodes, otherwise the cache keys would differ.
	 *
	 * @since 2.1.0
	 * @param int $club_id Club id.
	 * @param int $season  Season (start year).
	 * @return array[] Requests with `endpoint`, `params` and `paged`.
	 */
	private static function get_club_requests( $club_id, $season ) {
		return array(
			array(
				'endpoint' => 'games',
				'params'   => array(
					'mode'    => 'club',
					'club_id' => $club_id,
					'season'  => $season,
				),
				'paged'    => true,
			),
			array(
				'endpoint' => 'topscorers/mobiliar-highlight',
				'params'   => array(
					'season'    => $season,
					'club_id'   => $club_id,
					'view_type' => 'table',
				),
				'paged'    => false,
			),
		);
	}

	/**
	 * Requests per league group of the club teams: the ranking and the topscorers.
	 *
	 * @since 2.1.0
	 * @param array[] $groups Unique `[ league, game_class, group ]` triples.
	 * @param int     $season Season (start year).
	 * @return array[] Requests with `endpoint`, `params` and `paged`.
	 */
	private static function get_group_requests( $groups, $season ) {
		$requests = array();
		$scorers  = array();

		foreach ( $groups as $group ) {
			list( $league, $game_class, $name ) = $group;

			$requests[] = array(
				'endpoint' => 'rankings',
				'params'   => SWFL_Table_Data::clean_params(
					array(
						'season'     => $season,
						'league'     => $league,
						'game_class' => $game_class,
						'group'      => $name,
						'view'       => 'full',
					)
				),
				'paged'    => false,
			);

			// The topscorers do not depend on the group, so each league and game class is fetched once.
			$scorers[ $league . '|' . $game_class ] = array(
				'endpoint' => 'topscorers/su',
				'params'   => array(
					'season'     => $season,
					'league'     => $league,
					'game_class' => $game_class,
				),
				'paged'    => false,
			);
		}

		return array_merge( $requests, array_values( $scorers ) );
	}

	/**
	 * Get the ids of the teams the club registered for the season.
	 *
	 * @since 2.1.0
	 * @param int $club_id   Club id.
	 * @param int $season    Season (start year).
	 * @param int $refreshed Counter of refreshed responses, increased by one on success.
	 * @return int[] Team ids.
	 */
	private static function get_team_ids( $club_id, $season, &$refreshed ) {
		$response = SWFL_Widgets::get_client()->fetch_data(
			'teams',
			array(
				'club_id' => $club_id,
				'season'  => $season,
				'mode'    => 'by_club',
			),
			null,
			true
		);
		if ( is_wp_error( $response ) || empty( $response['entries'] ) || ! is_array( $response['entries'] ) ) {
			return array();
		}
		++$refreshed;

		$team_ids = array();
		foreach ( $response['entries'] as $entry ) {
			if ( isset( $entry['set_in_context']['team_id'] ) ) {
				$team_ids[] = absint( $entry['set_in_context']['team_id'] );
			}
		}

		return array_values( array_unique( array_filter( $team_ids ) ) );
	}

	/**
	 * Refresh one request.
	 *
	 * @since 2.1.0
	 * @param array $request   Request with `endpoint`, `params` and `paged`.
	 * @param int   $refreshed Counter of refreshed responses, increased by one on success.
	 * @return bool False when the run should stop because the API credentials are rejected.
	 */
	private static function refresh( $request, &$refreshed ) {
		if ( $request['paged'] ) {
			$result = SWFL_Table_Data::fetch_all_pages( $request['endpoint'], $request['params'], true );
		} else {
			$result = SWFL_Widgets::get_client()->fetch_data( $request['endpoint'], $request['params'], null, true );
		}

		if ( ! is_wp_error( $result ) ) {
			++$refreshed;
			return true;
		}

		// A wrong key or secret fails every following request as well; the free API simply lacks some endpoints.
		return ! in_array( $result->get_error_code(), array( 'swfl_partner_credentials', 'swfl_partner_auth' ), true );
	}
}
