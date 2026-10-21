<?php
/**
 * Tests of SWFL_Upstream_Budget and of paging under the budget.
 *
 * @package SWFL
 */

swfl_test(
	'budget: admins and an unlimited setting are exempt, the filter wins, leaving ends the state',
	function () {
		$GLOBALS['swfl_t']['options']['swfl_upstream_budget'] = 1;
		$GLOBALS['swfl_t']['user_can']                         = true;
		SWFL_Upstream_Budget::enter_public_request();
		swfl_same( true, SWFL_Upstream_Budget::acquire() );
		swfl_same( true, SWFL_Upstream_Budget::acquire(), 'an administrator is not limited' );

		$GLOBALS['swfl_t']['user_can'] = false;
		SWFL_Upstream_Budget::enter_public_request();
		$GLOBALS['swfl_t']['options']['swfl_upstream_budget'] = 0;
		swfl_same( true, SWFL_Upstream_Budget::acquire() );
		swfl_same( true, SWFL_Upstream_Budget::acquire(), '0 turns the budget off' );

		$GLOBALS['swfl_t']['options']['swfl_upstream_budget'] = 1;
		add_filter(
			'swfl_upstream_budget',
			function () {
				return 3;
			}
		);
		swfl_same( 3, SWFL_Upstream_Budget::get_limit(), 'the filter has the last word' );
		swfl_same( true, SWFL_Upstream_Budget::acquire() );
		swfl_same( true, SWFL_Upstream_Budget::acquire() );
		swfl_same( true, SWFL_Upstream_Budget::acquire() );
		swfl_same( true, SWFL_Upstream_Budget::is_budget_error( SWFL_Upstream_Budget::acquire() ), 'the fourth is refused' );
		SWFL_Upstream_Budget::leave_public_request();
		swfl_same( true, SWFL_Upstream_Budget::acquire(), 'after the route has run nothing is limited, although the minute is used up' );
	}
);

swfl_test(
	'budget: the default is 120 and a refusal carries retry_after',
	function () {
		swfl_same( 120, SWFL_Upstream_Budget::get_limit() );
		$GLOBALS['swfl_t']['options']['swfl_upstream_budget'] = 1;
		SWFL_Upstream_Budget::enter_public_request();
		swfl_same( true, SWFL_Upstream_Budget::acquire() );
		$error = SWFL_Upstream_Budget::acquire();
		swfl_same( 'swfl_budget_exceeded', $error->get_error_code() );
		swfl_same( 503, $error->get_error_data()['status'] );
	}
);

swfl_test(
	'budget: the notice and the action fire once per minute',
	function () {
		$GLOBALS['swfl_t']['options']['swfl_upstream_budget'] = 1;
		SWFL_Upstream_Budget::enter_public_request();
		SWFL_Upstream_Budget::acquire();
		SWFL_Upstream_Budget::acquire();
		SWFL_Upstream_Budget::acquire();
		SWFL_Upstream_Budget::acquire();
		swfl_same( 1, $GLOBALS['swfl_t']['actions']['swfl_upstream_budget_exceeded'] );
		swfl_same( 1, get_transient( 'swfl_budget_notice' )['limit'] );
	}
);

swfl_test(
	'budget: entering a new public request forgets the state of the previous one',
	function () {
		$GLOBALS['swfl_t']['options']['swfl_upstream_budget'] = 1;
		SWFL_Upstream_Budget::enter_public_request();
		SWFL_Upstream_Budget::acquire();
		SWFL_Upstream_Budget::refuse( SWFL_Upstream_Budget::acquire() );
		SWFL_Upstream_Budget::serve_stale( array() );
		swfl_same( true, null !== SWFL_Upstream_Budget::get_denial() && SWFL_Upstream_Budget::was_stale_served() );
		SWFL_Upstream_Budget::enter_public_request();
		swfl_same( null, SWFL_Upstream_Budget::get_denial() );
		swfl_same( false, SWFL_Upstream_Budget::was_stale_served() );
	}
);

/**
 * Body of a games page with one row and an optional next page.
 *
 * @param int|null $next Page number the slider points to, null for the last page.
 * @return string JSON.
 */
function swfl_test_page( $next = null ) {
	return wp_json_encode(
		array(
			'data' => array(
				'regions' => array( array( 'rows' => array( array( 'cells' => array() ) ) ) ),
				'slider'  => $next ? array( 'next' => array( 'set_in_context' => array( 'page' => $next ) ) ) : array(),
			),
		)
	);
}

swfl_test(
	'paging: a page the budget refused fails the whole list and the pages fetched so far are kept',
	function () {
		$GLOBALS['swfl_t']['options']['swfl_upstream_budget'] = 2;
		SWFL_Upstream_Budget::enter_public_request();
		swfl_test_http(
			array( 'code' => 200, 'body' => swfl_test_page( 2 ) ),
			array( 'code' => 200, 'body' => swfl_test_page( 3 ) ),
			array( 'code' => 200, 'body' => swfl_test_page() )
		);
		$result = SWFL_Table_Data::fetch_all_pages( 'games', array( 'mode' => 'team', 'team_id' => 31 ) );
		swfl_same( true, SWFL_Upstream_Budget::is_budget_error( $result ), 'an error, not a list of two rows' );

		// In the next window only the missing page is fetched.
		$GLOBALS['swfl_t']['options']['swfl_upstream_budget'] = 0;
		$result = SWFL_Table_Data::fetch_all_pages( 'games', array( 'mode' => 'team', 'team_id' => 31 ) );
		swfl_same( 3, count( $result['rows'] ) );
		swfl_same( 3, count( $GLOBALS['swfl_t']['http_calls'] ) );
	}
);

swfl_test(
	'paging: an upstream error in the middle of a list still returns the rows so far (unchanged)',
	function () {
		swfl_test_http(
			array( 'code' => 200, 'body' => swfl_test_page( 2 ) ),
			array( 'code' => 500, 'body' => '' )
		);
		$result = SWFL_Table_Data::fetch_all_pages( 'games', array( 'mode' => 'team', 'team_id' => 32 ) );
		swfl_same( 1, count( $result['rows'] ) );
	}
);

swfl_test(
	'paging: a page the budget refused before the current one ends the list, even when the later pages are cached',
	function () {
		$middle = array(
			'code' => 200,
			'body' => wp_json_encode(
				array(
					'data' => array(
						'regions' => array( array( 'rows' => array( array( 'cells' => array() ) ) ) ),
						'slider'  => array(
							'prev' => array( 'set_in_context' => array( 'page' => 1 ) ),
							'next' => array( 'set_in_context' => array( 'page' => 3 ) ),
						),
					),
				)
			),
		);
		$params = array(
			'mode'    => 'team',
			'team_id' => 32,
		);
		// Budget off: the current page, the page before and the page after are cached, in this order.
		swfl_test_http( $middle, array( 'code' => 200, 'body' => swfl_test_page() ), array( 'code' => 200, 'body' => swfl_test_page() ) );
		swfl_same( 3, count( SWFL_Table_Data::fetch_all_pages( 'games', $params )['rows'] ) );
		list( $current, $before ) = $GLOBALS['swfl_t']['writes'];
		delete_transient( $current );
		delete_transient( $before );

		// One unit: the current page takes it, the page before is refused, the page after would come from the cache.
		$GLOBALS['swfl_t']['options']['swfl_upstream_budget'] = 1;
		SWFL_Upstream_Budget::enter_public_request();
		swfl_test_http( $middle );
		$result = SWFL_Table_Data::fetch_all_pages( 'games', $params );
		swfl_same( true, SWFL_Upstream_Budget::is_budget_error( $result ), 'a 503, not a list without its first page' );
	}
);

swfl_test(
	'visitor share: a visitor beyond the share gets 429 and takes nothing from the budget of the others (default 20)',
	function () {
		swfl_same( 20, SWFL_Upstream_Budget::get_visitor_limit() );
		$GLOBALS['swfl_t']['options']['swfl_upstream_budget']         = 100;
		$GLOBALS['swfl_t']['options']['swfl_upstream_visitor_budget'] = 2;
		SWFL_Upstream_Budget::enter_public_request( '4:198.51.100.1' );
		swfl_same( true, SWFL_Upstream_Budget::acquire() );
		swfl_same( true, SWFL_Upstream_Budget::acquire() );
		$refused = SWFL_Upstream_Budget::acquire();
		swfl_same( true, SWFL_Upstream_Budget::is_budget_error( $refused ), 'the third unit of the visitor is refused' );
		swfl_same( 429, $refused->get_error_data()['status'] );
		swfl_same( true, $refused->get_error_data()['retry_after'] >= 1 );
		swfl_same( 2, SWFL_Counter::get( 'upstream' ), 'the refused unit did not count for the site' );
		swfl_same( false, isset( $GLOBALS['swfl_t']['actions']['swfl_upstream_budget_exceeded'] ), 'the site budget is not exhausted' );
		swfl_same( array( '4:198.51.100.1' ), array_keys( SWFL_Rate_Limit_Log::get_entries() ), 'listed under blocked visitors' );

		SWFL_Upstream_Budget::enter_public_request( '6:20010db800010002' );
		swfl_same( true, SWFL_Upstream_Budget::acquire(), 'another visitor has a share of its own' );
	}
);

swfl_test(
	'visitor share: the site budget still applies, 0 turns the share off, no visitor means no share',
	function () {
		$GLOBALS['swfl_t']['options']['swfl_upstream_budget']         = 3;
		$GLOBALS['swfl_t']['options']['swfl_upstream_visitor_budget'] = 2;
		SWFL_Upstream_Budget::enter_public_request( '4:192.0.2.1' );
		SWFL_Upstream_Budget::acquire();
		SWFL_Upstream_Budget::acquire();
		SWFL_Upstream_Budget::enter_public_request( '4:192.0.2.2' );
		swfl_same( true, SWFL_Upstream_Budget::acquire() );
		$refused = SWFL_Upstream_Budget::acquire();
		swfl_same( 503, $refused->get_error_data()['status'], 'two visitors within their share used the site budget up' );

		swfl_test_reset();
		$GLOBALS['swfl_t']['options']['swfl_upstream_visitor_budget'] = 0;
		SWFL_Upstream_Budget::enter_public_request( '4:192.0.2.1' );
		for ( $i = 0; $i < 5; $i++ ) {
			swfl_same( true, SWFL_Upstream_Budget::acquire(), 'share off' );
		}
		$GLOBALS['swfl_t']['options']['swfl_upstream_visitor_budget'] = 1;
		SWFL_Upstream_Budget::enter_public_request();
		swfl_same( true, SWFL_Upstream_Budget::acquire() );
		swfl_same( true, SWFL_Upstream_Budget::acquire(), 'without a known visitor only the site budget applies' );
	}
);

swfl_test(
	'visitor share: the permission callback hands the visitor to the budget, an expired copy is served instead of a refusal',
	function () {
		$GLOBALS['swfl_t']['options']['swfl_upstream_visitor_budget'] = 1;
		$_SERVER['REMOTE_ADDR']                                       = '203.0.113.44';
		$client = new SWFL_Client();
		$client->fetch_data( 'games', array( 'team_id' => 41 ) );
		swfl_test_expire_envelopes();
		SWFL_Request_Guard::check_rate_limit( new WP_REST_Request() );
		swfl_same( false, is_wp_error( $client->fetch_data( 'games', array( 'team_id' => 42 ) ) ), 'the share pays for one miss' );
		swfl_same( false, is_wp_error( $client->fetch_data( 'games', array( 'team_id' => 41 ) ) ), 'then the expired copy is served' );
		swfl_same( true, SWFL_Upstream_Budget::was_stale_served() );
		swfl_same( 429, $client->fetch_data( 'games', array( 'team_id' => 43 ) )->get_error_data()['status'], 'and a miss without a copy is refused' );
		swfl_same( 2, count( $GLOBALS['swfl_t']['http_calls'] ) );
	}
);
