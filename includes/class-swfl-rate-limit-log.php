<?php
/**
 * Short-lived list of the visitors the REST rate limit blocked, for the settings page.
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
 * Remembers which rate-limit buckets (IPv4 addresses, IPv6 /64 prefixes) were blocked during the last hour.
 *
 * The counter of the rate limit only knows a hash of the bucket, so the bucket itself is kept here. A visitor is
 * written at most once per window, and the list is capped, so a flood costs reads only. Entries are dropped
 * an hour after their last block; the list is personal data and kept no longer than that.
 *
 * @since      2.2.0
 * @package    SWFL
 * @subpackage SWFL_Plugin/includes
 * @author     Flavio Waser <kontakt@flawas.ch>
 */
class SWFL_Rate_Limit_Log {

	/**
	 * Transient that holds the list.
	 *
	 * @since 2.2.0
	 * @var   string
	 */
	const TRANSIENT = 'swfl_rate_limited';

	/**
	 * Most visitors in the list; further ones are not recorded until older entries expire.
	 *
	 * @since 2.2.0
	 * @var   int
	 */
	const MAX_ENTRIES = 50;

	/**
	 * How long an entry stays after its last block, in seconds.
	 *
	 * @since 2.2.0
	 * @var   int
	 */
	const KEEP = HOUR_IN_SECONDS;

	/**
	 * Record that a bucket was blocked until the end of the current window.
	 *
	 * @since 2.2.0
	 * @param string   $bucket Rate-limit bucket from SWFL_Client_Ip::get_bucket().
	 * @param int      $until  Timestamp the block ends (end of the window).
	 * @param int|null $now    Optional. Timestamp, for tests. Default: time().
	 * @return void
	 */
	public static function record( $bucket, $until, $now = null ) {
		$now     = null === $now ? time() : (int) $now;
		$entries = self::prune( SWFL_Cache_Entry::get_shared( self::TRANSIENT ), $now );
		$entry   = isset( $entries[ $bucket ] ) ? $entries[ $bucket ] : null;
		// Once per bucket and window, and no new buckets beyond the cap: refused requests of a flood write nothing.
		if ( ( null !== $entry && $entry['until'] >= $until ) || ( null === $entry && count( $entries ) >= self::MAX_ENTRIES ) ) {
			return;
		}

		$entries[ $bucket ] = array(
			'first'   => null === $entry ? $now : $entry['first'],
			'until'   => (int) $until,
			'minutes' => null === $entry ? 1 : $entry['minutes'] + 1,
		);
		set_transient( self::TRANSIENT, $entries, self::KEEP );
	}

	/**
	 * The visitors blocked during the last hour, the latest block first.
	 *
	 * @since 2.2.0
	 * @param int|null $now Optional. Timestamp, for tests. Default: time().
	 * @return array[] Entries by bucket: `first` (first block), `until` (end of the latest block), `minutes` (windows blocked).
	 */
	public static function get_entries( $now = null ) {
		$entries = self::prune( get_transient( self::TRANSIENT ), null === $now ? time() : (int) $now );
		uasort(
			$entries,
			static function ( $a, $b ) {
				return $b['until'] - $a['until'];
			}
		);

		return $entries;
	}

	/**
	 * Write a bucket the way an administrator reads it.
	 *
	 * @since 2.2.0
	 * @param string $bucket Rate-limit bucket.
	 * @return string IPv4 address, IPv6 prefix such as `2001:db8:1:2::/64`, or an empty string for the unknown bucket.
	 */
	public static function format_bucket( $bucket ) {
		$text = '';
		if ( 0 === strpos( $bucket, '4:' ) ) {
			$text = substr( $bucket, 2 );
		} elseif ( 1 === preg_match( '/^6:([0-9a-f]{16})$/D', $bucket, $match ) ) {
			$text = inet_ntop( hex2bin( $match[1] ) . str_repeat( "\0", 8 ) ) . '/64';
		}

		return $text;
	}

	/**
	 * Keep the well-formed entries whose last block ended less than KEEP seconds ago.
	 *
	 * @since 2.2.0
	 * @param mixed $stored Stored list, false when there is none.
	 * @param int   $now    Timestamp.
	 * @return array[] Entries by bucket.
	 */
	private static function prune( $stored, $now ) {
		$entries = array();
		foreach ( is_array( $stored ) ? $stored : array() as $bucket => $entry ) {
			if ( is_array( $entry ) && isset( $entry['first'], $entry['until'], $entry['minutes'] ) && (int) $entry['until'] > $now - self::KEEP ) {
				$entries[ (string) $bucket ] = array_map( 'intval', $entry );
			}
		}

		return $entries;
	}
}
