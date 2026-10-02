<?php
/**
 * Reading, paging and preparing the grid rows of API tables.
 *
 * @link       https://flaviowaser.ch
 * @since      2.0.1
 *
 * @package    Swiss_Floorball_Api
 * @subpackage Swiss_Floorball_Api/includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reading, paging and preparing the grid rows of API tables.
 *
 * Split out of Swiss_Floorball_API_Widgets, which keeps the widget output and the REST routes.
 *
 * @since      2.0.1
 * @package    Swiss_Floorball_Api
 * @subpackage Swiss_Floorball_Api/includes
 * @author     Flavio Waser <kontakt@flawas.ch>
 */
class Swiss_Floorball_API_Table_Data {

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
	 * @param string $endpoint API endpoint.
	 * @param array  $params   Query parameters of the first request.
	 * @return array|WP_Error Array with `data` (first page) and `rows` (all pages), or WP_Error.
	 */
	public static function fetch_all_pages( $endpoint, $params ) {
		$client = Swiss_Floorball_API_Widgets::get_client();
		$first  = $client->fetch_data( $endpoint, $params );
		if ( is_wp_error( $first ) ) {
			return $first;
		}
		if ( ! isset( $first['data']['regions'] ) ) {
			return new WP_Error( 'swfl_invalid_response', 'Unexpected response shape' );
		}

		$rows = self::get_rows( $first['data'] );
		$seen = array();

		foreach ( array( 'prev', 'next' ) as $direction ) {
			$rows = self::follow_slider( $client, $endpoint, $params, $first['data'], $direction, $rows, $seen );
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
	 * @param Swiss_Floorball_API_Client $client    API client.
	 * @param string                     $endpoint  API endpoint.
	 * @param array                      $params    Query parameters of the first request.
	 * @param array                      $data      Data of the first page.
	 * @param string                     $direction `prev` or `next`.
	 * @param array                      $rows      Rows collected so far.
	 * @param array                      $seen      Contexts already requested, shared between both directions.
	 * @return array Rows including the pages of this direction.
	 */
	private static function follow_slider( $client, $endpoint, $params, $data, $direction, $rows, &$seen ) {
		$context = self::get_slider_context( $data, $direction );
		for ( $i = 0; $i < Swiss_Floorball_API_Widgets::MAX_PAGES && ! empty( $context ); $i++ ) {
			$key = wp_json_encode( $context );
			if ( isset( $seen[ $key ] ) ) {
				break;
			}
			$seen[ $key ] = true;

			$page = $client->fetch_data( $endpoint, array_merge( $params, $context ) );
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
			return current_time( 'Y-m-d' );
		}
		if ( false !== strpos( $lower, 'gestern' ) ) {
			return gmdate( 'Y-m-d', strtotime( current_time( 'Y-m-d' ) . ' UTC' ) - DAY_IN_SECONDS );
		}
		if ( preg_match( '/(\d{1,2})\.(\d{1,2})\.(\d{2,4})/', $text, $m ) ) {
			$year = (int) $m[3];
			if ( $year < 100 ) {
				$year += 2000;
			}
			return sprintf( '%04d-%02d-%02d', $year, (int) $m[2], (int) $m[1] );
		}
		if ( preg_match( '/(\d{4})-(\d{2})-(\d{2})/', $text, $m ) ) {
			return $m[0];
		}
		return '';
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
