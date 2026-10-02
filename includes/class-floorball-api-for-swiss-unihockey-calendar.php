<?php
/**
 * Calendar feed of games (iCalendar REST route and ICS output).
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
 * Calendar feed of games (iCalendar REST route and ICS output).
 *
 * Split out of Swiss_Floorball_API_Widgets, which keeps the widget output and the REST routes.
 *
 * @since      2.0.1
 * @package    Swiss_Floorball_Api
 * @subpackage Swiss_Floorball_Api/includes
 * @author     Flavio Waser <kontakt@flawas.ch>
 */
class Swiss_Floorball_API_Calendar {

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
			$params['season'] = Swiss_Floorball_API_Display::get_current_season();
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

		$result = Swiss_Floorball_API_Table_Data::fetch_all_pages( 'games', Swiss_Floorball_API_Table_Data::clean_params( $params ) );
		if ( is_wp_error( $result ) ) {
			$result->add_data( array( 'status' => 502 ) );
			return $result;
		}

		$title = isset( $result['data']['title'] ) && is_string( $result['data']['title'] ) ? $result['data']['title'] : 'Swiss Floorball';
		return self::build_calendar( $title, isset( $result['data']['headers'] ) ? $result['data']['headers'] : array(), Swiss_Floorball_API_Table_Data::prepare_game_rows( $result['rows'] ) );
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
		$columns = array();
		foreach ( $headers as $index => $header ) {
			$text = isset( $header['text'] ) ? (string) $header['text'] : '';
			foreach ( array(
				'date'   => '/datum|zeit/iu',
				'place'  => '/^ort/iu',
				'home'   => '/heim/iu',
				'away'   => '/gast/iu',
				'league' => '/liga|gruppe/iu',
			) as $key => $pattern ) {
				if ( preg_match( $pattern, $text ) ) {
					$columns[ $key ][] = $index;
				}
			}
		}
		$zone  = new DateTimeZone( 'Europe/Zurich' );
		$utc   = new DateTimeZone( 'UTC' );
		$host  = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		$stamp = gmdate( self::ICAL_UTC_FORMAT );

		$lines = array(
			'BEGIN:VCALENDAR',
			'VERSION:2.0',
			'PRODID:-//Swiss Floorball API//WordPress//DE',
			'CALSCALE:GREGORIAN',
			'X-WR-CALNAME:' . self::escape_ics_text( $title ),
			'X-PUBLISHED-TTL:PT1H',
		);

		foreach ( $rows as $row ) {
			$cells = isset( $row['cells'] ) && is_array( $row['cells'] ) ? $row['cells'] : array();
			$text  = static function ( $key ) use ( $columns, $cells ) {
				$parts = array();
				foreach ( isset( $columns[ $key ] ) ? $columns[ $key ] : array() as $index ) {
					if ( isset( $cells[ $index ] ) ) {
						$parts[] = Swiss_Floorball_API_Table_Data::get_cell_text( $cells[ $index ] );
					}
				}
				return trim( implode( ' ', $parts ) );
			};

			$date = $row['sfa_date'];
			if ( '' === $date ) {
				continue;
			}
			$home    = $text( 'home' );
			$away    = $text( 'away' );
			$game_id = Swiss_Floorball_API_Table_Data::get_game_id( $row );

			$lines[] = 'BEGIN:VEVENT';
			$lines[] = 'UID:' . ( $game_id ? 'game-' . $game_id : substr( hash( 'sha256', $date . $home . $away ), 0, 32 ) ) . '@' . $host;
			$lines[] = 'DTSTAMP:' . $stamp;

			if ( preg_match( '/(\d{1,2}):(\d{2})/', $text( 'date' ), $time ) ) {
				$start   = new DateTime( $date . ' ' . sprintf( '%02d:%02d', $time[1], $time[2] ), $zone );
				$end     = clone $start;
				$lines[] = 'DTSTART:' . $start->setTimezone( $utc )->format( self::ICAL_UTC_FORMAT );
				$lines[] = 'DTEND:' . $end->modify( '+2 hours' )->setTimezone( $utc )->format( self::ICAL_UTC_FORMAT );
			} else {
				$lines[] = 'DTSTART;VALUE=DATE:' . str_replace( '-', '', $date );
			}

			$lines[] = 'SUMMARY:' . self::escape_ics_text( implode( ' – ', array_filter( array( $home, $away ) ) ) );
			if ( '' !== $text( 'place' ) ) {
				$lines[] = 'LOCATION:' . self::escape_ics_text( $text( 'place' ) );
			}
			if ( '' !== $text( 'league' ) ) {
				$lines[] = 'DESCRIPTION:' . self::escape_ics_text( $text( 'league' ) );
			}
			if ( $game_id ) {
				$lines[] = 'URL:' . Swiss_Floorball_API_Widgets::GAME_LINK_BASE . $game_id;
			}
			$lines[] = 'END:VEVENT';
		}

		$lines[] = 'END:VCALENDAR';

		return implode( "\r\n", array_map( array( __CLASS__, 'fold_ics_line' ), $lines ) ) . "\r\n";
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
