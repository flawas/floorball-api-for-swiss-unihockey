<?php
/**
 * Offline test bootstrap: stubs the few WordPress functions the plugin classes use and loads them.
 *
 * Dev-only (scripts/ is not part of the release ZIP). Needs PHP 7.4+, no WordPress, no network, no Composer.
 *
 * @package SWFL
 */

if ( 'cli' !== PHP_SAPI ) {
	exit;
}

define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
error_reporting( E_ALL );
// A warning, notice or deprecation raised by the code under test fails the test instead of only being printed.
set_error_handler(
	function ( $severity, $message, $file, $line ) {
		if ( ! ( error_reporting() & $severity ) ) {
			return false;
		}
		throw new ErrorException( $message . ' in ' . basename( $file ) . ':' . $line, 0, $severity, $file, $line );
	}
);

foreach ( array(
	'MINUTE_IN_SECONDS' => 60,
	'HOUR_IN_SECONDS'   => 3600,
	'DAY_IN_SECONDS'    => 86400,
) as $swfl_const => $swfl_value ) {
	define( $swfl_const, $swfl_value );
}

// ---------------------------------------------------------------------------
// WordPress classes
// ---------------------------------------------------------------------------
class WP_Error {
	public $errors     = array();
	public $error_data = array();
	public function __construct( $code = '', $message = '', $data = '' ) {
		if ( empty( $code ) ) {
			return;
		}
		$this->errors[ $code ][] = $message;
		if ( ! empty( $data ) ) {
			$this->error_data[ $code ] = $data;
		}
	}
	public function get_error_code() {
		$codes = array_keys( $this->errors );
		return $codes ? $codes[0] : '';
	}
	public function get_error_message( $code = '' ) {
		$code = $code ? $code : $this->get_error_code();
		return isset( $this->errors[ $code ][0] ) ? $this->errors[ $code ][0] : '';
	}
	public function get_error_data( $code = '' ) {
		$code = $code ? $code : $this->get_error_code();
		return isset( $this->error_data[ $code ] ) ? $this->error_data[ $code ] : null;
	}
	public function add_data( $data, $code = '' ) {
		$code                      = $code ? $code : $this->get_error_code();
		$this->error_data[ $code ] = $data;
	}
}
class WP_REST_Request {
	public $params;
	public function __construct( $params = array() ) {
		$this->params = $params;
	}
	public function get_param( $key ) {
		return isset( $this->params[ $key ] ) ? $this->params[ $key ] : null;
	}
}
class WP_REST_Response {
	public $data;
	public $status;
	public $headers = array();
	public function __construct( $data = null, $status = 200 ) {
		$this->data   = $data;
		$this->status = $status;
	}
	public function header( $key, $value ) {
		$this->headers[ $key ] = $value;
	}
	public function get_data() {
		return $this->data;
	}
}

// ---------------------------------------------------------------------------
// Mutable fake state. swfl_test_reset() restores the defaults before every test.
// ---------------------------------------------------------------------------
function swfl_test_reset() {
	$GLOBALS['swfl_t'] = array(
		'options'    => array(),
		'transients' => array(),   // Key => array( value, absolute expiry or 0, ttl ).
		'writes'     => array(),   // Keys passed to set_transient(), in order.
		'cache'      => array(),   // "group:key" => array( value, ttl ).
		'process'    => false,     // Whether this process keeps WordPress' in-process copy of the transients it has read or written.
		'local'      => array(),   // That copy: transient name => value.
		'notoptions' => array(),   // Names this process has found missing.
		'rows'       => array(),   // Option rows written by SQL (SWFL_Test_Wpdb): name => value.
		'db_fails'   => false,     // Makes every SQL statement fail.
		'db_writes'  => 0,         // INSERT and DELETE statements sent.
		'ext_cache'  => false,     // What wp_using_ext_object_cache() reports.
		'incr_fails' => false,     // Makes wp_cache_incr() return false.
		'filters'    => array(),
		'actions'    => array(),   // Hook => number of calls.
		'errors'     => array(),   // add_settings_error() calls.
		'http_queue' => array(),   // Scripted wp_remote_get() answers.
		'http_calls' => array(),   // URLs passed to wp_remote_get() and wp_remote_post().
		'user_can'   => false,
		'year'       => gmdate( 'Y' ),
	);
	unset( $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_X_FORWARDED_FOR'] );
	if ( class_exists( 'SWFL_Upstream_Budget', false ) ) {
		SWFL_Upstream_Budget::reset();
	}
}
swfl_test_reset();

// ---------------------------------------------------------------------------
// WordPress functions
// ---------------------------------------------------------------------------
function is_wp_error( $thing ) {
	return $thing instanceof WP_Error;
}
function absint( $value ) {
	return abs( (int) $value );
}
function sanitize_text_field( $text ) {
	return trim( strip_tags( (string) $text ) );
}
function wp_unslash( $value ) {
	return is_string( $value ) ? stripslashes( $value ) : $value;
}
function wp_json_encode( $data ) {
	return json_encode( $data );
}
function wp_parse_args( $args, $defaults = array() ) {
	return array_merge( $defaults, (array) $args );
}
function current_user_can( $capability ) {
	return $GLOBALS['swfl_t']['user_can'];
}
function current_time( $type ) {
	return 'Y' === $type ? (string) $GLOBALS['swfl_t']['year'] : gmdate( 'mysql' === $type ? 'Y-m-d H:i:s' : $type );
}
function get_option( $name, $default = false ) {
	return array_key_exists( $name, $GLOBALS['swfl_t']['options'] ) ? $GLOBALS['swfl_t']['options'][ $name ] : $default;
}
function update_option( $name, $value ) {
	$GLOBALS['swfl_t']['options'][ $name ] = $value;
	return true;
}
function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	$GLOBALS['swfl_t']['filters'][ $hook ][] = $callback;
	return true;
}
function apply_filters( $hook, $value, ...$args ) {
	if ( ! empty( $GLOBALS['swfl_t']['filters'][ $hook ] ) ) {
		foreach ( $GLOBALS['swfl_t']['filters'][ $hook ] as $callback ) {
			$value = call_user_func( $callback, $value, ...$args );
		}
	}
	return $value;
}
function do_action( $hook, ...$args ) {
	$GLOBALS['swfl_t']['actions'][ $hook ] = ( isset( $GLOBALS['swfl_t']['actions'][ $hook ] ) ? $GLOBALS['swfl_t']['actions'][ $hook ] : 0 ) + 1;
}
function add_settings_error( $setting, $code, $message, $type = 'error' ) {
	$GLOBALS['swfl_t']['errors'][] = array( $setting, $code, $message, $type );
}
function add_query_arg( $key, $value = false, $url = false ) {
	if ( is_array( $key ) ) {
		$args = $key;
		$url  = $value;
	} else {
		$args = array( $key => $value );
	}
	// Like WordPress, the values are not encoded: callers have to do it themselves.
	$pairs = array();
	foreach ( $args as $name => $arg_value ) {
		$pairs[] = $name . '=' . $arg_value;
	}
	return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . implode( '&', $pairs );
}

// Transients keep their lifetime. swfl_test_expire() ages one by hand, as the code under test uses the real clock.
// $GLOBALS['swfl_t']['transients'] is the database that every request shares. With 'process' on, a transient that
// was read or written is also remembered per process, like WordPress' in-process options cache: a row that
// another request stored (swfl_test_db_write()) stays invisible until wp_cache_delete() drops the copy.
function swfl_test_db_read( $key ) {
	$stored = $GLOBALS['swfl_t']['transients'];
	if ( ! isset( $stored[ $key ] ) ) {
		return false;
	}
	if ( $stored[ $key ][1] && $stored[ $key ][1] <= time() ) {
		unset( $GLOBALS['swfl_t']['transients'][ $key ] );
		return false;
	}
	return $stored[ $key ][0];
}
function get_transient( $key ) {
	$t = &$GLOBALS['swfl_t'];
	if ( ! $t['process'] ) {
		return swfl_test_db_read( $key );
	}
	if ( isset( $t['notoptions'][ $key ] ) ) {
		return false;
	}
	if ( ! array_key_exists( $key, $t['local'] ) ) {
		$value = swfl_test_db_read( $key );
		if ( false === $value ) {
			$t['notoptions'][ $key ] = true;
			return false;
		}
		$t['local'][ $key ] = $value;
	}
	return $t['local'][ $key ];
}
function set_transient( $key, $value, $ttl = 0 ) {
	$GLOBALS['swfl_t']['writes'][]           = $key;
	$GLOBALS['swfl_t']['transients'][ $key ] = array( $value, $ttl ? time() + $ttl : 0, $ttl );
	if ( $GLOBALS['swfl_t']['process'] ) {
		$GLOBALS['swfl_t']['local'][ $key ] = $value;
		unset( $GLOBALS['swfl_t']['notoptions'][ $key ] );
	}
	return true;
}
function delete_transient( $key ) {
	unset( $GLOBALS['swfl_t']['transients'][ $key ], $GLOBALS['swfl_t']['local'][ $key ] );
	return true;
}
function swfl_test_expire( $key ) {
	$GLOBALS['swfl_t']['transients'][ $key ][1] = time() - 1;
}
// A write by another request: it reaches the database but not this process' copy.
function swfl_test_db_write( $key, $value, $ttl ) {
	$GLOBALS['swfl_t']['transients'][ $key ] = array( $value, time() + $ttl, $ttl );
}

// The object cache: core reports null (not false) without a persistent drop-in, so the fake does too.
function wp_using_ext_object_cache( $using = null ) {
	return $GLOBALS['swfl_t']['ext_cache'] ? true : null;
}
function wp_cache_add( $key, $data, $group = '', $expire = 0 ) {
	$id = $group . ':' . $key;
	if ( isset( $GLOBALS['swfl_t']['cache'][ $id ] ) ) {
		return false;
	}
	$GLOBALS['swfl_t']['cache'][ $id ] = array( $data, $expire );
	return true;
}
function wp_cache_incr( $key, $offset = 1, $group = '' ) {
	$id = $group . ':' . $key;
	if ( $GLOBALS['swfl_t']['incr_fails'] || ! isset( $GLOBALS['swfl_t']['cache'][ $id ] ) ) {
		return false;
	}
	$GLOBALS['swfl_t']['cache'][ $id ][0] += $offset;
	return $GLOBALS['swfl_t']['cache'][ $id ][0];
}
function wp_cache_get( $key, $group = '' ) {
	$id = $group . ':' . $key;
	return isset( $GLOBALS['swfl_t']['cache'][ $id ] ) ? $GLOBALS['swfl_t']['cache'][ $id ][0] : false;
}
// Group "options" is the in-process copy of the transients (see get_transient()): _transient_<name> holds a
// value, notoptions the names found missing. The timeout row is part of the same entry in the fake.
function wp_cache_delete( $key, $group = '' ) {
	if ( 'options' !== $group ) {
		unset( $GLOBALS['swfl_t']['cache'][ $group . ':' . $key ] );
	} elseif ( 'notoptions' === $key ) {
		$GLOBALS['swfl_t']['notoptions'] = array();
	} elseif ( 0 === strpos( $key, '_transient_' ) && 0 !== strpos( $key, '_transient_timeout_' ) ) {
		unset( $GLOBALS['swfl_t']['local'][ substr( $key, strlen( '_transient_' ) ) ] );
	}
	return true;
}

// The database behind SWFL_Counter: the statements it sends, on rows in
// $GLOBALS['swfl_t']['rows']. 'db_fails' makes every statement fail, as on a database without that syntax.
class SWFL_Test_Wpdb {
	public $options = 'wp_options';
	public function esc_like( $text ) {
		return addcslashes( $text, '_%\\' );
	}
	public function prepare( $query, ...$args ) {
		return array( $query, $args );
	}
	public function query( $prepared ) {
		list( $query, $args ) = $prepared;
		$rows                 = &$GLOBALS['swfl_t']['rows'];
		if ( $GLOBALS['swfl_t']['db_fails'] ) {
			return false;
		}
		++$GLOBALS['swfl_t']['db_writes'];
		if ( 0 === strpos( $query, 'INSERT INTO' ) && false !== strpos( $query, 'option_value + 1' ) ) {
			$new                = ! isset( $rows[ $args[0] ] );
			$rows[ $args[0] ]   = $new ? 1 : $rows[ $args[0] ] + 1;
			return $new ? 1 : 2;
		}
		if ( 0 === strpos( $query, 'DELETE FROM' ) ) {
			// A LIKE prefix, then option_name < or <> the second value.
			if ( 1 !== preg_match( '/option_name LIKE %s AND option_name (<|<>) %s$/', $query, $operator ) ) {
				throw new RuntimeException( 'Unexpected query: ' . $query );
			}
			$prefix  = stripslashes( substr( $args[0], 0, -1 ) );
			$deleted = 0;
			foreach ( array_keys( $rows ) as $name ) {
				$other = '<' === $operator[1] ? strcmp( $name, $args[1] ) < 0 : $name !== $args[1];
				if ( 0 === strpos( $name, $prefix ) && $other ) {
					unset( $rows[ $name ] );
					++$deleted;
				}
			}
			return $deleted;
		}
		throw new RuntimeException( 'Unexpected query: ' . $query );
	}
	public function get_var( $prepared ) {
		list( , $args ) = $prepared;
		if ( $GLOBALS['swfl_t']['db_fails'] ) {
			return null;
		}
		return isset( $GLOBALS['swfl_t']['rows'][ $args[0] ] ) ? (string) $GLOBALS['swfl_t']['rows'][ $args[0] ] : null;
	}
}
$GLOBALS['wpdb'] = new SWFL_Test_Wpdb();

// HTTP: scripted answers ( array( 'code' => 200, 'body' => '...' ) or a WP_Error ), every call is recorded.
function swfl_test_http( ...$responses ) {
	foreach ( $responses as $response ) {
		$GLOBALS['swfl_t']['http_queue'][] = $response;
	}
}
function wp_remote_get( $url, $args = array() ) {
	$GLOBALS['swfl_t']['http_calls'][] = $url;
	if ( $GLOBALS['swfl_t']['http_queue'] ) {
		return array_shift( $GLOBALS['swfl_t']['http_queue'] );
	}
	return array(
		'code' => 200,
		'body' => '{"data":{"regions":[{"rows":[{"cells":[{"text":["a"]}]}]}]}}',
	);
}
function wp_remote_post( $url, $args = array() ) {
	$GLOBALS['swfl_t']['http_calls'][] = $url;
	return array(
		'code' => 200,
		'body' => '{"auth_token":"token"}',
	);
}
function wp_remote_retrieve_response_code( $response ) {
	return $response['code'];
}
function wp_remote_retrieve_body( $response ) {
	return $response['body'];
}

// Output helpers used by the render methods.
function esc_html( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}
function esc_attr( $text ) {
	return esc_html( $text );
}
function esc_url( $text ) {
	return esc_html( $text );
}
function __( $text, $domain = '' ) {
	return $text;
}
function esc_html__( $text, $domain = '' ) {
	return esc_html( $text );
}
function esc_attr__( $text, $domain = '' ) {
	return esc_attr( $text );
}
function esc_html_e( $text, $domain = '' ) {
	echo esc_html( $text );
}
function wp_kses( $html, $allowed = array() ) {
	return $html;
}
function disabled( $value, $current = true, $display = true ) {
	return '';
}
function _n( $single, $plural, $number, $domain = '' ) {
	return 1 === $number ? $single : $plural;
}
function admin_url( $path = '' ) {
	return 'http://localhost/wp-admin/' . $path;
}

// ---------------------------------------------------------------------------
// Plugin classes
// ---------------------------------------------------------------------------
foreach ( array( 'client', 'display', 'icons', 'widgets', 'calendar', 'rest', 'table-data', 'request-guard', 'counter', 'client-ip', 'cache-entry', 'upstream-budget', 'warmup', 'rate-limit-log', 'php-requirement' ) as $swfl_class_file ) {
	require_once ABSPATH . 'includes/class-swfl-' . $swfl_class_file . '.php';
}
require_once ABSPATH . 'admin/class-swfl-admin-settings.php';

// ---------------------------------------------------------------------------
// Tiny runner
// ---------------------------------------------------------------------------
$GLOBALS['swfl_tests']    = array();
$GLOBALS['swfl_failures'] = 0;

/**
 * Register a test. The state is reset before it runs.
 *
 * @param string   $name Test name.
 * @param callable $test Test body; throws on failure.
 */
function swfl_test( $name, $test ) {
	$GLOBALS['swfl_tests'][ $name ] = $test;
}

/**
 * Assert that two values are identical.
 *
 * @param mixed  $expected Expected value.
 * @param mixed  $actual   Actual value.
 * @param string $message  Optional context.
 */
function swfl_same( $expected, $actual, $message = '' ) {
	if ( $expected !== $actual ) {
		throw new RuntimeException( ( $message ? $message . ': ' : '' ) . 'expected ' . var_export( $expected, true ) . ', got ' . var_export( $actual, true ) );
	}
}

/**
 * Wait for the next minute when the current one ends within two seconds.
 *
 * The counters use the real clock and aligned one-minute windows, so a test that counts and then checks the count
 * would fail if a window boundary fell between the two.
 */
function swfl_test_wait_for_window() {
	if ( MINUTE_IN_SECONDS - time() % MINUTE_IN_SECONDS <= 2 ) {
		sleep( 3 );
	}
}

/**
 * Run all registered tests and exit with 1 when one failed.
 */
function swfl_run() {
	if ( ! $GLOBALS['swfl_tests'] ) {
		echo "No tests found\n";
		exit( 1 );
	}
	foreach ( $GLOBALS['swfl_tests'] as $name => $test ) {
		swfl_test_wait_for_window();
		swfl_test_reset();
		try {
			$test();
			echo "  ok    $name\n";
		} catch ( Throwable $e ) {
			++$GLOBALS['swfl_failures'];
			echo "  FAIL  $name\n        " . $e->getMessage() . "\n";
		}
	}
	echo $GLOBALS['swfl_failures'] ? $GLOBALS['swfl_failures'] . " test(s) failed\n" : 'All ' . count( $GLOBALS['swfl_tests'] ) . " tests passed\n";
	exit( $GLOBALS['swfl_failures'] ? 1 : 0 );
}
