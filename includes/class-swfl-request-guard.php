<?php
/**
 * Guards for input that reaches the Swiss Unihockey API: value checks and a rate limit for public routes.
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
 * therefore accept only values in a sensible range and answer at most a limited number of requests per visitor
 * and minute.
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
	 * Highest accepted ID (team, club, league, game class, round). The API's own IDs are below 1.2 million.
	 *
	 * @since 2.2.0
	 * @var   int
	 */
	const MAX_ID = 10000000;

	/**
	 * Oldest accepted season: the first one the API lists (1996/97).
	 *
	 * @since 2.2.0
	 * @var   int
	 */
	const MIN_SEASON = 1996;

	/**
	 * Request that was last counted and the result, so WordPress asking again for the same request counts once.
	 *
	 * @since 2.2.0
	 * @var   WP_REST_Request|null
	 */
	private static $checked_request = null;

	/**
	 * Result for the request in $checked_request.
	 *
	 * @since 2.2.0
	 * @var   true|WP_Error|null
	 */
	private static $checked_result = null;

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
	 * Definition of an ID argument (team, club, league, game class, round, page size).
	 *
	 * @since 2.2.0
	 * @param bool $required Whether the argument is required. A required ID is at least 1; an optional one may
	 *                       also be 0 or empty, which both mean "not set".
	 * @return array Argument definition.
	 */
	public static function int_arg( $required = false ) {
		return array(
			'type'              => 'integer',
			'required'          => $required,
			'validate_callback' => array( __CLASS__, $required ? 'validate_required_id' : 'validate_optional_id' ),
			'sanitize_callback' => 'absint',
		);
	}

	/**
	 * Definition of the season argument.
	 *
	 * @since 2.2.0
	 * @return array Argument definition.
	 */
	public static function season_arg() {
		return array(
			'type'              => 'integer',
			'validate_callback' => array( __CLASS__, 'validate_season' ),
			'sanitize_callback' => 'absint',
		);
	}

	/**
	 * Definition of the group name argument.
	 *
	 * @since 2.2.0
	 * @return array Argument definition.
	 */
	public static function group_arg() {
		return array(
			'type'              => 'string',
			'validate_callback' => array( __CLASS__, 'validate_group' ),
			'sanitize_callback' => array( __CLASS__, 'sanitize_group' ),
		);
	}

	/**
	 * Validate a required ID: a whole number from 1 to MAX_ID.
	 *
	 * @since 2.2.0
	 * @param mixed $value Raw value.
	 * @return true|WP_Error True when valid.
	 */
	public static function validate_required_id( $value ) {
		$number = self::parse_whole_number( $value );

		return null !== $number && $number >= 1 && $number <= self::MAX_ID
			? true
			: new WP_Error( 'swfl_invalid_id', 'Must be a whole number from 1 to ' . self::MAX_ID . '.' );
	}

	/**
	 * Validate an optional ID: empty or 0 (not set), or a whole number from 1 to MAX_ID.
	 *
	 * @since 2.2.0
	 * @param mixed $value Raw value.
	 * @return true|WP_Error True when valid.
	 */
	public static function validate_optional_id( $value ) {
		$number = '' === $value ? 0 : self::parse_whole_number( $value );

		return null !== $number && $number <= self::MAX_ID
			? true
			: new WP_Error( 'swfl_invalid_id', 'Must be empty, 0 or a whole number from 1 to ' . self::MAX_ID . '.' );
	}

	/**
	 * Validate a season: empty or 0 (current season), or a start year from MIN_SEASON to next year.
	 *
	 * @since 2.2.0
	 * @param mixed $value Raw value.
	 * @return true|WP_Error True when valid.
	 */
	public static function validate_season( $value ) {
		$number = '' === $value ? 0 : self::parse_whole_number( $value );
		$max    = self::get_max_season();

		return null !== $number && ( 0 === $number || ( $number >= self::MIN_SEASON && $number <= $max ) )
			? true
			: new WP_Error( 'swfl_invalid_season', 'Must be empty, 0 or a season start year from ' . self::MIN_SEASON . ' to ' . $max . '.' );
	}

	/**
	 * Validate a group name: empty, or 1 to 40 letters, digits, spaces and . _ / -.
	 *
	 * @since 2.2.0
	 * @param mixed $value Raw value.
	 * @return true|WP_Error True when valid.
	 */
	public static function validate_group( $value ) {
		if ( '' === $value || ( is_string( $value ) && 1 === preg_match( '/^[\p{L}\p{N} ._\/-]{1,' . self::MAX_GROUP_LENGTH . '}$/uD', $value ) ) ) {
			return true;
		}

		return new WP_Error( 'swfl_invalid_group', 'Must be up to ' . self::MAX_GROUP_LENGTH . ' letters, digits, spaces or . _ / -.' );
	}

	/**
	 * Latest accepted season: one year after the later of the configured season and the current year.
	 *
	 * The configured season may be pinned to an old year, so the calendar year is looked at as well.
	 *
	 * @since 2.2.0
	 * @return int Season start year.
	 */
	public static function get_max_season() {
		return max( (int) SWFL_Display::get_current_season(), absint( current_time( 'Y' ) ) ) + 1;
	}

	/**
	 * Permission callback of the public REST routes: limits the requests per visitor and minute.
	 *
	 * The routes stay public, but each uncached request costs an upstream request and a database row. The check
	 * also marks the request as a public REST request, which puts its upstream requests under the site-wide budget.
	 *
	 * @since 2.0.3
	 * @param WP_REST_Request|null $request Optional. The request; WordPress asks more than once per request
	 *                                      (e.g. for the Allow header), and that counts once.
	 * @return true|WP_Error True while the visitor is within the limit, a 429 error otherwise.
	 */
	public static function check_rate_limit( $request = null ) {
		if ( null !== $request && self::$checked_request === $request ) {
			return self::$checked_result;
		}
		$bucket = SWFL_Client_Ip::get_bucket();
		SWFL_Upstream_Budget::enter_public_request( $bucket );

		/**
		 * Filters how many public REST requests one visitor may send per minute. 0 turns the limit off.
		 *
		 * The default is the setting "REST requests per minute and visitor".
		 *
		 * @since 2.0.3
		 * @param int $limit Requests per minute.
		 */
		$limit  = (int) apply_filters( 'swfl_rest_rate_limit', absint( get_option( 'swfl_rest_rate_limit', self::DEFAULT_RATE_LIMIT ) ) );
		$result = true;
		if ( $limit > 0 && ! SWFL_Counter::take( 'rl_' . substr( hash( 'sha256', $bucket ), 0, 32 ), $limit, MINUTE_IN_SECONDS ) ) {
			// For the list of blocked visitors on the settings page.
			SWFL_Rate_Limit_Log::record( $bucket, time() + SWFL_Counter::seconds_left( MINUTE_IN_SECONDS ) );
			$result = new WP_Error(
				'swfl_rate_limited',
				'Too many requests',
				array(
					'status'      => 429,
					'retry_after' => SWFL_Counter::seconds_left( MINUTE_IN_SECONDS ),
				)
			);
		}

		self::$checked_request = $request;
		self::$checked_result  = $result;

		return $result;
	}

	/**
	 * Add Retry-After and no-store to the 429 and 503 answers of the plugin.
	 *
	 * Hooked to rest_post_dispatch, because a WP_Error cannot carry headers.
	 *
	 * @since 2.2.0
	 * @param WP_HTTP_Response $response Response about to be sent.
	 * @return WP_HTTP_Response The response.
	 */
	public static function add_retry_after( $response ) {
		$data = $response->get_data();
		if ( is_array( $data ) && isset( $data['code'], $data['data']['retry_after'] ) && in_array( $data['code'], array( 'swfl_rate_limited', SWFL_Upstream_Budget::ERROR_CODE ), true ) ) {
			$response->header( 'Retry-After', (string) absint( $data['data']['retry_after'] ) );
			$response->header( 'Cache-Control', 'no-store' );
		}

		return $response;
	}

	/**
	 * Read a value as a non-negative whole number without being generous: digits only, up to 10 of them.
	 *
	 * Rejects negative numbers, decimals, exponents, signs, spaces, arrays and anything else absint() would turn into a number.
	 *
	 * @since 2.2.0
	 * @param mixed $value Raw value.
	 * @return int|null The number, null when the value is not one.
	 */
	private static function parse_whole_number( $value ) {
		if ( is_int( $value ) ) {
			return $value >= 0 ? $value : null;
		}

		return is_string( $value ) && 1 === preg_match( '/^\d{1,10}$/D', $value ) ? (int) $value : null;
	}
}
