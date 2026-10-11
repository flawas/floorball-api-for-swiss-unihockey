<?php
/**
 * Guards for input that reaches the Swiss Unihockey API: value cleaning and a rate limit for public routes.
 *
 * @link       https://flaviowaser.ch
 * @since      2.0.3
 *
 * @package    SWFL
 * @subpackage SWFL_Plugin/includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Guards for input that reaches the Swiss Unihockey API.
 *
 * Every distinct request value creates an upstream request and a cached transient. The public REST routes
 * therefore accept only short group names and answer at most a limited number of requests per visitor and minute.
 *
 * @since      2.0.3
 * @package    SWFL
 * @subpackage SWFL_Plugin/includes
 * @author     Flavio Waser <kontakt@flawas.ch>
 */
class SWFL_Request_Guard {

	/**
	 * Longest accepted group name.
	 *
	 * @since 2.0.3
	 * @var   int
	 */
	const MAX_GROUP_LENGTH = 40;

	/**
	 * Default number of public REST requests per visitor and minute.
	 *
	 * @since 2.0.3
	 * @var   int
	 */
	const DEFAULT_RATE_LIMIT = 60;

	/**
	 * Clean a group name such as "Gruppe 1": letters, digits, spaces and a few separators only.
	 *
	 * @since 2.0.3
	 * @param mixed $value Raw value.
	 * @return string Cleaned group name, possibly empty.
	 */
	public static function sanitize_group( $value ) {
		if ( ! is_scalar( $value ) ) {
			return '';
		}

		$group = preg_replace( '/[^\p{L}\p{N} ._\/-]+/u', '', sanitize_text_field( (string) $value ) );
		$group = is_string( $group ) ? $group : '';

		return trim( mb_substr( $group, 0, self::MAX_GROUP_LENGTH ) );
	}

	/**
	 * Permission callback of the public REST routes: limits the requests per visitor and minute.
	 *
	 * The routes stay public, but each uncached request costs an upstream request and a database row.
	 *
	 * @since 2.0.3
	 * @return true|WP_Error True while the visitor is within the limit, a 429 error otherwise.
	 */
	public static function check_rate_limit() {
		/**
		 * Filters how many public REST requests one visitor may send per minute. 0 turns the limit off.
		 *
		 * The default is the setting "REST requests per minute and visitor".
		 *
		 * @since 2.0.3
		 * @param int $limit Requests per minute.
		 */
		$limit = (int) apply_filters( 'swfl_rest_rate_limit', absint( get_option( 'swfl_rest_rate_limit', self::DEFAULT_RATE_LIMIT ) ) );
		if ( $limit <= 0 ) {
			return true;
		}

		// WordPress asks the permission callback more than once per request (e.g. for the Allow header), so count once.
		static $result = null;
		if ( null !== $result ) {
			return $result;
		}

		// REMOTE_ADDR only: forwarded headers are chosen by the client and would let it dodge the limit.
		$address = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$key     = 'swfl_rl_' . substr( hash( 'sha256', $address ), 0, 32 );
		$count   = (int) get_transient( $key );

		if ( $count >= $limit ) {
			$result = new WP_Error( 'swfl_rate_limited', 'Too many requests', array( 'status' => 429 ) );
			return $result;
		}

		set_transient( $key, $count + 1, MINUTE_IN_SECONDS );
		$result = true;

		return true;
	}
}
