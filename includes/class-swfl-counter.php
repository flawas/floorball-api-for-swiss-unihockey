<?php
/**
 * Fixed-window counter shared by the per-visitor rate limit and the site-wide upstream budget.
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
 * Counts events per fixed time window and tells whether a limit is still free.
 *
 * The count is atomic, so parallel requests can neither lose an event nor get past the limit. With a persistent
 * object cache it is a wp_cache_incr(). Without one it is an option row per counter and window that a single
 * SQL statement creates or increases; the row name carries the end of its window, and expired rows are removed
 * whenever a new one is created. Only a database that does not understand that statement falls back to a
 * transient, whose read-modify-write is not atomic.
 *
 * @since      2.2.0
 * @package    SWFL
 * @subpackage SWFL_Plugin/includes
 * @author     Flavio Waser <kontakt@flawas.ch>
 */
class SWFL_Counter {

	/**
	 * Object cache group.
	 *
	 * @since 2.2.0
	 * @var   string
	 */
	const GROUP = 'swfl';

	/**
	 * Prefix of the option row of a window: the prefix, the end of the window (ten digits) and the counter name.
	 *
	 * @since 2.2.0
	 * @var   string
	 */
	const ROW_PREFIX = 'swfl_count_';

	/**
	 * Prefix of the transient that holds a counter when the database refused the statements of the row.
	 *
	 * @since 2.2.0
	 * @var   string
	 */
	const TRANSIENT_PREFIX = 'swfl_c_';

	/**
	 * Count one event and tell whether it is within the limit.
	 *
	 * An event beyond the limit is not written, so a flood that is being refused costs reads only.
	 *
	 * @since 2.2.0
	 * @param string   $name   Counter name: lowercase letters, digits and underscores.
	 * @param int      $limit  Events allowed per window; below 1 nothing is allowed.
	 * @param int      $window Window length in seconds. Windows are aligned to the Unix epoch.
	 * @param int|null $now    Optional. Timestamp, for tests. Default: time().
	 * @return bool True while the event is within the limit.
	 */
	public static function take( $name, $limit, $window = MINUTE_IN_SECONDS, $now = null ) {
		if ( $limit < 1 ) {
			return false;
		}
		$now    = null === $now ? time() : (int) $now;
		$bucket = (int) floor( $now / $window );
		$count  = wp_using_ext_object_cache() ? self::increment_cache( $name, $bucket, self::seconds_left( $window, $now ) ) : null;
		if ( null === $count ) {
			$count = self::increment_row( self::row_name( $name, $bucket, $window ), (int) $limit, $now );
		}
		if ( null === $count ) {
			$count = self::increment_transient( $name, $bucket, self::seconds_left( $window, $now ), (int) $limit );
		}

		return $count <= $limit;
	}

	/**
	 * Events counted in the current window.
	 *
	 * @since 2.2.0
	 * @param string   $name   Counter name.
	 * @param int      $window Window length in seconds.
	 * @param int|null $now    Optional. Timestamp, for tests. Default: time().
	 * @return int Count, 0 when the window has no events.
	 */
	public static function get( $name, $window = MINUTE_IN_SECONDS, $now = null ) {
		global $wpdb;
		$now    = null === $now ? time() : (int) $now;
		$bucket = (int) floor( $now / $window );
		$count  = wp_using_ext_object_cache() ? wp_cache_get( $name . ':' . $bucket, self::GROUP ) : false;
		if ( false === $count ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- The count must come from the database, see increment_row().
			$count = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", self::row_name( $name, $bucket, $window ) ) );
		}
		if ( null === $count ) {
			$count = self::count_in_state( SWFL_Cache_Entry::get_shared( self::TRANSIENT_PREFIX . $name ), $bucket );
		}

		return (int) $count;
	}

	/**
	 * Seconds until the current window ends, the value for a Retry-After header.
	 *
	 * @since 2.2.0
	 * @param int      $window Window length in seconds.
	 * @param int|null $now    Optional. Timestamp, for tests. Default: time().
	 * @return int 1 to $window.
	 */
	public static function seconds_left( $window = MINUTE_IN_SECONDS, $now = null ) {
		$now = null === $now ? time() : (int) $now;

		return $window - ( $now % $window );
	}

	/**
	 * Name of the option row of a window.
	 *
	 * @since 2.2.0
	 * @param string $name   Counter name.
	 * @param int    $bucket Window number.
	 * @param int    $window Window length in seconds.
	 * @return string Option name, e.g. `swfl_count_1792607100_upstream`.
	 */
	private static function row_name( $name, $bucket, $window ) {
		return self::ROW_PREFIX . sprintf( '%010d', ( $bucket + 1 ) * $window ) . '_' . $name;
	}

	/**
	 * Add one event in the persistent object cache.
	 *
	 * @since 2.2.0
	 * @param string $name   Counter name.
	 * @param int    $bucket Window number.
	 * @param int    $left   Seconds until the window ends.
	 * @return int|null Count including this event, null when the cache could not count.
	 */
	private static function increment_cache( $name, $bucket, $left ) {
		$key = $name . ':' . $bucket;
		// Creating the key is atomic, so parallel requests cannot reset each other's count.
		wp_cache_add( $key, 0, self::GROUP, $left + 1 );
		$count = wp_cache_incr( $key, 1, self::GROUP );

		return false === $count ? null : (int) $count;
	}

	/**
	 * Add one event to the option row of a window, atomically, and return the new count.
	 *
	 * @since 2.2.0
	 * @param string $row   Option name from row_name().
	 * @param int    $limit Limit of the window: an event beyond it is not written.
	 * @param int    $now   Timestamp.
	 * @return int|null Count including this event, null when the database refused the statement.
	 */
	private static function increment_row( $row, $limit, $now ) {
		global $wpdb;
		// The options API can neither increase a value atomically nor read it past its in-process cache, so the
		// row is read and written with SQL of its own.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- See above.
		$current = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $row ) );
		if ( null !== $current && (int) $current >= $limit ) {
			// Already used up: refused without a write. The count only grows within a window, so this cannot be wrong.
			return (int) $current + 1;
		}

		// One statement creates the row or adds one to it, so parallel requests cannot lose an event.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- See above.
		$affected = $wpdb->query( $wpdb->prepare( "INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, '1', 'no') ON DUPLICATE KEY UPDATE option_value = option_value + 1", $row ) );
		if ( 1 === $affected ) {
			// A new row: remove every row whose window has ended, of all counters, so visitors leave no rows behind.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Removes the plugin's own expired rows.
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s AND option_name < %s", $wpdb->esc_like( self::ROW_PREFIX ) . '%', self::ROW_PREFIX . sprintf( '%010d', $now ) ) );
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- See above.
		$count = false === $affected ? null : $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $row ) );

		return null === $count ? null : (int) $count;
	}

	/**
	 * Add one event to the transient of a counter: the last resort, not atomic.
	 *
	 * @since 2.2.0
	 * @param string $name   Counter name.
	 * @param int    $bucket Window number.
	 * @param int    $left   Seconds until the window ends.
	 * @param int    $limit  Limit of the window, so events beyond it need no database write.
	 * @return int Count including this event.
	 */
	private static function increment_transient( $name, $bucket, $left, $limit ) {
		$key = self::TRANSIENT_PREFIX . $name;
		// The state comes from the database: a request that counts several times (every page of a long list is one event) must see the events of parallel requests, not only its own.
		$count = self::count_in_state( SWFL_Cache_Entry::get_shared( $key ), $bucket ) + 1;
		if ( $count <= $limit ) {
			set_transient(
				$key,
				array(
					'b' => $bucket,
					'n' => $count,
				),
				$left + 1
			);
		}

		return $count;
	}

	/**
	 * Events a stored state holds for a window.
	 *
	 * @since 2.2.0
	 * @param mixed $state  Stored state, false when there is none.
	 * @param int   $bucket Window number.
	 * @return int Count, 0 when the state belongs to another window or is empty.
	 */
	private static function count_in_state( $state, $bucket ) {
		return is_array( $state ) && isset( $state['b'], $state['n'] ) && (int) $state['b'] === $bucket ? (int) $state['n'] : 0;
	}
}
