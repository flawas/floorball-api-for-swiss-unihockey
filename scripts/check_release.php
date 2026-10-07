<?php
/**
 * Release consistency checks (no WordPress needed).
 *
 * Usage: php scripts/check_release.php [expected-version]
 *
 * Verifies that the plugin header, SWFL_VERSION, the README "Stable tag"
 * and the top changelog entry agree, that every PHP file has an ABSPATH guard, and that
 * translation functions use the correct text domain. Exit code 1 on any failure.
 */

$root     = dirname( __DIR__ );
$errors   = array();
$expected = $argv[1] ?? null;
$expected = $expected !== null ? ltrim( $expected, 'v' ) : null;

$main   = file_get_contents( $root . '/swiss-floorball-api.php' );
$readme = file_get_contents( $root . '/README.md' );

preg_match( '/^\s*\*\s*Version:\s*(\S+)/m', $main, $m );
$header = $m[1] ?? null;
preg_match( "/define\(\s*'SWFL_VERSION',\s*'([^']+)'/", $main, $m );
$const = $m[1] ?? null;
preg_match( '/\*\*Stable tag:\*\*\s*(\S+)/', $readme, $m );
$stable = $m[1] ?? null;
preg_match( '/^### (\d+\.\d+\.\d+) \((\d{4}-\d{2}-\d{2})\)/m', $readme, $m );
$changelog = $m[1] ?? null;

$versions = array(
	'Plugin header'          => $header,
	'Konstante'              => $const,
	'README Stable tag'      => $stable,
	'Neuester Changelog'     => $changelog,
);
foreach ( $versions as $label => $v ) {
	if ( ! $v || ! preg_match( '/^\d+\.\d+\.\d+$/', $v ) ) {
		$errors[] = "$label: keine gültige Version gefunden";
	} elseif ( $v !== $header ) {
		$errors[] = "$label ($v) weicht vom Plugin-Header ($header) ab";
	}
}
if ( $expected && $header !== $expected ) {
	$errors[] = "Version ($header) entspricht nicht der erwarteten Version ($expected)";
}

// Release gate only: version must be higher than the latest tag (SFA_REQUIRE_NEW_VERSION=1).
$tags = trim( (string) shell_exec( 'git -C ' . escapeshellarg( $root ) . ' tag --list "v*" 2>/dev/null' ) );
if ( getenv( 'SFA_REQUIRE_NEW_VERSION' ) === '1' && $tags !== '' && $header ) {
	$list = array_map( fn( $t ) => ltrim( $t, 'v' ), explode( "\n", $tags ) );
	usort( $list, 'version_compare' );
	$latest = end( $list );
	if ( version_compare( $header, $latest, '<=' ) ) {
		$errors[] = "Version $header ist nicht höher als der neueste Tag v$latest";
	}
}

$php_files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) );
foreach ( $php_files as $file ) {
	$path = $file->getPathname();
	$rel  = substr( $path, strlen( $root ) + 1 );
	if ( substr( $path, -4 ) !== '.php' || preg_match( '#^(\.git|\.github|scripts|graphify-out|vendor|verify_api\.php)#', $rel ) ) {
		continue;
	}
	$src = file_get_contents( $path );
	if ( ! in_array( basename( $path ), array( 'index.php', 'uninstall.php' ), true ) && strpos( $src, "defined( 'ABSPATH' )" ) === false && strpos( $src, "defined('ABSPATH')" ) === false ) {
		$errors[] = "$rel: ABSPATH-Guard fehlt";
	}
	if ( preg_match_all( "/\b(?:__|_e|_x|esc_html__|esc_html_e|esc_attr__|esc_attr_e)\(\s*'[^']*'\s*,\s*'([^']+)'/", $src, $mm ) ) {
		foreach ( array_unique( $mm[1] ) as $domain ) {
			if ( $domain !== 'swiss-floorball-api' ) {
				$errors[] = "$rel: falsche Textdomain '$domain'";
			}
		}
	}
}

if ( $errors ) {
	fwrite( STDERR, "Release-Check FEHLGESCHLAGEN:\n - " . implode( "\n - ", $errors ) . "\n" );
	exit( 1 );
}
echo "Release-Check OK (Version $header)\n";
