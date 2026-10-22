<?php
/**
 * Tests of SWFL_Client_Ip: buckets, CIDR ranges, X-Forwarded-For walk and the trusted proxies setting.
 *
 * @package SWFL
 */

/**
 * Bucket of an address text.
 *
 * @param string $text Address as sent by a web server.
 * @return string Bucket.
 */
function swfl_test_bucket( $text ) {
	return SWFL_Client_Ip::to_bucket( SWFL_Client_Ip::parse_ip( $text ) );
}

swfl_test(
	'client ip: IPv4 by address, IPv6 by /64, mapped addresses are IPv4, junk shares one bucket',
	function () {
		$expected = array(
			'203.0.113.7'                              => '4:203.0.113.7',
			'203.0.113.7:8080'                         => '4:203.0.113.7',
			'2001:db8:1:2::1'                          => '6:20010db800010002',
			'2001:0db8:0001:0002:ffff:eeee:dddd:cccc'  => '6:20010db800010002',
			'2001:db8:1:3::1'                          => '6:20010db800010003',
			'[2001:db8:1:2::9]:443'                    => '6:20010db800010002',
			'fe80::1%eth0'                             => '6:fe80000000000000',
			'::ffff:203.0.113.7'                       => '4:203.0.113.7',
			'::ffff:cb00:7107'                         => '4:203.0.113.7',
			'::1'                                      => '6:0000000000000000',
			''                                         => 'unknown',
			'not-an-ip'                                => 'unknown',
			'999.1.1.1'                                => 'unknown',
			'010.1.1.1'                                => 'unknown',
			'[2001:db8::1'                             => 'unknown',
		);
		foreach ( $expected as $text => $bucket ) {
			swfl_same( $bucket, swfl_test_bucket( (string) $text ), (string) $text );
		}
	}
);

swfl_test(
	'client ip: parse_range accepts addresses and CIDR ranges, rejects everything else including /0',
	function () {
		swfl_same( 32, SWFL_Client_Ip::parse_range( '10.1.2.3' )['bits'] );
		swfl_same( 128, SWFL_Client_Ip::parse_range( '2001:db8::1' )['bits'] );
		swfl_same( 8, SWFL_Client_Ip::parse_range( '10.0.0.0/8' )['bits'] );
		swfl_same( 32, SWFL_Client_Ip::parse_range( '2400:cb00::/32' )['bits'] );
		foreach ( array( '0.0.0.0/0', '::/0', '10.0.0.0/33', '2001:db8::/129', 'abc/8', '10.0.0.0/', '10.0.0.0/-1', '10.0.0.0/8/8', '[::1]/128', '', '10.0.0.0/08x', '10.0.0.0/8x' ) as $text ) {
			swfl_same( null, SWFL_Client_Ip::parse_range( $text ), 'rejects ' . var_export( $text, true ) );
		}
		swfl_same( '10.0.0.0/8', SWFL_Client_Ip::format_range( SWFL_Client_Ip::parse_range( '10.0.0.0/8' ) ) );
		swfl_same( '2400:cb00::/32', SWFL_Client_Ip::format_range( SWFL_Client_Ip::parse_range( '2400:cb00::/32' ) ) );
	}
);

swfl_test(
	'client ip: range matching at the edges, per family',
	function () {
		$ranges = array();
		foreach ( array( '173.245.48.0/20', '103.21.244.0/22', '2400:cb00::/32', '172.64.0.0/13', '10.0.0.5' ) as $text ) {
			$ranges[] = SWFL_Client_Ip::parse_range( $text );
		}
		$cases = array(
			'173.245.48.1'     => true,
			'173.245.63.255'   => true,
			'173.245.64.0'     => false,
			'172.71.255.255'   => true,
			'172.72.0.0'       => false,
			'10.0.0.5'         => true,
			'10.0.0.6'         => false,
			'2400:cb00:1234::1' => true,
			'2400:cb01::1'     => false,
			'2001:db8::1'      => false,
		);
		foreach ( $cases as $text => $inside ) {
			swfl_same( $inside, SWFL_Client_Ip::in_ranges( SWFL_Client_Ip::parse_ip( (string) $text ), $ranges ), (string) $text );
		}
	}
);

swfl_test(
	'client ip: the X-Forwarded-For walk',
	function () {
		$ranges = array( SWFL_Client_Ip::parse_range( '10.0.0.0/8' ), SWFL_Client_Ip::parse_range( '173.245.48.0/20' ) );
		$bucket = function ( $remote, $forwarded, $ranges ) {
			return SWFL_Client_Ip::to_bucket( SWFL_Client_Ip::resolve( $remote, $forwarded, $ranges ) );
		};
		swfl_same( '4:10.0.0.5', $bucket( '10.0.0.5', '198.51.100.9', array() ), 'no trusted proxies: ignored' );
		swfl_same( '4:198.51.100.1', $bucket( '198.51.100.1', '203.0.113.9', $ranges ), 'untrusted peer: ignored' );
		swfl_same( '4:203.0.113.9', $bucket( '10.0.0.5', '203.0.113.9', $ranges ) );
		swfl_same( '4:203.0.113.9', $bucket( '10.0.0.5', '1.1.1.1, 203.0.113.9', $ranges ), 'forged left hop' );
		swfl_same( '4:203.0.113.9', $bucket( '10.0.0.5', '203.0.113.9, 173.245.48.7, 10.0.0.9', $ranges ), 'trusted hops are skipped from the right' );
		swfl_same( '4:10.0.0.5', $bucket( '10.0.0.5', '10.0.0.9, 173.245.48.7', $ranges ), 'only proxies: the connecting one' );
		swfl_same( '4:10.0.0.5', $bucket( '10.0.0.5', '203.0.113.9, garbage', $ranges ), 'an unreadable hop' );
		swfl_same( '4:10.0.0.5', $bucket( '10.0.0.5', '', $ranges ), 'no header' );
		swfl_same( '6:20010db800010002', $bucket( '10.0.0.5', '2001:db8:1:2::77', $ranges ) );
		swfl_same( '6:20010db800010002', $bucket( '10.0.0.5', '[2001:db8:1:2::77]:5000', $ranges ) );
		swfl_same( '4:203.0.113.9', $bucket( '::ffff:10.0.0.5', '203.0.113.9', $ranges ), 'IPv4-mapped peer' );
		swfl_same( 'unknown', $bucket( '', '203.0.113.9', $ranges ), 'unreadable REMOTE_ADDR' );
		$long = implode( ', ', array_merge( array( '203.0.113.9' ), array_fill( 0, 40, '10.0.0.9' ) ) );
		swfl_same( '4:10.0.0.5', $bucket( '10.0.0.5', $long, $ranges ), 'only the last 32 hops are read' );
	}
);

swfl_test(
	'client ip: the swfl_client_ip filter replaces the address, junk becomes the unknown bucket',
	function () {
		$_SERVER['REMOTE_ADDR'] = '203.0.113.7';
		add_filter(
			'swfl_client_ip',
			function () {
				return '2001:db8::5';
			}
		);
		swfl_same( '6:20010db800000000', SWFL_Client_Ip::get_bucket() );
		$GLOBALS['swfl_t']['filters'] = array();
		add_filter(
			'swfl_client_ip',
			function () {
				return 'garbage';
			}
		);
		swfl_same( 'unknown', SWFL_Client_Ip::get_bucket() );
		unset( $_SERVER['REMOTE_ADDR'] );
		$GLOBALS['swfl_t']['filters'] = array();
		swfl_same( 'unknown', SWFL_Client_Ip::get_bucket(), 'no REMOTE_ADDR at all' );
	}
);

swfl_test(
	'client ip: ranges come from the setting and the swfl_trusted_proxies filter, invalid entries are skipped',
	function () {
		swfl_same( array(), SWFL_Client_Ip::get_trusted_ranges() );
		$GLOBALS['swfl_t']['options']['swfl_trusted_proxies'] = "10.0.0.0/8, junk\n# a comment 1.2.3.4\n2400:cb00::/32";
		swfl_same( 2, count( SWFL_Client_Ip::get_trusted_ranges() ) );
		add_filter(
			'swfl_trusted_proxies',
			function ( $entries ) {
				$entries[] = '192.168.0.0/16';
				$entries[] = 'also junk';
				return $entries;
			}
		);
		swfl_same( 3, count( SWFL_Client_Ip::get_trusted_ranges() ) );
		$GLOBALS['swfl_t']['filters'] = array();
		add_filter(
			'swfl_trusted_proxies',
			function () {
				return 'not an array';
			}
		);
		swfl_same( array(), SWFL_Client_Ip::get_trusted_ranges() );
	}
);

swfl_test(
	'settings: trusted proxies are normalised, de-duplicated and reported when invalid',
	function () {
		$settings = new SWFL_Admin_Settings();
		swfl_same( "10.0.0.0/8\n1.2.3.4/32\n2400:cb00::/32", $settings->sanitize_trusted_proxies( "10.0.0.0/8\r\n1.2.3.4 1.2.3.4/32 # same again\n2400:cb00::/32" ) );
		swfl_same( array(), $GLOBALS['swfl_t']['errors'] );
		swfl_same( '10.0.0.0/8', $settings->sanitize_trusted_proxies( "10.0.0.0/8\n0.0.0.0/0\nnope" ) );
		swfl_same( 1, count( $GLOBALS['swfl_t']['errors'] ) );
		swfl_same( 'swfl_trusted_proxies_invalid', $GLOBALS['swfl_t']['errors'][0][1] );
		swfl_same( '', $settings->sanitize_trusted_proxies( array( 'x' ) ) );
		swfl_same( '', $settings->sanitize_trusted_proxies( '' ) );
	}
);

swfl_test(
	'settings: the upstream budget accepts 0 to 10000 and falls back to 120',
	function () {
		$settings = new SWFL_Admin_Settings();
		swfl_same( 0, $settings->sanitize_upstream_budget( '0' ) );
		swfl_same( 250, $settings->sanitize_upstream_budget( '250' ) );
		swfl_same( 10000, $settings->sanitize_upstream_budget( '99999' ) );
		swfl_same( 5, $settings->sanitize_upstream_budget( '-5' ), 'absint, like the other limits' );
		swfl_same( 120, $settings->sanitize_upstream_budget( 'abc' ) );
		swfl_same( 120, $settings->sanitize_upstream_budget( array() ) );
	}
);

swfl_test(
	'client ip: a trusted range must not be wider than /8 (IPv4) or /16 (IPv6), as the settings page saves half-typed values',
	function () {
		foreach ( array( '10.0.0.0/8' => 8, '0.0.0.0/8' => 8, '10.0.0.0/24' => 24, 'fe80::/16' => 16, '2400::/16' => 16 ) as $text => $bits ) {
			swfl_same( $bits, SWFL_Client_Ip::parse_range( $text )['bits'], 'accepts ' . $text );
		}
		foreach ( array( '10.0.0.0/1', '128.0.0.0/1', '10.0.0.0/7', '0.0.0.0/0', '2400::/1', '2400::/15', '::/0' ) as $text ) {
			swfl_same( null, SWFL_Client_Ip::parse_range( $text ), 'rejects ' . $text );
		}
		$settings = new SWFL_Admin_Settings();
		swfl_same( "10.0.0.0/8\n2400:cb00::/32", $settings->sanitize_trusted_proxies( "10.0.0.0/8\n10.0.0.0/1\n2400:cb00::/32\n::/3" ), 'wide ranges are dropped like any invalid entry' );
		swfl_same( 1, count( $GLOBALS['swfl_t']['errors'] ), 'and reported' );
	}
);

swfl_test(
	'client ip: an IPv4-mapped range is the IPv4 range it stands for, and the floor still applies',
	function () {
		$mapped = SWFL_Client_Ip::parse_range( '::ffff:10.0.0.0/104' );
		swfl_same( 8, $mapped['bits'] );
		swfl_same( 4, strlen( $mapped['binary'] ) );
		swfl_same( '10.0.0.0/8', SWFL_Client_Ip::format_range( $mapped ) );
		swfl_same( '10.0.0.5/32', SWFL_Client_Ip::format_range( SWFL_Client_Ip::parse_range( '::ffff:10.0.0.5' ) ) );
		swfl_same( '192.168.0.0/16', SWFL_Client_Ip::format_range( SWFL_Client_Ip::parse_range( '::ffff:c0a8:0/112' ) ) );
		foreach ( array( '::ffff:0:0/96', '::ffff:10.0.0.0/103', '::ffff:10.0.0.0/129', '::ffff:0.0.0.0/103' ) as $text ) {
			swfl_same( null, SWFL_Client_Ip::parse_range( $text ), 'rejects ' . $text );
		}
		// A proxy that shows up as ::ffff:10.0.0.5 in the server's log is trusted for IPv4 and for mapped peers alike.
		$ranges = array( SWFL_Client_Ip::parse_range( '::ffff:10.0.0.0/104' ) );
		swfl_same( true, SWFL_Client_Ip::in_ranges( SWFL_Client_Ip::parse_ip( '10.0.0.5' ), $ranges ) );
		swfl_same( true, SWFL_Client_Ip::in_ranges( SWFL_Client_Ip::parse_ip( '::ffff:10.0.0.5' ), $ranges ) );
		swfl_same( false, SWFL_Client_Ip::in_ranges( SWFL_Client_Ip::parse_ip( '11.0.0.5' ), $ranges ) );
		swfl_same( '4:203.0.113.9', SWFL_Client_Ip::to_bucket( SWFL_Client_Ip::resolve( '10.0.0.5', '203.0.113.9', $ranges ) ), 'the walk reads X-Forwarded-For' );
	}
);

swfl_test(
	'client ip: prefixes that are not a whole number of bytes decide on the right bit, in both families',
	function () {
		$cases = array(
			// Range, address, inside.
			array( '173.245.48.0/20', '173.245.63.255', true ),
			array( '173.245.48.0/20', '173.245.176.1', false ), // The top bit of the third byte differs.
			array( '10.0.0.0/9', '10.127.255.255', true ),
			array( '10.0.0.0/9', '10.128.0.1', false ),
			array( '10.128.0.0/9', '10.128.0.1', true ),
			array( '10.128.0.0/9', '10.0.0.1', false ),
			array( '192.0.2.0/25', '192.0.2.127', true ),
			array( '192.0.2.0/25', '192.0.2.128', false ),
			array( '192.0.2.128/25', '192.0.2.200', true ),
			array( '2001:db8::/20', '2001:0fff::1', true ),
			array( '2001:db8::/20', '2001:1000::1', false ),
			array( '2001:db8::/33', '2001:db8:7fff::1', true ),
			array( '2001:db8::/33', '2001:db8:8000::1', false ),
			array( '2001:db8:8000::/33', '2001:db8:8000::1', true ),
			array( '2001:db8:8000::/33', '2001:db8:7fff::1', false ),
		);
		foreach ( $cases as $case ) {
			$range = SWFL_Client_Ip::parse_range( $case[0] );
			swfl_same( $case[2], SWFL_Client_Ip::in_ranges( SWFL_Client_Ip::parse_ip( $case[1] ), array( $range ) ), $case[1] . ' in ' . $case[0] );
		}
	}
);

swfl_test(
	'client ip: the swfl_client_ip filter may return anything, only a string is read',
	function () {
		$_SERVER['REMOTE_ADDR'] = '203.0.113.7';
		foreach ( array( array( '2001:db8::5' ), new stdClass(), true, 42, null, 1.5 ) as $value ) {
			$GLOBALS['swfl_t']['filters'] = array();
			add_filter(
				'swfl_client_ip',
				function () use ( $value ) {
					return $value;
				}
			);
			swfl_same( 'unknown', SWFL_Client_Ip::get_bucket(), 'a ' . gettype( $value ) . ' is no address' );
		}
	}
);

swfl_test(
	'settings: the message about ignored trusted proxy entries is escaped and short, and entries beyond the limit are reported',
	function () {
		$settings = new SWFL_Admin_Settings();
		$settings->sanitize_trusted_proxies( "10.0.0.0/8\n<svg/onload=alert(document.domain)>\n" . str_repeat( 'x', 5000 ) . "\n<b>x</b>\n\"a'b" );
		swfl_same( 1, count( $GLOBALS['swfl_t']['errors'] ) );
		list( , $code, $message, $type ) = $GLOBALS['swfl_t']['errors'][0];
		swfl_same( 'swfl_trusted_proxies_invalid', $code );
		swfl_same( false, strpos( $message, '<' ), 'no markup survives in the message' );
		swfl_same( true, false !== strpos( $message, '&lt;svg/onload=alert(document.domain)&gt;' ), 'the entry stays readable' );
		swfl_same( true, strlen( $message ) < 400, 'a huge entry is cut' );
		swfl_same( 'error', $type, 'a type that every WordPress version styles' );

		$GLOBALS['swfl_t']['errors'] = array();
		$lines                       = array();
		for ( $i = 0; $i < 101; $i++ ) {
			$lines[] = '10.' . $i . '.0.0/16';
		}
		$stored = $settings->sanitize_trusted_proxies( implode( "\n", $lines ) );
		swfl_same( SWFL_Client_Ip::MAX_RANGES, count( explode( "\n", $stored ) ) );
		swfl_same( 'swfl_trusted_proxies_too_many', $GLOBALS['swfl_t']['errors'][0][1] );
	}
);
