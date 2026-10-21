<?php
/**
 * Tests of SWFL_Rate_Limit_Log: the list of blocked visitors on the settings page.
 *
 * @package SWFL
 */

swfl_test(
	'blocked visitors: written once per window, minutes counted, the latest block first, gone after an hour',
	function () {
		SWFL_Rate_Limit_Log::record( '4:203.0.113.7', 1060, 1010 );
		$writes = count( $GLOBALS['swfl_t']['writes'] );
		SWFL_Rate_Limit_Log::record( '4:203.0.113.7', 1060, 1020 );
		SWFL_Rate_Limit_Log::record( '4:203.0.113.7', 1060, 1050 );
		swfl_same( $writes, count( $GLOBALS['swfl_t']['writes'] ), 'more refusals in the same window write nothing' );
		SWFL_Rate_Limit_Log::record( '4:203.0.113.7', 1120, 1070 );
		SWFL_Rate_Limit_Log::record( '6:20010db800010002', 1180, 1130 );

		$entries = SWFL_Rate_Limit_Log::get_entries( 1140 );
		swfl_same( array( '6:20010db800010002', '4:203.0.113.7' ), array_keys( $entries ) );
		swfl_same(
			array(
				'first'   => 1010,
				'until'   => 1120,
				'minutes' => 2,
			),
			$entries['4:203.0.113.7']
		);
		swfl_same( array( '6:20010db800010002' ), array_keys( SWFL_Rate_Limit_Log::get_entries( 1120 + HOUR_IN_SECONDS ) ), 'an hour after its last block an entry is gone' );
		swfl_same( array(), SWFL_Rate_Limit_Log::get_entries( 1180 + HOUR_IN_SECONDS ) );
	}
);

swfl_test(
	'blocked visitors: the list is capped, a known visitor is still updated',
	function () {
		for ( $i = 0; $i < SWFL_Rate_Limit_Log::MAX_ENTRIES; $i++ ) {
			SWFL_Rate_Limit_Log::record( '4:10.0.0.' . $i, 1060, 1010 );
		}
		SWFL_Rate_Limit_Log::record( '4:10.0.1.1', 1060, 1010 );
		$entries = SWFL_Rate_Limit_Log::get_entries( 1010 );
		swfl_same( SWFL_Rate_Limit_Log::MAX_ENTRIES, count( $entries ) );
		swfl_same( false, isset( $entries['4:10.0.1.1'] ) );
		SWFL_Rate_Limit_Log::record( '4:10.0.0.3', 1120, 1070 );
		swfl_same( 2, SWFL_Rate_Limit_Log::get_entries( 1070 )['4:10.0.0.3']['minutes'] );
		$GLOBALS['swfl_t']['transients'][ SWFL_Rate_Limit_Log::TRANSIENT ][0] = 'garbage';
		swfl_same( array(), SWFL_Rate_Limit_Log::get_entries( 1070 ), 'a broken list reads as empty' );
	}
);

swfl_test(
	'blocked visitors: buckets are shown as address or /64 prefix',
	function () {
		swfl_same( '203.0.113.7', SWFL_Rate_Limit_Log::format_bucket( '4:203.0.113.7' ) );
		swfl_same( '2001:db8:1:2::/64', SWFL_Rate_Limit_Log::format_bucket( '6:20010db800010002' ) );
		swfl_same( '::/64', SWFL_Rate_Limit_Log::format_bucket( '6:0000000000000000' ) );
		swfl_same( '', SWFL_Rate_Limit_Log::format_bucket( 'unknown' ) );
		swfl_same( '', SWFL_Rate_Limit_Log::format_bucket( '6:zz' ) );
	}
);

swfl_test(
	'blocked visitors: the rate limit records the visitor it refuses, with the end of the minute',
	function () {
		$GLOBALS['swfl_t']['options']['swfl_rest_rate_limit'] = 1;
		$_SERVER['REMOTE_ADDR']                               = '2001:db8:1:2::99';
		SWFL_Request_Guard::check_rate_limit( new WP_REST_Request() );
		swfl_same( array(), SWFL_Rate_Limit_Log::get_entries(), 'a request within the limit is not listed' );
		swfl_same( true, is_wp_error( SWFL_Request_Guard::check_rate_limit( new WP_REST_Request() ) ) );
		$entry = SWFL_Rate_Limit_Log::get_entries()['6:20010db800010002'];
		swfl_same( true, $entry['until'] > time() && $entry['until'] <= time() + MINUTE_IN_SECONDS );
	}
);
