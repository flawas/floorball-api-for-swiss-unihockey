<?php
/**
 * Site-wide budget for uncached requests to the Swiss Unihockey API that public REST routes cause.
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
 * Limits how many requests per minute the whole site may send to the API on behalf of public REST requests.
 *
 * Only requests that passed SWFL_Request_Guard::check_rate_limit() are limited. Page views with shortcodes, the
 * admin area, administrators and the WP-Cron warm-up never enter that state, so they are not limited. Every
 * visitor (rate-limit bucket) may only use a share of it, so one or two addresses cannot use the budget up for
 * everybody.
 *
 * @since      2.2.0
 * @package    SWFL
 * @subpackage SWFL_Plugin/includes
 * @author     Flavio Waser <kontakt@flawas.ch>
 */
class SWFL_Upstream_Budget {

	/**
	 * Default number of upstream requests per minute.
	 *
	 * @since 2.2.0
	 * @var   int
	 */
	const DEFAULT_LIMIT = 120;

	/**
	 * Highest accepted setting.
	 *
	 * @since 2.2.0
	 * @var   int
	 */
	const MAX_LIMIT = 10000;

	/**
	 * Default number of upstream requests per minute one visitor may cause.
	 *
	 * Enough for the longest lists of a big club (about 17 pages) in one go.
	 *
	 * @since 2.2.0
	 * @var   int
	 */
	const DEFAULT_VISITOR_LIMIT = 20;

	/**
	 * Code of the error that stands for a refused upstream request.
	 *
	 * @since 2.2.0
	 * @var   string
	 */
	const ERROR_CODE = 'swfl_budget_exceeded';

	/**
	 * Transient that remembers the last time the budget ran out, for the notice on the settings page.
	 *
	 * @since 2.2.0
	 * @var   string
	 */
	const NOTICE_TRANSIENT = 'swfl_budget_notice';

	/**
	 * Whether the current request runs for a public REST route.
	 *
	 * @since 2.2.0
	 * @var   bool
	 */
	private static $public_request = false;

	/**
	 * Rate-limit bucket of the visitor of the current public request, empty when unknown.
	 *
	 * @since 2.2.0
	 * @var   string
	 */
	private static $visitor = '';

	/**
	 * The error of the first refused request of this PHP request, for routes that cannot return it directly.
	 *
	 * @since 2.2.0
	 * @var   WP_Error|null
	 */
	private static $denial = null;

	/**
	 * Whether an expired copy was served instead of a refused request.
	 *
	 * @since 2.2.0
	 * @var   bool
	 */
	private static $stale_served = false;

	/**
	 * Mark the current request as one that causes upstream requests for a public REST route.
	 *
	 * @since 2.2.0
	 * @param string $visitor Optional. Rate-limit bucket of the visitor, see SWFL_Client_Ip::get_bucket(). Without
	 *                        one only the site-wide limit applies.
	 * @return void
	 */
	public static function enter_public_request( $visitor = '' ) {
		self::$public_request = ! current_user_can( 'manage_options' );
		self::$visitor        = (string) $visitor;
		// A PHP process can serve several REST requests (internal calls, tests), each starts without history.
		self::$denial       = null;
		self::$stale_served = false;
	}

	/**
	 * End the public REST state once a route has run, so the rest of the page load is not limited.
	 *
	 * Hooked to rest_request_after_callbacks, which fires for internal rest_do_request() calls as well, and late
	 * to rest_post_dispatch: core checks the permission again for the Allow header, which would enter the state
	 * anew for a request that failed its argument validation and never reached the callback.
	 *
	 * @since 2.2.0
	 * @param mixed $response Response, returned unchanged.
	 * @return mixed The response.
	 */
	public static function leave_public_request( $response = null ) {
		self::$public_request = false;

		return $response;
	}

	/**
	 * Get the number of upstream requests per minute.
	 *
	 * @since 2.2.0
	 * @return int Requests per minute, 0 when the budget is off.
	 */
	public static function get_limit() {
		/**
		 * Filters how many requests per minute public REST requests may cause site-wide. 0 turns the budget off.
		 *
		 * The default is the setting "Upstream requests per minute".
		 *
		 * @since 2.2.0
		 * @param int $limit Requests per minute.
		 */
		return (int) apply_filters( 'swfl_upstream_budget', absint( get_option( 'swfl_upstream_budget', self::DEFAULT_LIMIT ) ) );
	}

	/**
	 * Get the number of upstream requests per minute one visitor may cause.
	 *
	 * @since 2.2.0
	 * @return int Requests per minute, 0 when visitors have no share of their own.
	 */
	public static function get_visitor_limit() {
		/**
		 * Filters how many upstream requests per minute one visitor of the public REST routes may cause. 0 turns the share off.
		 *
		 * The default is the setting "Upstream requests per minute and visitor".
		 *
		 * @since 2.2.0
		 * @param int $limit Requests per minute.
		 */
		return (int) apply_filters( 'swfl_upstream_visitor_budget', absint( get_option( 'swfl_upstream_visitor_budget', self::DEFAULT_VISITOR_LIMIT ) ) );
	}

	/**
	 * Take one unit of the budget before an upstream request: first of the visitor's share, then of the site.
	 *
	 * The share comes first, so a visitor who used it up takes nothing from the budget of the others.
	 *
	 * @since 2.2.0
	 * @return true|WP_Error True when the request may go out, a 429 error when the visitor's share is used up,
	 *                       a 503 error when the minute's budget of the site is used up.
	 */
	public static function acquire() {
		$allowed = true;
		if ( self::$public_request ) {
			$allowed = self::take_visitor_share() ? self::take_site_unit() : self::refusal( 429 );
		}

		return $allowed;
	}

	/**
	 * Hand out an expired copy because the budget refused the refresh.
	 *
	 * @since 2.2.0
	 * @param mixed $data Payload of the expired entry.
	 * @return mixed The payload.
	 */
	public static function serve_stale( $data ) {
		self::$stale_served = true;

		return $data;
	}

	/**
	 * Remember a refused request, so routes that render HTML can answer with 503 instead of a notice.
	 *
	 * @since 2.2.0
	 * @param WP_Error $error Error from acquire().
	 * @return WP_Error The error.
	 */
	public static function refuse( $error ) {
		if ( null === self::$denial ) {
			self::$denial = $error;
		}

		return $error;
	}

	/**
	 * The first refused request of this PHP request.
	 *
	 * @since 2.2.0
	 * @return WP_Error|null Error, or null when nothing was refused.
	 */
	public static function get_denial() {
		return self::$denial;
	}

	/**
	 * Whether an expired copy was served in this PHP request.
	 *
	 * @since 2.2.0
	 * @return bool
	 */
	public static function was_stale_served() {
		return self::$stale_served;
	}

	/**
	 * Whether a value is the error of a refused request.
	 *
	 * @since 2.2.0
	 * @param mixed $thing Value to check.
	 * @return bool
	 */
	public static function is_budget_error( $thing ) {
		return is_wp_error( $thing ) && self::ERROR_CODE === $thing->get_error_code();
	}

	/**
	 * Forget the state of the current request (tests and long-running workers).
	 *
	 * @since 2.2.0
	 * @return void
	 */
	public static function reset() {
		self::$public_request = false;
		self::$visitor        = '';
		self::$denial         = null;
		self::$stale_served   = false;
	}

	/**
	 * Take one unit of the visitor's share.
	 *
	 * @since 2.2.0
	 * @return bool False when the share is used up; true without a share or without a known visitor.
	 */
	private static function take_visitor_share() {
		$limit = self::get_visitor_limit();
		if ( $limit <= 0 || '' === self::$visitor ) {
			return true;
		}
		$allowed = SWFL_Counter::take( 'uv_' . substr( hash( 'sha256', self::$visitor ), 0, 32 ), $limit, MINUTE_IN_SECONDS );
		if ( ! $allowed ) {
			SWFL_Rate_Limit_Log::record( self::$visitor, time() + SWFL_Counter::seconds_left( MINUTE_IN_SECONDS ) );
		}

		return $allowed;
	}

	/**
	 * Take one unit of the site's budget.
	 *
	 * @since 2.2.0
	 * @return true|WP_Error True, or the 503 error when the budget is used up.
	 */
	private static function take_site_unit() {
		$limit = self::get_limit();
		if ( $limit <= 0 || SWFL_Counter::take( 'upstream', $limit, MINUTE_IN_SECONDS ) ) {
			return true;
		}
		self::note_exhausted( $limit );

		return self::refusal( 503 );
	}

	/**
	 * The error of a refused upstream request.
	 *
	 * @since 2.2.0
	 * @param int $status 429 for the visitor's share, 503 for the site's budget.
	 * @return WP_Error Error with status and retry_after.
	 */
	private static function refusal( $status ) {
		return new WP_Error(
			self::ERROR_CODE,
			429 === $status ? 'Too many requests' : 'The service is busy, please try again later.',
			array(
				'status'      => $status,
				'retry_after' => SWFL_Counter::seconds_left( MINUTE_IN_SECONDS ),
			)
		);
	}

	/**
	 * Tell administrators once per minute that the budget ran out: a notice on the settings page and an action.
	 *
	 * @since 2.2.0
	 * @param int $limit Requests per minute.
	 * @return void
	 */
	private static function note_exhausted( $limit ) {
		$last = get_transient( self::NOTICE_TRANSIENT );
		if ( is_array( $last ) && isset( $last['time'] ) && time() - (int) $last['time'] < MINUTE_IN_SECONDS ) {
			return;
		}
		set_transient(
			self::NOTICE_TRANSIENT,
			array(
				'time'  => time(),
				'limit' => $limit,
			),
			DAY_IN_SECONDS
		);

		/**
		 * Fires at most once per minute while the site-wide upstream request budget is used up.
		 *
		 * Hook it to log or to alert, the plugin itself only shows a notice on its settings page.
		 *
		 * @since 2.2.0
		 * @param int $limit Requests per minute.
		 */
		do_action( 'swfl_upstream_budget_exceeded', $limit );
	}
}
