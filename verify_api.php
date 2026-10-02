<?php
/**
 * Live smoke test: runs the plugin's real API client and render methods against the
 * Swiss Unihockey API, with the WordPress functions they depend on stubbed.
 *
 * Usage: php verify_api.php
 *        SWFL_API_SOURCE=partner php verify_api.php   (free is the default; partner needs the stored key and secret)
 *
 * IDs (season, league, group, club, game, team, player) are discovered from the API itself,
 * so the test doesn't rot when a season ends. Fails (exit 1) on PHP warnings/notices,
 * API errors, unexpected response shapes, or render methods that print the
 * "data could not be loaded" message.
 *
 * Dev-only: not part of the release ZIP and needs network access.
 */

// This script defines ABSPATH itself, so on a git install (no release ZIP) a web request would run it
// and trigger outgoing API requests. Abort silently unless it is run from the command line.
if ( 'cli' !== PHP_SAPI ) {
	exit;
}

define( 'ABSPATH', __DIR__ . '/' );
error_reporting( E_ALL );

// ---------------------------------------------------------------------------
// WordPress stubs (only what the client and display classes call)
// ---------------------------------------------------------------------------
class WP_Error {
	public $code;
	public $message;
	public function __construct( $code = '', $message = '' ) {
		$this->code    = $code;
		$this->message = $message;
	}
	public function get_error_message() {
		return $this->message;
	}
	public function get_error_code() {
		return $this->code;
	}
}
function wp_parse_args( $args, $defaults = array() ) {
	return array_merge( $defaults, (array) $args );
}
function wp_json_encode( $data ) {
	return json_encode( $data );
}
function wp_unique_id( $prefix = '' ) {
	static $n = 0;
	return $prefix . ( ++$n );
}
function disabled( $value, $current = true, $display = true ) {
	$out = (string) $value === (string) $current ? "disabled='disabled'" : '';
	if ( $display ) {
		echo $out;
	}
	return $out;
}
function _n( $single, $plural, $number, $domain = '' ) {
	return 1 === $number ? $single : $plural;
}
function sanitize_text_field( $t ) {
	return trim( strip_tags( (string) $t ) );
}
define( 'DAY_IN_SECONDS', 86400 );
function current_time( $type ) {
	return gmdate( 'mysql' === $type ? 'Y-m-d H:i:s' : $type );
}
function absint( $n ) {
	return abs( (int) $n );
}

function is_wp_error( $thing ) {
	return $thing instanceof WP_Error;
}
// The stubs below take the same parameters as the WordPress functions, so static analysis does not flag the real calls in the plugin.
function apply_filters( $hook_name, $value, ...$args ) {
	return $value;
}
function add_query_arg( $key, $value = false, $url = false ) {
	if ( is_array( $key ) ) {
		$args = $key;
		$url  = $value;
	} else {
		$args = array( $key => $value );
	}
	return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . http_build_query( $args );
}
function get_transient( $transient ) {
	return $GLOBALS['sfa_transients'][ $transient ] ?? false;
}
function set_transient( $transient, $value, $expiration = 0 ) {
	$GLOBALS['sfa_transients'][ $transient ] = $value;
	return true;
}
function wp_remote_get( $url, $args = array() ) {
	$ctx  = stream_context_create( array(
		'http' => array(
			'method'        => 'GET',
			'timeout'       => 20,
			'ignore_errors' => true,
			'header'        => "User-Agent: SwissFloorballApiSmokeTest/2.0\r\n",
		),
	) );
	$body = @file_get_contents( $url, false, $ctx );
	if ( false === $body ) {
		return new WP_Error( 'http_request_failed', 'Request failed: ' . $url );
	}
	$code = 0;
	if ( ! empty( $http_response_header[0] ) && preg_match( '#\s(\d{3})\s#', $http_response_header[0], $m ) ) {
		$code = (int) $m[1];
	}
	return array( 'code' => $code, 'body' => $body );
}
function wp_remote_retrieve_response_code( $r ) {
	return $r['code'];
}
function wp_remote_retrieve_body( $r ) {
	return $r['body'];
}
function get_option( $name, $default = false ) {
	$options = array(
		'swissfloorball_api_source' => getenv( 'SWFL_API_SOURCE' ) ? getenv( 'SWFL_API_SOURCE' ) : 'free',
	);
	return $options[ $name ] ?? $default;
}
function admin_url( $path = '' ) {
	return 'http://localhost/wp-admin/' . $path;
}
function wp_kses( $html, $allowed = array() ) {
	return $html;
}
function plugin_dir_path( $file ) {
	return trailingslashit( dirname( $file ) );
}
function trailingslashit( $s ) {
	return rtrim( $s, '/' ) . '/';
}
function rest_url( $path = '' ) {
	return 'http://localhost/wp-json/' . $path;
}
function esc_html( $t ) {
	return htmlspecialchars( (string) $t, ENT_QUOTES, 'UTF-8' );
}
function esc_attr( $t ) {
	return esc_html( $t );
}
function esc_url( $t, $protocols = null, $context = 'display' ) {
	return esc_html( $t );
}
function __( $t, $d = '' ) {
	return $t;
}
function esc_html__( $t, $d = '' ) {
	return esc_html( $t );
}
function esc_attr__( $t, $d = '' ) {
	return esc_attr( $t );
}
function esc_html_e( $t, $d = '' ) {
	echo esc_html( $t );
}
function esc_attr_e( $t, $d = '' ) {
	echo esc_attr( $t );
}
function _e( $t, $d = '' ) {
	echo $t;
}

require_once __DIR__ . '/includes/class-floorball-api-for-swiss-unihockey-client.php';
require_once __DIR__ . '/includes/class-floorball-api-for-swiss-unihockey-display.php';
require_once __DIR__ . '/includes/class-floorball-api-for-swiss-unihockey-widgets.php';
require_once __DIR__ . '/includes/class-floorball-api-for-swiss-unihockey-icons.php';

// ---------------------------------------------------------------------------
// Tiny test harness
// ---------------------------------------------------------------------------
$failures = array();
$php_diag = array();

set_error_handler( function ( $no, $msg, $file, $line ) use ( &$php_diag ) {
	$php_diag[] = basename( $file ) . ":$line $msg";
	return true;
} );

function check( $name, callable $fn ) {
	global $failures, $php_diag;
	$php_diag = array();
	try {
		$fn();
		if ( $php_diag ) {
			throw new RuntimeException( 'PHP-Meldung: ' . implode( ' | ', array_unique( $php_diag ) ) );
		}
		echo "  ok    $name\n";
	} catch ( Throwable $e ) {
		$failures[] = $name;
		echo "  FAIL  $name\n        " . $e->getMessage() . "\n";
	}
}

function ensure( $cond, $msg ) {
	if ( ! $cond ) {
		throw new RuntimeException( $msg );
	}
}

/** Run a render method and return its HTML; fails on the plugin's error message or empty output. */
function render( callable $fn ) {
	ob_start();
	try {
		$fn();
	} finally {
		$html = ob_get_clean();
	}
	ensure( '' !== trim( $html ), 'leere Ausgabe' );
	ensure( false === strpos( $html, 'Daten konnten nicht geladen werden' ), 'Render-Methode meldet "Daten konnten nicht geladen werden"' );
	return $html;
}

/** Recursively collect link ids for a given page name, e.g. game_detail. */
function find_link_ids( $node, $page, &$out ) {
	if ( is_array( $node ) ) {
		if ( ( $node['page'] ?? null ) === $page && ! empty( $node['ids'][0] ) ) {
			$out[] = $node['ids'][0];
		}
		foreach ( $node as $child ) {
			find_link_ids( $child, $page, $out );
		}
	}
}

$client = new Swiss_Floorball_API_Client();

// ---------------------------------------------------------------------------
// 1. Raw API shapes (what the render methods rely on)
// ---------------------------------------------------------------------------
echo "API-Antworten\n";
$ctx = array();

foreach ( array( 'seasons', 'leagues', 'clubs' ) as $endpoint ) {
	check( "$endpoint: entries[] mit text + set_in_context", function () use ( $client, $endpoint, &$ctx ) {
		$r = $client->fetch_data( $endpoint );
		if ( is_wp_error( $r ) && 'swfl_endpoint_unavailable' === $r->get_error_code() ) {
			return; // Expected on the free API: the client reports the gated endpoint instead of failing.
		}
		ensure( ! is_wp_error( $r ), is_wp_error( $r ) ? $r->get_error_message() : '' );
		ensure( ! empty( $r['entries'] ), 'entries fehlt/leer' );
		ensure( isset( $r['entries'][0]['text'], $r['entries'][0]['set_in_context'] ), 'Eintrag ohne text/set_in_context' );
		$ctx[ $endpoint ] = $r['entries'];
	} );
}

// Previous season: complete, so data is stable. Falls back to the first one.
$season     = $ctx['seasons'][1]['set_in_context']['season'] ?? ( $ctx['seasons'][0]['set_in_context']['season'] ?? null );
// The free API has no leagues endpoint, so fall back to a known league (2, class 11, "Gruppe 1").
$league     = $ctx['leagues'][0]['set_in_context']['league'] ?? 2;
$game_class = $ctx['leagues'][0]['set_in_context']['game_class'] ?? 11;
$club_id    = $ctx['clubs'][0]['set_in_context']['club_id'] ?? null;
$group      = 'Gruppe 1';
$game_id    = null;
$team_id    = null;
echo "  Kontext: season=$season league=$league game_class=$game_class club=$club_id\n";

check( 'groups: Gruppe für ermittelte Liga', function () use ( $client, $season, $league, $game_class, &$group ) {
	$r = $client->fetch_data( 'groups', array( 'season' => $season, 'league' => $league, 'game_class' => $game_class, 'format' => 'dropdown' ) );
	if ( is_wp_error( $r ) && 'swfl_endpoint_unavailable' === $r->get_error_code() ) {
		return; // Expected on the free API; the default group stays in place.
	}
	ensure( ! is_wp_error( $r ) && ! empty( $r['entries'][0]['set_in_context']['group'] ), 'keine Gruppe gefunden' );
	$group = $r['entries'][0]['set_in_context']['group'];
} );

check( 'games: regions[0].rows[] Raster + game_detail-Link', function () use ( $client, $season, $club_id, &$game_id ) {
	$r = $client->fetch_data( 'games', array( 'mode' => 'club', 'club_id' => $club_id, 'season' => $season ) );
	ensure( ! is_wp_error( $r ), is_wp_error( $r ) ? $r->get_error_message() : '' );
	ensure( isset( $r['data']['regions'][0]['rows'] ), 'data.regions[0].rows fehlt' );
	ensure( isset( $r['data']['regions'][0]['rows'][0]['cells'] ), 'rows[0].cells fehlt' );
	$ids = array();
	find_link_ids( $r, 'game_detail', $ids );
	ensure( $ids, 'kein game_detail-Link in der Antwort' );
	$game_id = $ids[0];
} );

check( 'rankings: regions[0].rows[] + team_detail-Link', function () use ( $client, $season, $league, $game_class, &$group, &$team_id ) {
	$r = $client->fetch_data( 'rankings', array( 'season' => $season, 'league' => $league, 'game_class' => $game_class, 'group' => $group ) );
	ensure( ! is_wp_error( $r ), is_wp_error( $r ) ? $r->get_error_message() : '' );
	ensure( isset( $r['data']['regions'][0]['rows'] ), 'data.regions[0].rows fehlt' );
	$ids = array();
	find_link_ids( $r, 'team_detail', $ids );
	ensure( $ids, 'kein team_detail-Link in der Antwort' );
	$team_id = $ids[0];
} );

check( 'clubs/{id}/statistics: Struktur', function () use ( $client, $club_id ) {
	$r = $client->fetch_data( 'clubs/' . $club_id . '/statistics' );
	ensure( ! is_wp_error( $r ) && isset( $r['data']['regions'][0]['rows'] ), 'Struktur unerwartet' );
} );

check( 'Client: HTTP-Fehler liefert WP_Error statt Daten', function () use ( $client ) {
	$r = $client->fetch_data( 'rankings', array( 'season' => 2025, 'league' => 2, 'game_class' => 11, 'group' => 'Gibt es nicht' ) );
	ensure( is_wp_error( $r ), 'WP_Error erwartet' );
} );

check( 'Client: zweiter Aufruf kommt aus dem Cache', function () use ( $client ) {
	$before = count( $GLOBALS['sfa_transients'] ?? array() );
	$client->fetch_data( 'seasons' );
	ensure( count( $GLOBALS['sfa_transients'] ?? array() ) === $before, 'Cache-Eintrag wurde neu angelegt' );
} );

// ---------------------------------------------------------------------------
// 2. Render methods (same code as shortcodes and admin pages)
// ---------------------------------------------------------------------------
echo "Render-Methoden\n";
$D = 'Swiss_Floorball_API_Display';

$renders = array(
	'render_seasons'          => function () use ( $D ) { $D::render_seasons(); },
	'render_leagues'          => function () use ( $D ) { $D::render_leagues(); },
	'render_clubs'            => function () use ( $D ) { $D::render_clubs(); },
	'render_cups'             => function () use ( $D ) { $D::render_cups(); },
	'render_teams'            => function () use ( $D ) { $D::render_teams(); },
	'render_national_players' => function () use ( $D ) { $D::render_national_players(); },
	'render_groups'           => function () use ( $D, $season, $league, $game_class ) { $D::render_groups( $season, $league, $game_class ); },
	'render_rankings'         => function () use ( $D, $season, $league, $game_class, &$group ) { $D::render_rankings( $season, $league, $game_class, $group ); },
	'render_topscorers'       => function () use ( $D, $season, $league, $game_class, &$group ) { $D::render_topscorers( $season, $league, $game_class, $group ); },
	'render_club_teams'       => function () use ( $D, $club_id ) { $D::render_club_teams( $club_id ); },
	'render_club_teams_pub'   => function () use ( $D, $club_id ) { $D::render_club_teams_pub( $club_id ); },
	'render_club_games'       => function () use ( $D, $club_id, $season ) { $D::render_club_games( $club_id, $season ); },
	'render_club_games_cards' => function () use ( $D, $club_id, $season ) { $D::render_club_games_cards( $club_id, $season ); },
	'render_calendars (Club)' => function () use ( $D, $club_id, $season ) { $D::render_calendars( null, $club_id, $season ); },
	'render_game_details_table' => function () use ( $D, &$game_id ) { $D::render_game_details_table( $game_id ); },
	'render_game_events'      => function () use ( $D, &$game_id ) { $D::render_game_events( $game_id ); },
);
foreach ( $renders as $name => $fn ) {
	check( $name, function () use ( $fn ) {
		$html = render( $fn );
		ensure( false !== strpos( $html, '<' ), 'kein HTML in der Ausgabe' );
	} );
}

check( 'render_team_games', function () use ( $D, &$team_id, $season ) {
	ensure( $team_id, 'keine Team-ID ermittelt' );
	render( function () use ( $D, $team_id, $season ) { $D::render_team_games( $team_id, $season ); } );
} );

// Widgets modelled on the official web components (IDs from their README examples).
$W = 'Swiss_Floorball_API_Widgets';
$widgets = array(
	'widget league-games'  => array( function () use ( $W, $season, $league, $game_class, $group ) { $W::render_league_games( $game_class, $league, $season, $group ); }, 'data-sfa-widget="league"', '<table' ),
	'widget club-team-games' => array( function () use ( $W, $season ) { $W::render_club_team_games( 372, $season, 6 ); }, 'data-sfa-widget="team-select"', '<option' ),
	'widget team-games'    => array( function () use ( $W, $season ) { $W::render_team_games( 429626, $season, 5 ); }, 'data-sfa-widget="pager"', '<table' ),
	'widget club-games'    => array( function () use ( $W, $season, $club_id ) { $W::render_club_games( $club_id, $season ); }, 'data-sfa-widget="week"', '<table' ),
	'widget ranking'       => array( function () use ( $W, $season, $league, $game_class, $group ) { $W::render_ranking( $season, $league, $game_class, $group ); }, 'data-sfa-widget="ranking"', '<table' ),
	'widget mobiliar-topscorer' => array( function () use ( $W, $season ) { $W::render_mobiliar_topscorers( 463845, $season ); }, 'data-sfa-widget="topscorers"', 'sfa-topscorers' ),
);
foreach ( $widgets as $name => $def ) {
	check( $name, function () use ( $def ) {
		$html = render( $def[0] );
		ensure( false !== strpos( $html, $def[1] ), 'Widget-Container fehlt' );
		ensure( false !== strpos( $html, $def[2] ), 'erwartetes Markup fehlt: ' . $def[2] );
	} );
}

// render_club_games_callout is intentionally not asserted:
// the callout renders nothing when no game is upcoming.

// ---------------------------------------------------------------------------
echo "\n";
if ( $failures ) {
	echo count( $failures ) . ' Test(s) fehlgeschlagen: ' . implode( ', ', $failures ) . "\n";
	exit( 1 );
}
echo "Alle Smoke-Tests bestanden.\n";
