<?php
/**
 * Tests of SWFL_Counter: the fixed-window counter behind the rate limit and the upstream budget.
 *
 * @package SWFL
 */

/**
 * Whether a transient name is a counter.
 *
 * @param string $key Transient name.
 * @return bool
 */
function swfl_test_is_counter_key( $key ) {
	return 0 === strpos( $key, 'swfl_c_' );
}

swfl_test(
	'counter: one row per window counted by the database, refused events write nothing, windows are aligned',
	function () {
		$now = 1000 * 60 + 5;
		swfl_same( true, SWFL_Counter::take( 'x', 3, 60, $now ) );
		swfl_same( true, SWFL_Counter::take( 'x', 3, 60, $now ) );
		swfl_same( true, SWFL_Counter::take( 'x', 3, 60, $now ) );
		swfl_same( array( 'swfl_count_0000060060_x' => 3 ), $GLOBALS['swfl_t']['rows'], 'the row name holds the end of the window' );
		$GLOBALS['swfl_t']['db_writes'] = 0;
		swfl_same( false, SWFL_Counter::take( 'x', 3, 60, $now ), 'the fourth event is refused' );
		swfl_same( false, SWFL_Counter::take( 'x', 3, 60, $now ) );
		swfl_same( 0, $GLOBALS['swfl_t']['db_writes'], 'a refused event must not write' );
		swfl_same( 3, SWFL_Counter::get( 'x', 60, $now ) );
		swfl_same( 0, SWFL_Counter::get( 'x', 60, $now + 60 ), 'the next window is empty' );
		swfl_same( true, SWFL_Counter::take( 'x', 3, 60, $now + 60 ), 'the next window starts again' );
		swfl_same( 1, SWFL_Counter::get( 'x', 60, $now + 60 ) );
		swfl_same( array(), $GLOBALS['swfl_t']['transients'], 'no transient' );
	}
);

swfl_test(
	'counter: another request counts in the same row, and expired rows of every counter are removed',
	function () {
		swfl_same( true, SWFL_Counter::take( 'upstream', 3, 60, 600 ) );
		// Parallel requests counted in the database meanwhile: the next event sees them.
		$GLOBALS['swfl_t']['rows']['swfl_count_0000000660_upstream'] = 3;
		swfl_same( false, SWFL_Counter::take( 'upstream', 3, 60, 610 ) );
		swfl_same( true, SWFL_Counter::take( 'rl_a', 1, 60, 610 ), 'other counters have rows of their own' );
		swfl_same( true, SWFL_Counter::take( 'rl_b', 1, 60, 650 ) );
		swfl_same( 3, count( $GLOBALS['swfl_t']['rows'] ) );
		// A visitor that never comes back leaves no row: the first new row of a later window removes the ended ones.
		swfl_same( true, SWFL_Counter::take( 'upstream', 3, 60, 725 ) );
		swfl_same( array( 'swfl_count_0000000780_upstream' => 1 ), $GLOBALS['swfl_t']['rows'] );
		swfl_same( true, SWFL_Counter::take( 'hourly', 1, 3600, 725 ) );
		swfl_same( true, SWFL_Counter::take( 'upstream', 3, 60, 790 ) );
		$rows = array_keys( $GLOBALS['swfl_t']['rows'] );
		sort( $rows );
		swfl_same( array( 'swfl_count_0000000840_upstream', 'swfl_count_0000003600_hourly' ), $rows, 'an ended window is removed, a longer window that has not ended is kept' );
	}
);

swfl_test(
	'counter: names are independent, a limit below 1 allows nothing',
	function () {
		swfl_same( true, SWFL_Counter::take( 'a', 1, 60, 600 ) );
		swfl_same( false, SWFL_Counter::take( 'a', 1, 60, 600 ) );
		swfl_same( true, SWFL_Counter::take( 'b', 1, 60, 600 ) );
		swfl_same( false, SWFL_Counter::take( 'c', 0, 60, 600 ) );
		swfl_same( false, SWFL_Counter::take( 'c', -3, 60, 600 ) );
	}
);

swfl_test(
	'counter: seconds_left is the time to the end of the aligned window',
	function () {
		swfl_same( 60, SWFL_Counter::seconds_left( 60, 120 ) );
		swfl_same( 1, SWFL_Counter::seconds_left( 60, 179 ) );
		swfl_same( 60, SWFL_Counter::seconds_left( 60, 180 ) );
		swfl_same( 55, SWFL_Counter::seconds_left( 60, 185 ) );
		swfl_same( 7, SWFL_Counter::seconds_left( 10, 83 ) );
	}
);

swfl_test(
	'counter (object cache): atomic path, no rows, key expires after the window',
	function () {
		$GLOBALS['swfl_t']['ext_cache'] = true;
		swfl_same( true, SWFL_Counter::take( 'y', 2, 60, 125 ) );
		swfl_same( true, SWFL_Counter::take( 'y', 2, 60, 125 ) );
		swfl_same( false, SWFL_Counter::take( 'y', 2, 60, 125 ) );
		swfl_same( array(), $GLOBALS['swfl_t']['transients'] );
		swfl_same( array(), $GLOBALS['swfl_t']['rows'] );
		swfl_same( 3, SWFL_Counter::get( 'y', 60, 125 ) );
		swfl_same( 56, $GLOBALS['swfl_t']['cache']['swfl:y:2'][1], 'seconds left in the window + 1' );
		swfl_same( true, SWFL_Counter::take( 'y', 2, 60, 185 ), 'next window is a new key' );
	}
);

swfl_test(
	'counter (object cache): a failing wp_cache_incr falls back to the database row',
	function () {
		$GLOBALS['swfl_t']['ext_cache']  = true;
		$GLOBALS['swfl_t']['incr_fails'] = true;
		swfl_same( true, SWFL_Counter::take( 'z', 1, 60, 125 ) );
		swfl_same( false, SWFL_Counter::take( 'z', 1, 60, 125 ) );
		swfl_same( array( 'swfl_count_0000000180_z' => 1 ), $GLOBALS['swfl_t']['rows'] );
	}
);

swfl_test(
	'counter (transient, the last resort): used when the database refuses the statements',
	function () {
		$GLOBALS['swfl_t']['db_fails'] = true;
		$now                           = 1000 * 60 + 5;
		swfl_same( true, SWFL_Counter::take( 'x', 2, 60, $now ) );
		swfl_same( true, SWFL_Counter::take( 'x', 2, 60, $now ) );
		$writes = count( $GLOBALS['swfl_t']['writes'] );
		swfl_same( false, SWFL_Counter::take( 'x', 2, 60, $now ) );
		swfl_same( $writes, count( $GLOBALS['swfl_t']['writes'] ), 'a refused event must not write' );
		swfl_same( 2, SWFL_Counter::get( 'x', 60, $now ) );
		swfl_same( true, SWFL_Counter::take( 'x', 2, 60, $now + 60 ), 'the next window starts again' );
		swfl_same( 1, count( array_filter( array_keys( $GLOBALS['swfl_t']['transients'] ), 'swfl_test_is_counter_key' ) ), 'one transient is reused for every window' );
	}
);

swfl_test(
	'counter (transient, the last resort): a request that counts again sees the events of parallel requests',
	function () {
		$GLOBALS['swfl_t']['db_fails'] = true;
		$GLOBALS['swfl_t']['process']  = true;
		swfl_same( true, SWFL_Counter::take( 'q', 3, 60, 600 ), 'first event of this request' );
		swfl_test_db_write(
			'swfl_c_q',
			array(
				'b' => 10,
				'n' => 3,
			),
			60
		);
		swfl_same( false, SWFL_Counter::take( 'q', 3, 60, 600 ), 'the next event of the same request is refused' );
		swfl_same( 3, $GLOBALS['swfl_t']['transients']['swfl_c_q'][0]['n'], 'and the shared count was not lowered' );

		swfl_same( 0, SWFL_Counter::get( 'r', 60, 600 ), 'this process looked and found nothing' );
		swfl_test_db_write(
			'swfl_c_r',
			array(
				'b' => 10,
				'n' => 2,
			),
			60
		);
		swfl_same( true, SWFL_Counter::take( 'r', 3, 60, 600 ) );
		swfl_same( 3, $GLOBALS['swfl_t']['transients']['swfl_c_r'][0]['n'], 'a window another request opened is counted on' );
	}
);
