/**
 * Progressive enhancement for the Swiss Floorball widgets.
 *
 * The server renders every widget in a readable initial state. This script adds the
 * navigation of the official web components: week paging (club games), page paging
 * (team games), team selection (club team games) and round navigation (league games).
 *
 * @package SWFL
 * @since   1.1.0
 */
( function () {
	'use strict';

	var config = window.swflWidgets || {};
	var DAY = 24 * 60 * 60 * 1000;

	function pad( number ) {
		return ( number < 10 ? '0' : '' ) + number;
	}

	function parseIso( iso ) {
		var parts = iso.split( '-' );
		return new Date( Date.UTC( Number( parts[ 0 ] ), Number( parts[ 1 ] ) - 1, Number( parts[ 2 ] ) ) );
	}

	function toIso( date ) {
		return date.getUTCFullYear() + '-' + pad( date.getUTCMonth() + 1 ) + '-' + pad( date.getUTCDate() );
	}

	function formatDate( date ) {
		return pad( date.getUTCDate() ) + '.' + pad( date.getUTCMonth() + 1 ) + '.' + date.getUTCFullYear();
	}

	function parseJson( value ) {
		try {
			return JSON.parse( value || '{}' ) || {};
		} catch ( error ) {
			return {};
		}
	}

	/**
	 * Show (or hide, with an empty text) a short notice at the top of a widget.
	 */
	function setNotice( widget, text ) {
		var notice = widget.querySelector( ':scope > .sfa-widget-notice' );
		if ( ! text ) {
			if ( notice ) {
				notice.remove();
			}
			return;
		}
		if ( ! notice ) {
			notice = document.createElement( 'p' );
			notice.className = 'sfa-widget-notice';
			notice.setAttribute( 'role', 'status' );
			widget.insertBefore( notice, widget.firstChild );
		}
		notice.textContent = text;
	}

	/**
	 * Fetch rendered widget HTML from a REST route and swap it in for the given element.
	 *
	 * A refused request (429, 503) or a failure leaves the content as it is and calls onFail, so the widget
	 * can tell the visitor and undo the change that asked for the new content.
	 */
	function reload( route, params, target, replaceSelf, onFail ) {
		var query = new URLSearchParams();
		Object.keys( params ).forEach( function ( key ) {
			if ( params[ key ] !== undefined && params[ key ] !== null && params[ key ] !== '' ) {
				query.set( key, params[ key ] );
			}
		} );

		target.setAttribute( 'aria-busy', 'true' );

		// Errors are handled below, so the promise is not returned and callers have nothing left to await.
		fetch( config.restUrl + route + '?' + query.toString(), { headers: { Accept: 'application/json' } } )
			.then( function ( response ) {
				if ( ! response.ok ) {
					throw new Error( 'HTTP ' + response.status );
				}
				return response.json();
			} )
			.then( function ( payload ) {
				var holder = document.createElement( 'div' );
				holder.innerHTML = payload.html || '';
				if ( replaceSelf ) {
					var fresh = holder.firstElementChild;
					if ( fresh ) {
						target.replaceWith( fresh );
						init( fresh.parentNode );
					}
				} else {
					target.innerHTML = holder.innerHTML;
					target.removeAttribute( 'aria-busy' );
					init( target );
				}
			} )
			.catch( function () {
				target.removeAttribute( 'aria-busy' );
				if ( onFail ) {
					onFail();
				}
			} );
	}

	function initWeek( widget ) {
		var weekStart = parseIso( widget.dataset.sfaWeekStart );
		var offset = 0;
		var label = widget.querySelector( '[data-sfa-label]' );
		var empty = widget.querySelector( '[data-sfa-empty]' );
		var rows = widget.querySelectorAll( 'tbody tr[data-sfa-date]' );

		function render() {
			var start = new Date( weekStart.getTime() + offset * 7 * DAY );
			var end = new Date( start.getTime() + 6 * DAY );
			var from = toIso( start );
			var to = toIso( end );
			var visible = 0;

			rows.forEach( function ( row ) {
				var date = row.dataset.sfaDate;
				var show = date !== '' && date >= from && date <= to;
				row.hidden = ! show;
				if ( show ) {
					visible++;
				}
			} );

			label.textContent = formatDate( start ) + ' – ' + formatDate( end );
			if ( empty ) {
				empty.hidden = visible > 0;
			}
		}

		widget.querySelectorAll( '[data-sfa-action]' ).forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				offset += button.dataset.sfaAction === 'next' ? 1 : -1;
				render();
			} );
		} );
	}

	function initPager( widget ) {
		var size = Number( widget.dataset.sfaPageSize ) || 4;
		var start = Number( widget.dataset.sfaStart ) || 0;
		var rows = widget.querySelectorAll( 'tbody tr' );
		var total = rows.length;
		var label = widget.querySelector( '[data-sfa-label]' );
		var prev = widget.querySelector( '[data-sfa-action="prev"]' );
		var next = widget.querySelector( '[data-sfa-action="next"]' );

		function render() {
			start = Math.min( Math.max( start, 0 ), Math.max( 0, total - size ) );
			rows.forEach( function ( row, index ) {
				row.hidden = index < start || index >= start + size;
			} );
			label.textContent = ( total ? start + 1 : 0 ) + '–' + Math.min( start + size, total ) + ' von ' + total;
			prev.disabled = start <= 0;
			next.disabled = start + size >= total;
		}

		prev.addEventListener( 'click', function () {
			start -= size;
			render();
		} );
		next.addEventListener( 'click', function () {
			start += size;
			render();
		} );
	}

	function initTeamSelect( widget ) {
		var select = widget.querySelector( '[data-sfa-select]' );
		var slot = widget.querySelector( '[data-sfa-slot]' );
		var shown = select.value;

		select.addEventListener( 'change', function () {
			var previous = shown;
			shown = select.value;
			setNotice( widget, '' );
			reload(
				'team-games',
				{
					team_id: shown,
					season: widget.dataset.sfaSeason,
					page_size: widget.dataset.sfaPageSize
				},
				slot,
				false,
				function () {
					// The table still shows the previous team, so the select has to as well.
					shown = previous;
					select.value = previous;
					setNotice( widget, config.retry );
				}
			);
		} );
	}

	function initLeague( widget ) {
		var params = parseJson( widget.dataset.sfaParams );

		widget.querySelectorAll( '[data-sfa-action]' ).forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				var context = parseJson( button.dataset.sfaContext );
				if ( ! context.round ) {
					return;
				}
				var request = { ...params, round: context.round };
				setNotice( widget, '' );
				reload( 'league-games', request, widget, true, function () {
					setNotice( widget, config.retry );
				} );
			} );
		} );
	}

	function init( root ) {
		root.querySelectorAll( '[data-sfa-widget]' ).forEach( function ( widget ) {
			if ( widget.dataset.sfaReady ) {
				return;
			}
			widget.dataset.sfaReady = '1';

			switch ( widget.dataset.sfaWidget ) {
				case 'week':
					initWeek( widget );
					break;
				case 'pager':
					initPager( widget );
					break;
				case 'team-select':
					initTeamSelect( widget );
					break;
				case 'league':
					initLeague( widget );
					break;
			}
		} );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', function () {
			init( document );
		} );
	} else {
		init( document );
	}
}() );
