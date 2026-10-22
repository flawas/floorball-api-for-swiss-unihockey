<?php
/**
 * Integration check against a real WordPress (the Docker site): REST validation, negative caching, rate limit and upstream budget.
 *
 * Run it inside the WordPress container, the plugin directory is mounted there:
 *
 *   docker exec swiss-floorball-api-wordpress-1 php /var/www/html/wp-content/plugins/swiss-floorball-api/scripts/tests/integration.php
 *
 * The Swiss Unihockey API is replaced by canned answers (pre_http_request), so nothing leaves the machine and every
 * upstream request is counted. Settings are restored and the swfl_* transients are cleared at the end.
 *
 * Dev-only: not part of the release ZIP.
 *
 * @package SWFL
 */

if ( 'cli' !== PHP_SAPI ) {
	exit;
}

// SWFL_PLUGINS_DIR and SWFL_WP_LOAD are only needed to test a copy of the plugin that is not mounted in WordPress.
$swfl_wp_load = getenv( 'SWFL_WP_LOAD' ) ? getenv( 'SWFL_WP_LOAD' ) : dirname( __DIR__, 5 ) . '/wp-load.php';
if ( getenv( 'SWFL_PLUGINS_DIR' ) ) {
	define( 'WP_PLUGIN_DIR', getenv( 'SWFL_PLUGINS_DIR' ) );
}
define( 'WP_USE_THEMES', false );
require $swfl_wp_load;

global $wpdb;
$swfl_failures = 0;
$swfl_calls    = array();

/**
 * Report one check.
 *
 * @param string $name Check name.
 * @param bool   $ok   Whether it held.
 * @param string $info Detail shown on failure.
 */
function swfl_check( $name, $ok, $info = '' ) {
	global $swfl_failures;
	if ( ! $ok ) {
		++$swfl_failures;
	}
	echo ( $ok ? '  ok    ' : '  FAIL  ' ) . $name . ( $ok || '' === $info ? '' : "\n        " . $info ) . "\n";
}

/**
 * Canned upstream: team ids from 9000000 on are unknown (404), everything else answers with one game.
 *
 * @param mixed  $pre  Short-circuit value.
 * @param array  $args Request arguments.
 * @param string $url  Request URL.
 * @return mixed Answer, or $pre for URLs that are not the API.
 */
function swfl_canned_upstream( $pre, $args, $url ) {
	global $swfl_calls;
	if ( false === strpos( $url, 'swissunihockey.ch' ) ) {
		return $pre;
	}
	$swfl_calls[] = $url;
	$code         = 200;
	$body         = '{"data":{"title":"Test","headers":[{"text":"Datum"},{"text":"Heim"},{"text":"Gast"},{"text":"Resultat"}],"regions":[{"rows":[{"cells":[{"text":["01.10.2025","20:00"]},{"text":["A"]},{"text":["B"]},{"text":["1:2"]}]}]}],"slider":{}}}';
	if ( preg_match( '/team_id=(\d+)/', $url, $match ) && (int) $match[1] >= 9000000 ) {
		$code = 404;
		$body = '{"error":"Team not found"}';
	}
	// A round that does not exist for a valid group: status 200 and a table without rows.
	if ( false !== strpos( $url, 'round=777' ) ) {
		$body = '{"data":{"title":"Test","headers":[],"regions":[]}}';
	}
	return array(
		'headers'  => array(),
		'body'     => $body,
		'response' => array(
			'code'    => $code,
			'message' => '',
		),
		'cookies'  => array(),
		'filename' => null,
	);
}
add_filter( 'pre_http_request', 'swfl_canned_upstream', 10, 3 );

$swfl_actions = 0;
add_action(
	'swfl_upstream_budget_exceeded',
	function () use ( &$swfl_actions ) {
		++$swfl_actions;
	}
);

/**
 * Send a request through the REST server of this WordPress, as an anonymous visitor from an address.
 *
 * @param string      $route     Route below swfl/v1.
 * @param array       $params    Query parameters.
 * @param string      $remote    REMOTE_ADDR.
 * @param string|null $forwarded X-Forwarded-For.
 * @return array Keys status, code, headers and data.
 */
function swfl_get( $route, $params = array(), $remote = '203.0.113.50', $forwarded = null ) {
	$_SERVER['REMOTE_ADDR'] = $remote;
	unset( $_SERVER['HTTP_X_FORWARDED_FOR'] );
	if ( null !== $forwarded ) {
		$_SERVER['HTTP_X_FORWARDED_FOR'] = $forwarded;
	}
	$request = new WP_REST_Request( 'GET', '/swfl/v1/' . $route );
	$request->set_query_params( $params );
	$response = rest_do_request( $request );
	// rest_do_request() skips this filter, which adds Retry-After.
	$response = apply_filters( 'rest_post_dispatch', $response, rest_get_server(), $request );
	$data     = $response->get_data();

	return array(
		'status'  => $response->get_status(),
		'code'    => is_array( $data ) && isset( $data['code'] ) ? $data['code'] : '',
		'headers' => $response->get_headers(),
		'data'    => $data,
	);
}

/**
 * Number of rate-limit counters stored as transients.
 *
 * @return int Count.
 */
function swfl_counter_rows() {
	global $wpdb;
	return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE 'swfl\\_count\\_%\\_rl\\_%'" ); // phpcs:ignore
}

/**
 * Clear the plugin cache, also the copy of the options this PHP process keeps in memory.
 */
function swfl_clear() {
	global $wpdb;
	SWFL_Client::clear_cache();
	// The counters are rows of their own, which clearing the cache keeps.
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'swfl\\_count\\_%'" ); // phpcs:ignore
	wp_cache_flush();
}

/**
 * The counters use aligned one-minute windows: wait for the next minute when fewer seconds are left in this one.
 *
 * @param int $seconds Seconds a section needs inside one window.
 */
function swfl_wait_for_window( $seconds ) {
	if ( 60 - time() % 60 < $seconds ) {
		sleep( 61 - time() % 60 );
	}
}

// Keep the settings of the site and restore them at the end.
$swfl_saved = array();
foreach ( array( 'swfl_api_source', 'swfl_rest_rate_limit', 'swfl_upstream_budget', 'swfl_upstream_visitor_budget', 'swfl_trusted_proxies', 'swfl_actual_season' ) as $swfl_option ) {
	$swfl_saved[ $swfl_option ] = get_option( $swfl_option, null );
}
update_option( 'swfl_api_source', 'free' );
update_option( 'swfl_actual_season', 2025 );
update_option( 'swfl_rest_rate_limit', 1000 );
update_option( 'swfl_upstream_budget', 0 );
update_option( 'swfl_trusted_proxies', '' );
wp_set_current_user( 0 );
swfl_clear();

echo "Issue 113: values are validated before any upstream request\n";
swfl_wait_for_window( 15 );
$swfl_before = count( $swfl_calls );
foreach ( array(
	array( 'season', '1' ),
	array( 'season', '99999' ),
	array( 'team_id', '-5' ),
	array( 'team_id', 'abc' ),
	array( 'team_id', '10000001' ),
	array( 'group', '<b>x</b>' ),
) as $swfl_case ) {
	$swfl_params                  = array( 'team_id' => 429626, 'season' => 2025 );
	$swfl_params[ $swfl_case[0] ] = $swfl_case[1];
	$swfl_answer                  = swfl_get( 'games', $swfl_params );
	swfl_check( $swfl_case[0] . '=' . $swfl_case[1] . ' -> 400 rest_invalid_param', 400 === $swfl_answer['status'] && 'rest_invalid_param' === $swfl_answer['code'], wp_json_encode( $swfl_answer['data'] ) );
}
// WordPress asks the permission callback for the Allow header of every answer, so a rejected request counts once for the visitor.
$swfl_name = 'rl_' . substr( hash( 'sha256', SWFL_Client_Ip::to_bucket( SWFL_Client_Ip::parse_ip( '203.0.113.50' ) ) ), 0, 32 );
swfl_check( 'every rejected request counts once for the visitor (6 requests)', 6 === SWFL_Counter::get( $swfl_name ), (string) SWFL_Counter::get( $swfl_name ) );
swfl_check( 'round=1e3 on the league route -> 400 rest_invalid_param', 400 === swfl_get( 'league-games', array( 'league' => 2, 'game_class' => 11, 'round' => '1e3' ) )['status'] );
swfl_check( 'no upstream request was made', count( $swfl_calls ) === $swfl_before );
swfl_check( 'a required ID of 0 is rejected', 400 === swfl_get( 'team-games', array( 'team_id' => 0 ) )['status'] );
swfl_check( 'the values of the plugin front end are accepted (page_size=0, season=0)', 200 === swfl_get( 'team-games', array( 'team_id' => 429626, 'season' => 0, 'page_size' => 0 ) )['status'] );
swfl_check( 'a valid request still works', 200 === swfl_get( 'games', array( 'team_id' => 429626, 'season' => 2025 ) )['status'] );

echo "Issue 112: failed requests are cached briefly\n";
swfl_clear();
$swfl_calls = array();
$swfl_first = swfl_get( 'games', array( 'team_id' => 9999999, 'season' => 2025 ) );
$swfl_twice = swfl_get( 'games', array( 'team_id' => 9999999, 'season' => 2025 ) );
swfl_check( 'unknown team: 502 twice', 502 === $swfl_first['status'] && 502 === $swfl_twice['status'] );
swfl_check( 'only the first request reached the upstream API', 1 === count( $swfl_calls ), (string) count( $swfl_calls ) );
swfl_check( 'a valid request is cached as before', 200 === swfl_get( 'games', array( 'team_id' => 429626, 'season' => 2025 ) )['status'] && 200 === swfl_get( 'games', array( 'team_id' => 429626, 'season' => 2025 ) )['status'] && 2 === count( $swfl_calls ) );
swfl_clear();
swfl_get( 'games', array( 'team_id' => 9999999, 'season' => 2025 ) );
swfl_check( 'clearing the cache clears the marker too', 3 === count( $swfl_calls ) );
swfl_clear();
$swfl_calls = array();
$swfl_round = array( 'game_class' => 11, 'league' => 2, 'season' => 2025, 'group' => 'Gruppe 1', 'round' => 777 );
swfl_check( 'a round without rows is served', 200 === swfl_get( 'league-games', $swfl_round )['status'] );
swfl_get( 'league-games', $swfl_round );
swfl_check( 'the answer without rows is cached, the second request does not reach the API', 1 === count( $swfl_calls ), (string) count( $swfl_calls ) );
$swfl_left = (int) $wpdb->get_var( "SELECT MAX(option_value) FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_timeout\\_swfl\\_%' AND option_name NOT LIKE '%swfl\\_c\\_%'" ) - time(); // phpcs:ignore
swfl_check( 'and only for a short time, not for the cache lifetime of the games', $swfl_left > 0 && $swfl_left <= 300, (string) $swfl_left );

echo "Issue 115: one budget per IPv6 /64, forwarded headers only for trusted proxies\n";
swfl_clear();
update_option( 'swfl_rest_rate_limit', 3 );
swfl_wait_for_window( 15 );
$swfl_ok = array( 'team_id' => 429626, 'season' => 2025 );
$swfl_a  = swfl_get( 'games', $swfl_ok, '2001:db8:77:1::1' )['status'];
$swfl_b  = swfl_get( 'games', $swfl_ok, '2001:db8:77:1:aaaa::2' )['status'];
$swfl_c  = swfl_get( 'games', $swfl_ok, '2001:db8:77:1:bbbb::3' )['status'];
$swfl_d  = swfl_get( 'games', $swfl_ok, '2001:db8:77:1:cccc::4' );
swfl_check( 'three addresses of one /64 are served', 200 === $swfl_a && 200 === $swfl_b && 200 === $swfl_c );
swfl_check( 'the fourth gets 429 with Retry-After', 429 === $swfl_d['status'] && 'swfl_rate_limited' === $swfl_d['code'] && isset( $swfl_d['headers']['Retry-After'] ) && (int) $swfl_d['headers']['Retry-After'] >= 1, wp_json_encode( $swfl_d['headers'] ) );
swfl_check( 'another /64 has its own budget', 200 === swfl_get( 'games', $swfl_ok, '2001:db8:77:2::1' )['status'] );
swfl_check( 'two counters for two /64 prefixes', 2 === swfl_counter_rows(), (string) swfl_counter_rows() );

swfl_clear();
update_option( 'swfl_rest_rate_limit', 2 );
swfl_get( 'games', $swfl_ok, '192.0.2.10', '198.51.100.1' );
swfl_get( 'games', $swfl_ok, '192.0.2.10', '198.51.100.2' );
swfl_check( 'a forwarded header is ignored by default', 429 === swfl_get( 'games', $swfl_ok, '192.0.2.10', '198.51.100.3' )['status'] );
swfl_clear();
update_option( 'swfl_trusted_proxies', "192.0.2.0/24\n# comment" );
swfl_get( 'games', $swfl_ok, '192.0.2.10', '198.51.100.11' );
swfl_get( 'games', $swfl_ok, '192.0.2.10', '198.51.100.11' );
swfl_check( 'behind a trusted proxy every visitor has a budget', 200 === swfl_get( 'games', $swfl_ok, '192.0.2.10', '198.51.100.13' )['status'] );
swfl_check( 'a forged left hop does not help', 429 === swfl_get( 'games', $swfl_ok, '192.0.2.10', '6.6.6.6, 198.51.100.11' )['status'] );
update_option( 'swfl_trusted_proxies', '' );

echo "Issue 114: flooding with unique requests stays within the budget\n";
swfl_clear();
update_option( 'swfl_rest_rate_limit', 0 );
swfl_wait_for_window( 20 );
// Warm entries first, so the cached and the expired case can be tried while the budget is used up.
swfl_get( 'games', $swfl_ok );
swfl_get( 'games', array( 'team_id' => 429627, 'season' => 2025 ) );
$swfl_stale_key = 'swfl_' . hash( 'sha256', SWFL_Client::BASE_URLS[ SWFL_Client::SOURCE_FREE ] . 'games?season=2025&mode=team&team_id=429627' );
$swfl_entry     = get_transient( $swfl_stale_key );
swfl_check( 'the warm entry is an envelope', is_array( $swfl_entry ) && isset( $swfl_entry['swfl_entry'] ) );
$swfl_entry['fresh_until'] = time() - 10;
set_transient( $swfl_stale_key, $swfl_entry, HOUR_IN_SECONDS );

update_option( 'swfl_upstream_budget', 5 );
$swfl_calls   = array();
$swfl_actions = 0;
$swfl_status  = array();
for ( $swfl_i = 1; $swfl_i <= 40; $swfl_i++ ) {
	$swfl_answer = swfl_get( 'games', array( 'team_id' => 9000000 + $swfl_i, 'season' => 2025 ), '203.0.113.' . $swfl_i );
	$swfl_status[ $swfl_answer['status'] ] = ( isset( $swfl_status[ $swfl_answer['status'] ] ) ? $swfl_status[ $swfl_answer['status'] ] : 0 ) + 1;
	$swfl_last                             = $swfl_answer;
}
swfl_check( 'at most 5 upstream requests for 40 unique requests', 5 === count( $swfl_calls ), (string) count( $swfl_calls ) );
swfl_check( '5 answers came from the API (502), 35 were refused (503)', 5 === ( $swfl_status[502] ?? 0 ) && 35 === ( $swfl_status[503] ?? 0 ), wp_json_encode( $swfl_status ) );
swfl_check( 'the refusal is 503 swfl_budget_exceeded with Retry-After', 503 === $swfl_last['status'] && 'swfl_budget_exceeded' === $swfl_last['code'] && (int) $swfl_last['headers']['Retry-After'] >= 1 && 'no-store' === $swfl_last['headers']['Cache-Control'], wp_json_encode( $swfl_last['headers'] ) );
swfl_check( 'the refusal was announced once', 1 === $swfl_actions && is_array( get_transient( 'swfl_budget_notice' ) ), (string) $swfl_actions );
$swfl_warm = swfl_get( 'games', $swfl_ok );
swfl_check( 'a cached response is unaffected', 200 === $swfl_warm['status'] && 'public, max-age=3600' === $swfl_warm['headers']['Cache-Control'] );
$swfl_old = swfl_get( 'games', array( 'team_id' => 429627, 'season' => 2025 ) );
swfl_check( 'an expired copy is served instead of a refusal, with a short max-age', 200 === $swfl_old['status'] && 'public, max-age=60' === $swfl_old['headers']['Cache-Control'], wp_json_encode( $swfl_old['headers'] ) );
swfl_check( 'the HTML route answers 503 instead of a notice', 503 === swfl_get( 'team-games', array( 'team_id' => 9000999, 'season' => 2025 ) )['status'] );
swfl_check( 'the calendar answers 503', 503 === swfl_get( 'calendar', array( 'team_id' => 9000998, 'season' => 2025 ) )['status'] );
swfl_check( 'the upstream count did not move while refusing', 5 === count( $swfl_calls ), (string) count( $swfl_calls ) );
wp_set_current_user( 1 );
swfl_check( 'an administrator is not limited', 502 === swfl_get( 'games', array( 'team_id' => 9001001, 'season' => 2025 ) )['status'] && 6 === count( $swfl_calls ) );
wp_set_current_user( 0 );

echo "Issue 114: one or two visitors cannot use the budget up for everybody\n";
swfl_clear();
update_option( 'swfl_upstream_budget', 100 );
update_option( 'swfl_upstream_visitor_budget', 3 );
swfl_wait_for_window( 15 );
$swfl_calls  = array();
$swfl_status = array();
for ( $swfl_i = 1; $swfl_i <= 10; $swfl_i++ ) {
	$swfl_answer                           = swfl_get( 'games', array( 'team_id' => 9100000 + $swfl_i, 'season' => 2025 ), '198.51.100.77' );
	$swfl_status[ $swfl_answer['status'] ] = ( isset( $swfl_status[ $swfl_answer['status'] ] ) ? $swfl_status[ $swfl_answer['status'] ] : 0 ) + 1;
	$swfl_last                             = $swfl_answer;
}
swfl_check( 'one visitor causes at most its share of 3 upstream requests', 3 === count( $swfl_calls ), (string) count( $swfl_calls ) );
swfl_check( 'the rest is refused with 429 and Retry-After', 7 === ( $swfl_status[429] ?? 0 ) && 'swfl_budget_exceeded' === $swfl_last['code'] && (int) $swfl_last['headers']['Retry-After'] >= 1, wp_json_encode( $swfl_status ) );
swfl_check( 'another visitor is still served', 502 === swfl_get( 'games', array( 'team_id' => 9100099, 'season' => 2025 ), '198.51.100.78' )['status'] && 4 === count( $swfl_calls ) );
swfl_check( 'the visitor is listed as blocked', isset( SWFL_Rate_Limit_Log::get_entries()['4:198.51.100.77'] ) );
swfl_check( 'the site budget was not announced as exhausted', false === get_transient( 'swfl_budget_notice' ) );

// Restore.
foreach ( $swfl_saved as $swfl_option => $swfl_value ) {
	if ( null === $swfl_value ) {
		delete_option( $swfl_option );
	} else {
		update_option( $swfl_option, $swfl_value );
	}
}
swfl_clear();

echo $swfl_failures ? "\n$swfl_failures check(s) failed\n" : "\nAll integration checks passed\n";
exit( $swfl_failures ? 1 : 0 );
