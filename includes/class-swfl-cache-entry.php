<?php
/**
 * Cache entries of the API client: payloads with a fresh-until time and short-lived error markers.
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
 * Encodes and decodes what SWFL_Client keeps in a transient.
 *
 * A transient holds either the decoded API payload (written by 2.1.2 and older), an envelope around a
 * payload that remembers when it stops being fresh, or a marker for a failed request.
 *
 * @since      2.2.0
 * @package    SWFL
 * @subpackage SWFL_Plugin/includes
 * @author     Flavio Waser <kontakt@flawas.ch>
 */
class SWFL_Cache_Entry {

	/**
	 * Key that tells an envelope or marker from a raw payload.
	 *
	 * @since 2.2.0
	 * @var   string
	 */
	const MARKER = 'swfl_entry';

	/**
	 * How long a payload outlives its fresh time, so a request can still be answered when the budget is used up.
	 *
	 * @since 2.2.0
	 * @var   int
	 */
	const STALE_TTL = HOUR_IN_SECONDS;

	/**
	 * Default lifetime of a failed request, in seconds.
	 *
	 * @since 2.2.0
	 * @var   int
	 */
	const ERROR_TTL = 300;

	/**
	 * Longest lifetime of a failure that is likely to pass by itself (timeouts, 5xx, 429), in seconds.
	 *
	 * @since 2.2.0
	 * @var   int
	 */
	const TRANSIENT_ERROR_TTL = 60;

	/**
	 * Longest stored text of an error, in characters.
	 *
	 * @since 2.2.0
	 * @var   int
	 */
	const MAX_TEXT_LENGTH = 200;

	/**
	 * Code of the pseudo error that stands for a successful answer without any rows (see is_empty_payload()).
	 *
	 * @since 2.2.0
	 * @var   string
	 */
	const EMPTY_CODE = 'swfl_empty_response';

	/**
	 * Wrap a payload together with the time it stops being fresh.
	 *
	 * @since 2.2.0
	 * @param mixed    $data Decoded API payload.
	 * @param int      $ttl  Seconds the payload is fresh.
	 * @param int|null $now  Optional. Timestamp, for tests. Default: time().
	 * @return array Envelope.
	 */
	public static function wrap( $data, $ttl, $now = null ) {
		return array(
			self::MARKER  => 1,
			'fresh_until' => ( null === $now ? time() : (int) $now ) + (int) $ttl,
			'data'        => $data,
		);
	}

	/**
	 * Build the marker of a failed request.
	 *
	 * @since 2.2.0
	 * @param WP_Error $error Error returned by the API client.
	 * @return array Marker.
	 */
	public static function wrap_error( $error ) {
		$data  = $error->get_error_data();
		$clean = array();
		if ( is_array( $data ) ) {
			foreach ( array_slice( $data, 0, 5, true ) as $key => $value ) {
				if ( is_scalar( $value ) ) {
					$clean[ (string) $key ] = is_string( $value ) ? mb_substr( $value, 0, self::MAX_TEXT_LENGTH ) : $value;
				}
			}
		}

		return array(
			self::MARKER => 1,
			'error'      => array(
				'code'    => (string) $error->get_error_code(),
				'message' => mb_substr( (string) $error->get_error_message(), 0, self::MAX_TEXT_LENGTH ),
				'data'    => $clean,
			),
		);
	}

	/**
	 * Read what get_transient() returned.
	 *
	 * @since 2.2.0
	 * @param mixed    $stored Transient value, false when there is none.
	 * @param int|null $now    Optional. Timestamp, for tests. Default: time().
	 * @return array|null Null for a miss, otherwise `type` ('data' or 'error'), `fresh`, and `data` or `error`.
	 */
	public static function read( $stored, $now = null ) {
		$entry = null;
		if ( false === $stored ) {
			return $entry;
		}

		if ( ! is_array( $stored ) || ! isset( $stored[ self::MARKER ] ) || 1 !== $stored[ self::MARKER ] ) {
			// Written by 2.1.2 or older: a raw payload whose transient lifetime was its fresh time.
			$entry = array(
				'type'  => 'data',
				'fresh' => true,
				'data'  => $stored,
			);
		} elseif ( isset( $stored['error'] ) && is_array( $stored['error'] ) ) {
			$entry = array(
				'type'  => 'error',
				'fresh' => true,
				'error' => $stored['error'],
			);
		} else {
			$now   = null === $now ? time() : (int) $now;
			$entry = array(
				'type'  => 'data',
				'fresh' => isset( $stored['fresh_until'] ) && (int) $stored['fresh_until'] > $now,
				'data'  => isset( $stored['data'] ) ? $stored['data'] : null,
			);
		}

		return $entry;
	}

	/**
	 * Read a transient from the database instead of this PHP process' copy of it.
	 *
	 * Without a persistent object cache WordPress keeps every option it has read or written in an in-process
	 * cache, so a row that a parallel request stored after this process first looked at the key would stay
	 * invisible. With a persistent object cache that cache is the shared store and nothing needs to be dropped.
	 *
	 * @since 2.2.0
	 * @param string $name Transient name.
	 * @return mixed Stored value, false when there is none.
	 */
	public static function get_shared( $name ) {
		if ( ! wp_using_ext_object_cache() ) {
			wp_cache_delete( '_transient_' . $name, 'options' );
			wp_cache_delete( '_transient_timeout_' . $name, 'options' );
			wp_cache_delete( 'notoptions', 'options' );
		}

		return get_transient( $name );
	}

	/**
	 * Rebuild the WP_Error a marker stands for.
	 *
	 * @since 2.2.0
	 * @param array $error `error` part of a marker.
	 * @return WP_Error Error with the code, message and data of the original.
	 */
	public static function to_error( $error ) {
		return new WP_Error(
			isset( $error['code'] ) ? (string) $error['code'] : 'api_error',
			isset( $error['message'] ) ? (string) $error['message'] : '',
			! empty( $error['data'] ) && is_array( $error['data'] ) ? $error['data'] : ''
		);
	}

	/**
	 * How long a failed request may be remembered.
	 *
	 * @since 2.2.0
	 * @param WP_Error $error Error returned by the API client.
	 * @param string   $url   Request URL without auth token.
	 * @return int Seconds, 0 when the failure must not be cached.
	 */
	public static function get_error_ttl( $error, $url ) {
		$class = self::classify( $error );
		if ( 'never' === $class ) {
			return 0;
		}

		/**
		 * Filters how long a failed upstream request is remembered, in seconds.
		 *
		 * Failures that pass by themselves (timeouts, 5xx, 429) are limited to 60 seconds after this filter.
		 * 0 turns the caching of failures off.
		 *
		 * @since 2.2.0
		 * @param int      $ttl   Seconds, default 300.
		 * @param WP_Error $error Error returned by the API client.
		 * @param string   $url   Request URL without auth token.
		 */
		$ttl = (int) apply_filters( 'swfl_error_cache_ttl', self::ERROR_TTL, $error, $url );
		if ( 'transient' === $class ) {
			$ttl = min( $ttl, self::TRANSIENT_ERROR_TTL );
		}

		return max( 0, $ttl );
	}

	/**
	 * How long an answer without rows is remembered.
	 *
	 * An unknown or out-of-range request is answered with status 200 and an empty table or list, which counts as a
	 * failed request here: it has the lifetime of one (filter swfl_error_cache_ttl) instead of the cache lifetime.
	 *
	 * @since 2.2.0
	 * @param string $url Request URL without auth token.
	 * @return int Seconds, 0 when empty answers are cached like any other answer.
	 */
	public static function get_empty_ttl( $url ) {
		return self::get_error_ttl( new WP_Error( self::EMPTY_CODE, 'The API answered without any rows.' ), $url );
	}

	/**
	 * How long a payload is kept after it stopped being fresh.
	 *
	 * @since 2.2.0
	 * @param string $url Request URL without auth token.
	 * @return int Seconds, 0 when no expired copy is kept.
	 */
	public static function get_stale_ttl( $url ) {
		/**
		 * Filters how long an expired response is kept, so it can still be served when the site-wide upstream budget is used up.
		 *
		 * Without a persistent object cache the copy occupies two database rows for this time on top of the cache
		 * lifetime. 0 keeps no expired copy.
		 *
		 * @since 2.2.0
		 * @param int    $ttl Seconds, default 3600.
		 * @param string $url Request URL without auth token.
		 */
		return max( 0, (int) apply_filters( 'swfl_stale_ttl', self::STALE_TTL, $url ) );
	}

	/**
	 * Whether a successful answer holds no rows at all.
	 *
	 * The API answers a round or season that does not exist for a valid group, or a club without teams, with
	 * status 200 and an empty table (`data.regions`) or list (`entries`). A client can ask for those with any number
	 * of different values.
	 *
	 * @since 2.2.0
	 * @param mixed $data Decoded API payload.
	 * @return bool True for an empty list or for a table whose regions have no rows.
	 */
	public static function is_empty_payload( $data ) {
		$empty = false;
		if ( is_array( $data ) && isset( $data['entries'] ) && is_array( $data['entries'] ) ) {
			$empty = array() === $data['entries'];
		} elseif ( is_array( $data ) && isset( $data['data']['regions'] ) && is_array( $data['data']['regions'] ) ) {
			$empty = true;
			foreach ( $data['data']['regions'] as $region ) {
				if ( ! empty( $region['rows'] ) ) {
					$empty = false;
					break;
				}
			}
		}

		return $empty;
	}

	/**
	 * Sort an error into 'never' (auth state, budget), 'permanent' (the answer will stay the same) or 'transient'.
	 *
	 * @since 2.2.0
	 * @param WP_Error $error Error returned by the API client.
	 * @return string One of 'never', 'permanent' and 'transient'.
	 */
	public static function classify( $error ) {
		$code  = $error->get_error_code();
		$class = 'transient';
		if ( in_array( $code, array( 'swfl_partner_credentials', 'swfl_partner_auth', 'swfl_budget_exceeded' ), true ) ) {
			$class = 'never';
		} elseif ( 'swfl_endpoint_unavailable' === $code || self::EMPTY_CODE === $code ) {
			$class = 'permanent';
		} elseif ( 'api_error' === $code ) {
			$class = self::classify_status( $error );
		}
		// Everything else is a transport error or invalid JSON, which is likely to pass by itself.

		return $class;
	}

	/**
	 * Sort an HTTP error of the API by its status code, see classify().
	 *
	 * @since 2.2.0
	 * @param WP_Error $error Error with the status in `upstream_status`.
	 * @return string One of 'never', 'permanent' and 'transient'.
	 */
	private static function classify_status( $error ) {
		$data   = $error->get_error_data();
		$status = is_array( $data ) && isset( $data['upstream_status'] ) ? (int) $data['upstream_status'] : 0;
		$class  = 'transient';
		if ( 401 === $status || 403 === $status ) {
			$class = 'never';
		} elseif ( $status >= 400 && $status < 500 && 408 !== $status && 429 !== $status ) {
			$class = 'permanent';
		}

		return $class;
	}
}
