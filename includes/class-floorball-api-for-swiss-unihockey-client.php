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
	 * The base URL for the Swiss Unihockey API.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $api_base_url    The base URL for the API.
	 */
	private $api_base_url = 'https://api-v2.swissunihockey.ch/api/';

	/**
	 * Fetch data from the API.
	 *
	 * @since    1.0.0
	 * @param    string    $endpoint    The API endpoint to fetch.
	 * @param    array     $args        Optional. Arguments for the API request.
	 * @param    int       $cache_time  Optional. Time in seconds to cache the response. Default 3600 (1 hour).
	 * @return   array|WP_Error         The decoded JSON response or WP_Error on failure.
	 */
	public function fetch_data( $endpoint, $args = array(), $cache_time = 3600 ) {
		$url = $this->api_base_url . $endpoint;

		// Add query args if present
		if ( ! empty( $args ) ) {
			$url = add_query_arg( $args, $url );
		}

		// Generate a unique cache key for this request
		$cache_key = 'sfa_' . md5( $url );
		$cached_data = get_transient( $cache_key );

		if ( false !== $cached_data ) {
			return $cached_data;
		}

		$response = wp_remote_get( $url );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $response_code ) {
			return new WP_Error( 'api_error', 'API returned status code ' . $response_code );
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return new WP_Error( 'json_error', 'Failed to decode JSON response' );
		}

		// Cache the successful response
		set_transient( $cache_key, $data, $cache_time );

		return $data;
	}
}
