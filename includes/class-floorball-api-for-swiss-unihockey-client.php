<?php
/**
 * Fired during plugin activation
 *
 * @link       https://flaviowaser.ch
 * @since      1.0.0
 *
 * @package    Swiss_Floorball_Api
 * @subpackage Swiss_Floorball_Api/includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    Swiss_Floorball_Api
 * @subpackage Swiss_Floorball_Api/includes
 * @author     Flavio Waser <kontakt@flawas.ch>
 */
class Swiss_Floorball_API_Client {

	/**
	 * Free public API (read-only whitelist of endpoints, no credentials).
	 *
	 * @since 1.1.0
	 * @var   string
	 */
	const SOURCE_FREE = 'free';

	/**
	 * Partner API (all endpoints, requires an api_key and secret from Swiss Unihockey).
	 *
	 * @since 1.1.0
	 * @var   string
	 */
	const SOURCE_PARTNER = 'partner';

	/**
	 * Base URLs per API source.
	 *
	 * @since 1.1.0
	 * @var   array
	 */
	const BASE_URLS = array(
		self::SOURCE_FREE    => 'https://wc.swissunihockey.ch/',
		self::SOURCE_PARTNER => 'https://office.swissunihockey.ch/api/legacy/',
	);

	/**
	 * Transient that holds the partner API auth token.
	 *
	 * @since 1.1.0
	 * @var   string
	 */
	const TOKEN_TRANSIENT = 'swfl_partner_token';

	/**
	 * Get the configured API source, falling back to the free API.
	 *
	 * @since 1.1.0
	 * @return string One of the SOURCE_* constants.
	 */
	public static function get_source() {
		$source = get_option( 'swissfloorball_api_source', self::SOURCE_FREE );
		// A stored removed source (the former 'legacy' API) falls back to the free API.
		return array_key_exists( $source, self::BASE_URLS ) ? $source : self::SOURCE_FREE;
	}

	/**
	 * Get the base URL of the configured API source.
	 *
	 * @since 1.1.0
	 * @return string Base URL with trailing slash.
	 */
	private function get_base_url() {
		return self::BASE_URLS[ self::get_source() ];
	}

	/**
	 * Get a partner API auth token, requesting a new one when none is cached.
	 *
	 * @since 1.1.0
	 * @param bool $force Optional. Ignore the cached token and request a new one.
	 * @return string|WP_Error Token, or WP_Error when credentials are missing or rejected.
	 */
	private function get_partner_token( $force = false ) {
		if ( ! $force ) {
			$token = get_transient( self::TOKEN_TRANSIENT );
			if ( is_string( $token ) && '' !== $token ) {
				return $token;
			}
		}

		$api_key = get_option( 'swissfloorball_api_key', '' );
		$secret  = get_option( 'swissfloorball_api_secret', '' );
		if ( '' === $api_key || '' === $secret ) {
			return new WP_Error( 'swfl_partner_credentials', 'API key or secret missing' );
		}

		$response = wp_remote_post(
			self::BASE_URLS[ self::SOURCE_PARTNER ] . 'bo/session/auth',
			array(
				'timeout' => 10,
				'body'    => array(
					'api_key' => $api_key,
					'secret'  => $secret,
				),
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body  = json_decode( wp_remote_retrieve_body( $response ), true );
		$token = '';
		if ( isset( $body['auth_token'] ) ) {
			$token = $body['auth_token'];
		} elseif ( isset( $body['data']['auth_token'] ) ) {
			$token = $body['data']['auth_token'];
		}
		if ( 200 !== wp_remote_retrieve_response_code( $response ) || ! is_string( $token ) || '' === $token ) {
			return new WP_Error( 'swfl_partner_auth', 'Partner API authentication failed' );
		}

		// Expiry is not documented, so keep it short and refresh on a rejected request.
		set_transient( self::TOKEN_TRANSIENT, $token, 30 * MINUTE_IN_SECONDS );

		return $token;
	}

	/**
	 * Fetch data from the API.
	 *
	 * @since    1.0.0
	 * @param    string $endpoint    The API endpoint to fetch.
	 * @param    array  $args        Optional. Arguments for the API request.
	 * @param    int    $cache_time  Optional. Time in seconds to cache the response. Default 3600 (1 hour).
	 * @return   array|WP_Error         The decoded JSON response or WP_Error on failure.
	 */
	public function fetch_data( $endpoint, $args = array(), $cache_time = 3600 ) {
		$source = self::get_source();
		$url    = $this->get_base_url() . $endpoint;

		// Add query args if present.
		if ( ! empty( $args ) ) {
			$url = add_query_arg( $args, $url );
		}

		// Generate a unique cache key for this request.
		$cache_key   = 'swfl_' . hash( 'sha256', $url );
		$cached_data = get_transient( $cache_key );

		if ( false !== $cached_data ) {
			return $cached_data;
		}

		// Request timeout in seconds; defaults to the admin setting and is overridable via the swfl_request_timeout filter.
		$timeout = max( 1, (float) apply_filters( 'swfl_request_timeout', (float) get_option( 'swissfloorball_request_timeout', 3 ), $url ) );

		$request_args = array(
			'timeout' => $timeout,
			'headers' => array( 'Accept' => 'application/json' ),
		);

		// The token is added after the cache key is built so it never ends up in the key.
		$request_url = $url;
		if ( self::SOURCE_PARTNER === $source ) {
			$token = $this->get_partner_token();
			if ( is_wp_error( $token ) ) {
				return $token;
			}
			$request_url = add_query_arg( 'auth_token', $token, $url );
		}

		$response = wp_remote_get( $request_url, $request_args );

		// A rejected token is refreshed once, as its lifetime is not documented.
		if ( self::SOURCE_PARTNER === $source && ! is_wp_error( $response ) && in_array( wp_remote_retrieve_response_code( $response ), array( 401, 403 ), true ) ) {
			$token = $this->get_partner_token( true );
			if ( is_wp_error( $token ) ) {
				return $token;
			}
			$response = wp_remote_get( add_query_arg( 'auth_token', $token, $url ), $request_args );
		}

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		if ( 403 === $response_code && self::SOURCE_FREE === $source ) {
			return new WP_Error( 'swfl_endpoint_unavailable', 'Endpoint not available in the free API' );
		}
		if ( 200 !== $response_code ) {
			return new WP_Error( 'api_error', 'API returned status code ' . $response_code );
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return new WP_Error( 'json_error', 'Failed to decode JSON response' );
		}

		// Cache the successful response.
		set_transient( $cache_key, $data, $cache_time );

		return $data;
	}
}
