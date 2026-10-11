<?php
/**
 * Reading, paging and preparing the grid rows of API tables.
 *
 * @link       https://flaviowaser.ch
 * @since      2.0.1
 *
 * @package    SWFL
 * @subpackage SWFL_Plugin/includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reading, paging and preparing the grid rows of API tables.
 *
 * Split out of SWFL_Widgets, which keeps the widget output and the REST routes.
 *
 * @since      2.0.1
 * @package    SWFL
 * @subpackage SWFL_Plugin/includes
 * @author     Flavio Waser <kontakt@flawas.ch>
 */
class SWFL_Table_Data {

	/**
	 * Drop empty query parameters so they do not end up in the request URL or cache key.
	 *
	 * @since 1.1.0
	 * @param array $params Query parameters.
	 * @return array Parameters with a non-empty, non-zero value.
	 */
	public static function clean_params( $params ) {
		return array_filter(
			$params,
			static function ( $value ) {
				return '' !== $value && null !== $value && 0 !== $value;
			}
		);
	}

	/**
	 * Flatten the rows of all regions of a table response.
	 *
	 * @since 1.1.0
	 * @param array $data The `data` part of an API response.
	 * @return array Rows.
	 */
	public static function get_rows( $data ) {
		$rows = array();
		if ( isset( $data['regions'] ) && is_array( $data['regions'] ) ) {
			foreach ( $data['regions'] as $region ) {
				if ( isset( $region['rows'] ) && is_array( $region['rows'] ) ) {
					$rows = array_merge( $rows, $region['rows'] );
				}
			}
		}
		return $rows;
	}

	/**
	 * Get the slider context of a page for one direction.
	 *
	 * @since 1.1.0
	 * @param array  $data      The `data` part of an API response.
	 * @param string $direction Either `prev` or `next`.
	 * @return array Context parameters, empty when there is no further page.
	 */
	public static function get_slider_context( $data, $direction ) {
		if ( isset( $data['slider'][ $direction ]['set_in_context'] ) && is_array( $data['slider'][ $direction ]['set_in_context'] ) ) {
			return $data['slider'][ $direction ]['set_in_context'];
		}
		return array();
	}

	/**
	 * Fetch a paginated table completely, following the slider in both directions.
	 *
	 * The API pages games (10 per page); the official components merge all pages client-side.
	 *
	 * @since 1.1.0
	 * @param string $endpoint      API endpoint.
	 * @param array  $params        Query parameters of the first request.
	 * @param bool   $force_refresh Optional. Skip reading the cache for every page (cache warm-up). Default false.
	 * @return array|WP_Error Array with `data` (first page) and `rows` (all pages), or WP_Error.
	 */
	public static function fetch_all_pages( $endpoint, $params, $force_refresh = false ) {
		$client = SWFL_Widgets::get_client();
		$first  = $client->fetch_data( $endpoint, $params, null, $force_refresh );
		if ( is_wp_error( $first ) ) {
			return $first;
		}
		if ( ! isset( $first['data']['regions'] ) ) {
			return new WP_Error( 'swfl_invalid_response', 'Unexpected response shape' );
		}

		$rows = self::get_rows( $first['data'] );
		$seen = array();

		foreach ( array( 'prev', 'next' ) as $direction ) {
			$rows = self::follow_slider( $client, $endpoint, $params, $first['data'], $direction, $rows, $seen, $force_refresh );
		}

		return array(
			'data' => $first['data'],
			'rows' => $rows,
		);
	}

	/**
	 * Follow the slider in one direction and merge the rows of every page into the collected rows.
	 *
	 * @since 2.0.1
	 * @param SWFL_Client $client        API client.
	 * @param string      $endpoint      API endpoint.
	 * @param array       $params        Query parameters of the first request.
	 * @param array       $data          Data of the first page.
	 * @param string      $direction     `prev` or `next`.
	 * @param array       $rows          Rows collected so far.
	 * @param array       $seen          Contexts already requested, shared between both directions.
	 * @param bool        $force_refresh Optional. Skip reading the cache for every page. Default false.
	 * @return array Rows including the pages of this direction.
	 */
	private static function follow_slider( $client, $endpoint, $params, $data, $direction, $rows, &$seen, $force_refresh = false ) {
		$context = self::get_slider_context( $data, $direction );
		for ( $i = 0; $i < SWFL_Widgets::MAX_PAGES && ! empty( $context ); $i++ ) {
			$key = wp_json_encode( $context );
			if ( isset( $seen[ $key ] ) ) {
				break;
			}
			$seen[ $key ] = true;

			$page = $client->fetch_data( $endpoint, array_merge( $params, $context ), null, $force_refresh );
			if ( is_wp_error( $page ) || ! isset( $page['data'] ) ) {
				break;
			}
			$page_rows = self::get_rows( $page['data'] );
			if ( empty( $page_rows ) ) {
				break;
			}

			$rows    = 'prev' === $direction ? array_merge( $page_rows, $rows ) : array_merge( $rows, $page_rows );
			$context = self::get_slider_context( $page['data'], $direction );
		}

		return $rows;
	}

	/**
	 * Get the visible text of a cell.
	 *
	 * @since 1.1.0
	 * @param array $cell Cell.
	 * @return string Text, multiple lines joined with a space.
	 */
	public static function get_cell_text( $cell ) {
		if ( isset( $cell['text'] ) ) {
			return trim( implode( ' ', array_map( 'strval', (array) $cell['text'] ) ) );
		}
		if ( isset( $cell['value'] ) && is_scalar( $cell['value'] ) ) {
			return trim( (string) $cell['value'] );
		}
		return '';
	}

	/**
	 * Get the text of all cells of a row.
	 *
	 * @since 1.1.0
	 * @param array $row Row.
	 * @return string Text.
	 */
	public static function get_row_text( $row ) {
		$parts = array();
		if ( isset( $row['cells'] ) && is_array( $row['cells'] ) ) {
			foreach ( $row['cells'] as $cell ) {
				$parts[] = self::get_cell_text( $cell );
			}
		}
		return trim( implode( ' ', $parts ) );
	}

	/**
	 * Get the game id of a row, from the row link or any game_detail cell link.
	 *
	 * @since 1.1.0
	 * @param array $row Row.
	 * @return int Game id, 0 when none.
	 */
	public static function get_game_id( $row ) {
		if ( isset( $row['link']['ids'][0] ) && isset( $row['link']['page'] ) && 'game_detail' === $row['link']['page'] ) {
			return absint( $row['link']['ids'][0] );
		}
		if ( isset( $row['cells'] ) && is_array( $row['cells'] ) ) {
			foreach ( $row['cells'] as $cell ) {
				if ( isset( $cell['link']['ids'][0] ) && isset( $cell['link']['page'] ) && 'game_detail' === $cell['link']['page'] ) {
					return absint( $cell['link']['ids'][0] );
				}
			}
		}
		return 0;
	}

	/**
	 * Get the date of a game row as Y-m-d, resolving "heute" and "gestern".
	 *
	 * @since 1.1.0
	 * @param array $row Row.
	 * @return string Date, empty when the row has none.
	 */
	public static function get_row_date( $row ) {
		$text  = self::get_row_text( $row );
		$lower = function_exists( 'mb_strtolower' ) ? mb_strtolower( $text ) : strtolower( $text );

		if ( false !== strpos( $lower, 'heute' ) ) {
			$date = current_time( 'Y-m-d' );
		} elseif ( false !== strpos( $lower, 'gestern' ) ) {
			$date = gmdate( 'Y-m-d', strtotime( current_time( 'Y-m-d' ) . ' UTC' ) - DAY_IN_SECONDS );
		} else {
			$date = self::parse_date_text( $text );
		}

		return $date;
	}

	/**
	 * Find a date in a text: dd.mm.yy(yy) or yyyy-mm-dd.
	 *
	 * @since 2.0.1
	 * @param string $text Text that may contain a date.
	 * @return string Date as Y-m-d, empty when none is found.
	 */
	private static function parse_date_text( $text ) {
		$date = '';
		if ( preg_match( '/(\d{1,2})\.(\d{1,2})\.(\d{2,4})/', $text, $m ) ) {
			$year = (int) $m[3];
			if ( $year < 100 ) {
				$year += 2000;
			}
			$date = sprintf( '%04d-%02d-%02d', $year, (int) $m[2], (int) $m[1] );
		} elseif ( preg_match( '/(\d{4})-(\d{2})-(\d{2})/', $text, $m ) ) {
			$date = $m[0];
		}

		return $date;
	}

	/**
	 * Drop cancelled games and sort by date, keeping the API order for equal dates.
	 *
	 * @since 1.1.0
	 * @param array $rows Rows.
	 * @return array Rows, each with an added `sfa_date` key.
	 */
	public static function prepare_game_rows( $rows ) {
		$prepared = array();
		foreach ( $rows as $index => $row ) {
			if ( false !== stripos( self::get_row_text( $row ), 'abgesagt' ) ) {
				continue;
			}
			$row['sfa_date']  = self::get_row_date( $row );
			$row['sfa_index'] = $index;
			$prepared[]       = $row;
		}
		usort(
			$prepared,
			static function ( $a, $b ) {
				$by_date = strcmp( $a['sfa_date'], $b['sfa_date'] );
				return 0 !== $by_date ? $by_date : $a['sfa_index'] - $b['sfa_index'];
			}
		);
		return $prepared;
	}

	/**
	 * Normalise the list attributes of a shortcode (limit, offset, show_upcoming, show_past, show_pagination).
	 *
	 * @since 2.0.4
	 * @param array $atts Shortcode attributes, already merged with their defaults.
	 * @return array Keys `limit`, `offset`, `upcoming`, `past` and `pagination`.
	 */
	public static function get_list_options( $atts ) {
		return array(
			'limit'      => isset( $atts['limit'] ) ? min( absint( $atts['limit'] ), 200 ) : 0,
			'offset'     => isset( $atts['offset'] ) ? absint( $atts['offset'] ) : 0,
			'upcoming'   => isset( $atts['show_upcoming'] ) && rest_sanitize_boolean( $atts['show_upcoming'] ),
			'past'       => isset( $atts['show_past'] ) && rest_sanitize_boolean( $atts['show_past'] ),
			'pagination' => isset( $atts['show_pagination'] ) && rest_sanitize_boolean( $atts['show_pagination'] ),
		);
	}

	/**
	 * Whether any list option asks for a trimmed, static list instead of the navigable widget.
	 *
	 * @since 2.0.4
	 * @param array $list_args Options from get_list_options().
	 * @return bool
	 */
	public static function is_list_mode( $list_args ) {
		return ! empty( $list_args ) && ( ! empty( $list_args['limit'] ) || ! empty( $list_args['offset'] ) || ! empty( $list_args['upcoming'] ) || ! empty( $list_args['past'] ) );
	}

	/**
	 * Keep the game rows that have not been played yet.
	 *
	 * Rows without a parseable date are kept instead of silently dropped.
	 *
	 * @since 2.0.4
	 * @param array $rows Game rows with an `sfa_date`.
	 * @return array Upcoming rows.
	 */
	public static function filter_upcoming_rows( $rows ) {
		$today = current_time( 'Y-m-d' );

		return array_values(
			array_filter(
				$rows,
				static function ( $row ) use ( $today ) {
					return empty( $row['sfa_date'] ) || $row['sfa_date'] >= $today;
				}
			)
		);
	}

	/**
	 * Keep the game rows of days before today, most recent first.
	 *
	 * Rows without a parseable date are dropped, as they cannot be told from a game that is still to come.
	 *
	 * @since 2.0.4
	 * @param array $rows Game rows with an `sfa_date`.
	 * @return array Past rows, newest first.
	 */
	public static function filter_past_rows( $rows ) {
		$today = current_time( 'Y-m-d' );

		return array_reverse(
			array_values(
				array_filter(
					$rows,
					static function ( $row ) use ( $today ) {
						return ! empty( $row['sfa_date'] ) && $row['sfa_date'] < $today;
					}
				)
			)
		);
	}

	/**
	 * Page requested through the `swfl_page` query argument.
	 *
	 * Read-only navigation, so no nonce is needed.
	 *
	 * @since 2.0.4
	 * @return int Page number, at least 1.
	 */
	public static function get_requested_page() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only pagination.
		return isset( $_GET['swfl_page'] ) ? max( 1, absint( wp_unslash( $_GET['swfl_page'] ) ) ) : 1;
	}

	/**
	 * Cut a row list down to the requested slice. The full response stays cached, slicing is local.
	 *
	 * @since 2.0.4
	 * @param array $rows       Rows.
	 * @param int   $limit      Rows per page, 0 for all remaining rows.
	 * @param int   $offset     Rows to skip at the start.
	 * @param bool  $pagination Whether to honour the `swfl_page` query argument.
	 * @return array Keys `rows`, `page` and `pages`.
	 */
	public static function paginate( $rows, $limit, $offset, $pagination ) {
		$rows  = array_slice( array_values( $rows ), $offset );
		$limit = absint( $limit );
		if ( 0 === $limit ) {
			return array(
				'rows'  => $rows,
				'page'  => 1,
				'pages' => 1,
			);
		}

		$pages = max( 1, (int) ceil( count( $rows ) / $limit ) );
		$page  = $pagination ? min( self::get_requested_page(), $pages ) : 1;

		return array(
			'rows'  => array_slice( $rows, ( $page - 1 ) * $limit, $limit ),
			'page'  => $page,
			'pages' => $pages,
		);
	}

	/**
	 * Print previous/next links for a paginated list; nothing for a single page.
	 *
	 * @since 2.0.4
	 * @param int $page  Current page.
	 * @param int $pages Number of pages.
	 * @return void
	 */
	public static function render_pagination( $page, $pages ) {
		if ( $pages < 2 ) {
			return;
		}

		$prev_url = $page > 2 ? add_query_arg( 'swfl_page', $page - 1 ) : remove_query_arg( 'swfl_page' );
		$next_url = add_query_arg( 'swfl_page', $page + 1 );
		?>
		<nav class="sfa-widget__controls sfa-pagination" aria-label="<?php esc_attr_e( 'Seitennavigation', 'swiss-floorball-api' ); ?>">
			<?php if ( $page > 1 ) : ?>
				<a class="sfa-btn" rel="prev" href="<?php echo esc_url( $prev_url ); ?>"><?php esc_html_e( 'Zurück', 'swiss-floorball-api' ); ?></a>
			<?php else : ?>
				<span class="sfa-btn sfa-btn--disabled" aria-disabled="true"><?php esc_html_e( 'Zurück', 'swiss-floorball-api' ); ?></span>
			<?php endif; ?>
			<span class="sfa-widget__label">
				<?php
				echo esc_html(
					sprintf(
						/* translators: 1: current page, 2: number of pages. */
						__( 'Seite %1$d von %2$d', 'swiss-floorball-api' ),
						$page,
						$pages
					)
				);
				?>
			</span>
			<?php if ( $page < $pages ) : ?>
				<a class="sfa-btn" rel="next" href="<?php echo esc_url( $next_url ); ?>"><?php esc_html_e( 'Weiter', 'swiss-floorball-api' ); ?></a>
			<?php else : ?>
				<span class="sfa-btn sfa-btn--disabled" aria-disabled="true"><?php esc_html_e( 'Weiter', 'swiss-floorball-api' ); ?></span>
			<?php endif; ?>
		</nav>
		<?php
	}

	/**
	 * Whether a cell links to the game detail (date, time or result cells).
	 *
	 * @since 1.1.0
	 * @param string $text Cell text.
	 * @return bool
	 */
	public static function is_game_link_cell( $text ) {
		// Separate patterns (a date, a time or a score) keep each expression simple.
		foreach ( array( '/\d{1,2}\.\d{1,2}\.\d{2,4}/', '/\d{1,2}:\d{2}/', '/\d+\s*[:–-]\s*\d+/u' ) as $pattern ) {
			if ( preg_match( $pattern, $text ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Find the columns that are hidden when the table gets narrow, so wide tables fit without scrolling.
	 *
	 * Two levels: `sfa-col-minor` (hidden below 520px of table width) for the detail columns of the ranking
	 * (SoW, SnV, NnV, PQ), the score separator, the league column and the venue in wide tables; `sfa-col-tiny`
	 * (hidden below 380px) for logo columns and the venue of narrower tables.
	 *
	 * @since 1.1.0
	 * @param string[] $labels Column labels by index.
	 * @return array<int,string> CSS class by column index, only for columns that can be hidden.
	 */
	public static function get_minor_columns( $labels ) {
		$minor = array();
		foreach ( $labels as $index => $label ) {
			if ( in_array( $label, array( 'SoW', 'SnV', 'NnV', 'PQ', '-', 'Liga / Gruppe' ), true ) || ( 'Ort' === $label && count( $labels ) >= 6 ) ) {
				$minor[ $index ] = 'sfa-col-minor';
			} elseif ( 'Ort' === $label || '' === $label ) {
				$minor[ $index ] = 'sfa-col-tiny';
			}
		}
		return $minor;
	}
}
