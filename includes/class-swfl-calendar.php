<?php
/**
 * Calendar feed of games (iCalendar REST route and ICS output).
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
 * Calendar feed of games (iCalendar REST route and ICS output).
 *
 * Split out of SWFL_Widgets, which keeps the widget output and the REST routes.
 *
 * @since      2.0.1
 * @package    SWFL
 * @subpackage SWFL_Plugin/includes
 * @author     Flavio Waser <kontakt@flawas.ch>
 */
class SWFL_Calendar {

	/**
	 * Date format of iCalendar UTC timestamps.
	 *
	 * @since 2.0.0
	 * @var   string
	 */
	const ICAL_UTC_FORMAT = 'Ymd\THis\Z';

	/**
	 * Send the calendar route response as an iCalendar file instead of JSON.
	 *
	 * @since 1.1.0
	 * @param bool             $served  Whether the request has already been served.
	 * @param WP_HTTP_Response $result  Result to send.
	 * @param WP_REST_Request  $request Request.
	 * @return bool Whether the request has been served.
	 */
	public static function serve_calendar( $served, $result, $request ) {
		if ( '/swfl/v1/calendar' !== $request->get_route() || ! is_string( $result->get_data() ) ) {
			return $served;
		}
		header( 'Content-Type: text/calendar; charset=utf-8' );
		header( 'Content-Disposition: inline; filename="swiss-floorball.ics"' );
		echo $result->get_data(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- iCalendar text, every value is escaped by escape_ics_text().
		return true;
	}

	/**
	 * REST callback: iCalendar feed of the games of a team, a club or a group.
	 *
	 * Replaces the calendar export of the previous API, which is no longer available.
	 *
	 * @since 1.1.0
	 * @param WP_REST_Request $request Request.
	 * @return string|WP_Error iCalendar text, or an error.
	 */
	public static function rest_calendar( $request ) {
		$params = array( 'season' => absint( $request->get_param( 'season' ) ) );
		if ( ! $params['season'] ) {
			$params['season'] = SWFL_Display::get_current_season();
		}

		if ( absint( $request->get_param( 'team_id' ) ) ) {
			$params['mode']    = 'team';
			$params['team_id'] = absint( $request->get_param( 'team_id' ) );
		} elseif ( absint( $request->get_param( 'club_id' ) ) ) {
			$params['mode']    = 'club';
			$params['club_id'] = absint( $request->get_param( 'club_id' ) );
		} elseif ( absint( $request->get_param( 'league' ) ) && absint( $request->get_param( 'game_class' ) ) ) {
			$params['mode']       = 'list';
			$params['league']     = absint( $request->get_param( 'league' ) );
			$params['game_class'] = absint( $request->get_param( 'game_class' ) );
			$params['group']      = sanitize_text_field( (string) $request->get_param( 'group' ) );
		} else {
			return new WP_Error( 'swfl_missing_params', 'team_id, club_id or league and game_class are required', array( 'status' => 400 ) );
		}

		$result = SWFL_Table_Data::fetch_all_pages( 'games', SWFL_Table_Data::clean_params( $params ) );
		if ( is_wp_error( $result ) ) {
			$result->add_data( array( 'status' => 502 ) );
			return $result;
		}

		$title = isset( $result['data']['title'] ) && is_string( $result['data']['title'] ) ? $result['data']['title'] : 'Swiss Floorball';
		return self::build_calendar( $title, isset( $result['data']['headers'] ) ? $result['data']['headers'] : array(), SWFL_Table_Data::prepare_game_rows( $result['rows'] ) );
	}

	/**
	 * Build an iCalendar document from game rows.
	 *
	 * Columns are found by their header text because the games tables differ per mode.
	 *
	 * @since 1.1.0
	 * @param string $title   Calendar name.
	 * @param array  $headers Table headers.
	 * @param array  $rows    Prepared game rows.
	 * @return string iCalendar text with CRLF line breaks.
	 */
	public static function build_calendar( $title, $headers, $rows ) {
		$columns = self::map_columns( $headers );
		$host    = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		$stamp   = gmdate( self::ICAL_UTC_FORMAT );

		$lines = array(
			'BEGIN:VCALENDAR',
			'VERSION:2.0',
			'PRODID:-//Swiss Floorball API//WordPress//DE',
			'CALSCALE:GREGORIAN',
			'X-WR-CALNAME:' . self::escape_ics_text( $title ),
			'X-PUBLISHED-TTL:PT1H',
		);

		foreach ( $rows as $row ) {
			$lines = array_merge( $lines, self::build_event( $row, $columns, $host, $stamp ) );
		}

		$lines[] = 'END:VCALENDAR';

		return implode( "\r\n", array_map( array( __CLASS__, 'fold_ics_line' ), $lines ) ) . "\r\n";
	}

	/**
	 * Find which columns hold the date, place, teams and league by their header text.
	 *
	 * @since 2.0.1
	 * @param array $headers Table headers.
	 * @return array Column indexes per key (date, place, home, away, league).
	 */
	private static function map_columns( $headers ) {
		$patterns = array(
			'date'   => '/datum|zeit/iu',
			'place'  => '/^ort/iu',
			'home'   => '/heim/iu',
			'away'   => '/gast/iu',
			'league' => '/liga|gruppe/iu',
		);
		$columns  = array();
		foreach ( $headers as $index => $header ) {
			$text = isset( $header['text'] ) ? (string) $header['text'] : '';
			foreach ( $patterns as $key => $pattern ) {
				if ( preg_match( $pattern, $text ) ) {
					$columns[ $key ][] = $index;
				}
			}
		}

		return $columns;
	}

	/**
	 * Joined text of the cells in the columns of a key.
	 *
	 * @since 2.0.1
	 * @param array  $columns Column indexes per key, see map_columns().
	 * @param array  $cells   Cells of one row.
	 * @param string $key     Column key.
	 * @return string Trimmed text.
	 */
	private static function column_text( $columns, $cells, $key ) {
		$parts   = array();
		$indexes = isset( $columns[ $key ] ) ? $columns[ $key ] : array();
		foreach ( $indexes as $index ) {
			if ( isset( $cells[ $index ] ) ) {
				$parts[] = SWFL_Table_Data::get_cell_text( $cells[ $index ] );
			}
		}

		return trim( implode( ' ', $parts ) );
	}

	/**
	 * Build the VEVENT lines of one game.
	 *
	 * @since 2.0.1
	 * @param array  $row     Prepared game row.
	 * @param array  $columns Column indexes per key, see map_columns().
	 * @param string $host    Site host used in the UID.
	 * @param string $stamp   DTSTAMP value.
	 * @return string[] Event lines, empty when the row has no date.
	 */
	private static function build_event( $row, $columns, $host, $stamp ) {
		$date = $row['sfa_date'];
		if ( '' === $date ) {
			return array();
		}

		$cells   = isset( $row['cells'] ) && is_array( $row['cells'] ) ? $row['cells'] : array();
		$home    = self::column_text( $columns, $cells, 'home' );
		$away    = self::column_text( $columns, $cells, 'away' );
		$place   = self::column_text( $columns, $cells, 'place' );
		$league  = self::column_text( $columns, $cells, 'league' );
		$game_id = SWFL_Table_Data::get_game_id( $row );

		$lines   = array(
			'BEGIN:VEVENT',
			'UID:' . ( $game_id ? 'game-' . $game_id : substr( hash( 'sha256', $date . $home . $away ), 0, 32 ) ) . '@' . $host,
			'DTSTAMP:' . $stamp,
		);
		$lines   = array_merge( $lines, self::build_event_times( $date, self::column_text( $columns, $cells, 'date' ) ) );
		$lines[] = 'SUMMARY:' . self::escape_ics_text( implode( ' – ', array_filter( array( $home, $away ) ) ) );
		if ( '' !== $place ) {
			$lines[] = 'LOCATION:' . self::escape_ics_text( $place );
		}
		if ( '' !== $league ) {
			$lines[] = 'DESCRIPTION:' . self::escape_ics_text( $league );
		}
		if ( $game_id ) {
			$lines[] = 'URL:' . SWFL_Widgets::GAME_LINK_BASE . $game_id;
		}
		$lines[] = 'END:VEVENT';

		return $lines;
	}

	/**
	 * Start and end lines of an event: a two-hour slot when the time is known, an all-day event otherwise.
	 *
	 * @since 2.0.1
	 * @param string $date      Game date as Y-m-d.
	 * @param string $time_text Text that may contain the kick-off time (HH:MM).
	 * @return string[] DTSTART and, with a time, DTEND lines.
	 */
	private static function build_event_times( $date, $time_text ) {
		if ( ! preg_match( '/(\d{1,2}):(\d{2})/', $time_text, $time ) ) {
			return array( 'DTSTART;VALUE=DATE:' . str_replace( '-', '', $date ) );
		}

		$start = new DateTime( $date . ' ' . sprintf( '%02d:%02d', $time[1], $time[2] ), new DateTimeZone( 'Europe/Zurich' ) );
		$end   = clone $start;
		$utc   = new DateTimeZone( 'UTC' );

		return array(
			'DTSTART:' . $start->setTimezone( $utc )->format( self::ICAL_UTC_FORMAT ),
			'DTEND:' . $end->modify( '+2 hours' )->setTimezone( $utc )->format( self::ICAL_UTC_FORMAT ),
		);
	}

	/**
	 * Escape a text value for an iCalendar property (RFC 5545).
	 *
	 * @since 1.1.0
	 * @param string $value Raw text.
	 * @return string Escaped text.
	 */
	public static function escape_ics_text( $value ) {
		return str_replace(
			array( '\\', ';', ',', "\r\n", "\n", "\r" ),
			array( '\\\\', '\\;', '\\,', '\\n', '\\n', '\\n' ),
			(string) $value
		);
	}

	/**
	 * Fold a content line to 75 octets (RFC 5545), without splitting multibyte characters.
	 *
	 * @since 1.1.0
	 * @param string $line Content line.
	 * @return string Folded line, continuation lines start with a space.
	 */
	public static function fold_ics_line( $line ) {
		$out   = '';
		$chunk = '';
		foreach ( preg_split( '//u', $line, -1, PREG_SPLIT_NO_EMPTY ) as $char ) {
			if ( strlen( $chunk ) + strlen( $char ) > 74 ) {
				$out  .= $chunk . "\r\n ";
				$chunk = '';
			}
			$chunk .= $char;
		}
		return $out . $chunk;
	}
}
