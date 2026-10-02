<?php
/**
 * Frontend widgets modelled on the official Swiss Unihockey web components.
 *
 * @link       https://github.com/swissunihockey/swissunihockey-webcomponents
 * @since      1.1.0
 *
 * @package    Swiss_Floorball_Api
 * @subpackage Swiss_Floorball_Api/includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the six official web components (league games, club team games, team games,
 * club games, ranking, Mobiliar topscorers) on the server.
 *
 * Paging, week navigation and team selection are progressive enhancements done by
 * public/js/swfl-widgets.js. Without JavaScript the initial state is still readable.
 * Components that need more data while navigating (league games, team selection) call
 * the REST routes registered here, which render the same markup.
 *
 * @since      1.1.0
 * @package    Swiss_Floorball_Api
 * @subpackage Swiss_Floorball_Api/includes
 * @author     Flavio Waser <kontakt@flawas.ch>
 */
class Swiss_Floorball_API_Widgets {

	/**
	 * Base of the public game link, as used by the official components.
	 *
	 * @since 1.1.0
	 * @var   string
	 */
	const GAME_LINK_BASE = 'https://myapp.swissunihockey.ch/link/game/';

	/**
	 * Upper bound of follow-up page requests per direction when collecting a paginated table.
	 *
	 * @since 1.1.0
	 * @var   int
	 */
	const MAX_PAGES = 50;

	/**
	 * Shared API client.
	 *
	 * @since 1.1.0
	 * @var   Swiss_Floorball_API_Client|null
	 */
	private static $client = null;

	/**
	 * Get the API client instance.
	 *
	 * @since 1.1.0
	 * @return Swiss_Floorball_API_Client
	 */
	private static function get_client() {
		if ( null === self::$client ) {
			self::$client = new Swiss_Floorball_API_Client();
		}
		return self::$client;
	}

	/**
	 * Register the REST routes used by the interactive widgets.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public static function register_routes() {
		register_rest_route(
			'swfl/v1',
			'/team-games',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'rest_team_games' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'team_id'   => array(
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
					'season'    => array(
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'page_size' => array(
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			'swfl/v1',
			'/league-games',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'rest_league_games' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'game_class' => array(
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
					'league'     => array(
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
					'season'     => array(
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'group'      => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'round'      => array(
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			'swfl/v1',
			'/calendar',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'rest_calendar' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'team_id'    => array(
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'club_id'    => array(
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'season'     => array(
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'league'     => array(
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'game_class' => array(
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'group'      => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		// The calendar route returns text/calendar instead of JSON.
		add_filter( 'rest_pre_serve_request', array( __CLASS__, 'serve_calendar' ), 10, 3 );
	}

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

		$result = self::fetch_all_pages( 'games', self::clean_params( $params ) );
		if ( is_wp_error( $result ) ) {
			$result->add_data( array( 'status' => 502 ) );
			return $result;
		}

		$title = isset( $result['data']['title'] ) && is_string( $result['data']['title'] ) ? $result['data']['title'] : 'Swiss Floorball';
		return self::build_calendar( $title, isset( $result['data']['headers'] ) ? $result['data']['headers'] : array(), self::prepare_game_rows( $result['rows'] ) );
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
	private static function build_calendar( $title, $headers, $rows ) {
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
		$stamp = gmdate( 'Ymd\THis\Z' );

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
						$parts[] = self::get_cell_text( $cells[ $index ] );
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
			$game_id = self::get_game_id( $row );

			$lines[] = 'BEGIN:VEVENT';
			$lines[] = 'UID:' . ( $game_id ? 'game-' . $game_id : md5( $date . $home . $away ) ) . '@' . $host;
			$lines[] = 'DTSTAMP:' . $stamp;

			if ( preg_match( '/(\d{1,2}):(\d{2})/', $text( 'date' ), $time ) ) {
				$start   = new DateTime( $date . ' ' . sprintf( '%02d:%02d', $time[1], $time[2] ), $zone );
				$end     = clone $start;
				$lines[] = 'DTSTART:' . $start->setTimezone( $utc )->format( 'Ymd\THis\Z' );
				$lines[] = 'DTEND:' . $end->modify( '+2 hours' )->setTimezone( $utc )->format( 'Ymd\THis\Z' );
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
				$lines[] = 'URL:' . self::GAME_LINK_BASE . $game_id;
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
	private static function escape_ics_text( $value ) {
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
	private static function fold_ics_line( $line ) {
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

	/**
	 * REST callback: team games widget.
	 *
	 * @since 1.1.0
	 * @param WP_REST_Request $request Request.
	 * @return array Rendered HTML.
	 */
	public static function rest_team_games( $request ) {
		ob_start();
		self::render_team_games(
			absint( $request->get_param( 'team_id' ) ),
			absint( $request->get_param( 'season' ) ),
			absint( $request->get_param( 'page_size' ) ),
			true
		);
		return array( 'html' => ob_get_clean() );
	}

	/**
	 * REST callback: league games widget.
	 *
	 * @since 1.1.0
	 * @param WP_REST_Request $request Request.
	 * @return array Rendered HTML.
	 */
	public static function rest_league_games( $request ) {
		$context = array();
		$round   = absint( $request->get_param( 'round' ) );
		if ( $round > 0 ) {
			$context['round'] = $round;
		}

		ob_start();
		self::render_league_games(
			absint( $request->get_param( 'game_class' ) ),
			absint( $request->get_param( 'league' ) ),
			absint( $request->get_param( 'season' ) ),
			sanitize_text_field( (string) $request->get_param( 'group' ) ),
			$context
		);
		return array( 'html' => ob_get_clean() );
	}

	/**
	 * Render the notice for a failed or empty API response.
	 *
	 * @since 1.1.0
	 * @param array|WP_Error $api_response Response returned by the client.
	 * @return void
	 */
	private static function render_error( $api_response ) {
		if ( is_wp_error( $api_response ) && 'swfl_endpoint_unavailable' === $api_response->get_error_code() ) {
			$message = __( 'Diese Daten sind in der kostenlosen API nicht verfügbar. Sie sind nur über die Partner-API verfügbar, die in den Plugin-Einstellungen aktiviert werden kann.', 'swiss-floorball-api' );
		} elseif ( is_wp_error( $api_response ) && in_array( $api_response->get_error_code(), array( 'swfl_partner_credentials', 'swfl_partner_auth' ), true ) ) {
			$message = __( 'Anmeldung an der Partner-API fehlgeschlagen. Bitte API Key und Secret in den Plugin-Einstellungen prüfen.', 'swiss-floorball-api' );
		} else {
			$message = __( 'Daten konnten nicht geladen werden.', 'swiss-floorball-api' );
		}
		echo '<div class="sfa-info-box sfa-info-box--danger" role="alert"><p>' . Swiss_Floorball_Api_Icons::get( 'error' ) . ' ' . esc_html( $message ) . '</p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Icon markup sanitized via wp_kses() in Swiss_Floorball_Api_Icons::get().
	}

	/**
	 * Render a notice for a missing required shortcode attribute.
	 *
	 * @since 1.1.0
	 * @param string $message Already translated message.
	 * @return void
	 */
	private static function render_missing( $message ) {
		echo '<div class="sfa-info-box sfa-info-box--danger" role="alert"><p>' . esc_html( $message ) . '</p></div>';
	}

	/**
	 * Drop empty query parameters so they do not end up in the request URL or cache key.
	 *
	 * @since 1.1.0
	 * @param array $params Query parameters.
	 * @return array Parameters with a non-empty, non-zero value.
	 */
	private static function clean_params( $params ) {
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
	private static function get_rows( $data ) {
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
	private static function get_slider_context( $data, $direction ) {
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
	private static function fetch_all_pages( $endpoint, $params ) {
		$client = self::get_client();
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
			$context = self::get_slider_context( $first['data'], $direction );
			for ( $i = 0; $i < self::MAX_PAGES && ! empty( $context ); $i++ ) {
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
		}

		return array(
			'data' => $first['data'],
			'rows' => $rows,
		);
	}

	/**
	 * Get the visible text of a cell.
	 *
	 * @since 1.1.0
	 * @param array $cell Cell.
	 * @return string Text, multiple lines joined with a space.
	 */
	private static function get_cell_text( $cell ) {
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
	private static function get_row_text( $row ) {
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
	private static function get_game_id( $row ) {
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
	private static function get_row_date( $row ) {
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
	private static function prepare_game_rows( $rows ) {
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
	private static function is_game_link_cell( $text ) {
		return (bool) preg_match( '/\d{1,2}\.\d{1,2}\.\d{2,4}|\d{1,2}:\d{2}|\d+\s*[:–-]\s*\d+/u', $text );
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
	private static function get_minor_columns( $labels ) {
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

	/**
	 * Render the table of a games or ranking response.
	 *
	 * @since 1.1.0
	 * @param array $headers   Header definitions (`text`, `align`).
	 * @param array $rows      Rows.
	 * @param array $args      {
	 *     Optional arguments.
	 *
	 *     @type bool   $drop_last  Drop the last column (the streaming column of league games).
	 *     @type array  $row_attrs  Extra attributes per row index, as name => value.
	 *     @type array  $hidden     Row indexes to render hidden.
	 *     @type string $caption    Visually hidden table caption.
	 *     @type bool   $admin_links Add a trailing column linking each game to its admin detail page.
	 * }
	 * @return void
	 */
	private static function render_table( $headers, $rows, $args = array() ) {
		$args      = wp_parse_args(
			$args,
			array(
				'drop_last'   => false,
				'row_attrs'   => array(),
				'hidden'      => array(),
				'caption'     => '',
				'admin_links' => false,
			)
		);
		$labels    = array();
		$col_count = is_array( $headers ) ? count( $headers ) : 0;
		if ( $args['drop_last'] && $col_count > 0 ) {
			--$col_count;
		}
		for ( $i = 0; $i < $col_count; $i++ ) {
			$labels[ $i ] = isset( $headers[ $i ]['text'] ) ? (string) $headers[ $i ]['text'] : '';
		}
		$minor = self::get_minor_columns( $labels );
		?>
		<div class="sfa-table-wrap">
			<table class="sfa-data-table">
				<?php if ( '' !== $args['caption'] ) : ?>
					<caption class="sfa-visually-hidden"><?php echo esc_html( $args['caption'] ); ?></caption>
				<?php endif; ?>
				<?php if ( $col_count > 0 ) : ?>
					<thead>
						<tr>
							<?php foreach ( $labels as $i => $label ) : ?>
								<?php
								$th_class = array();
								if ( isset( $headers[ $i ]['align'] ) && 'r' === $headers[ $i ]['align'] ) {
									$th_class[] = 'sfa-align-right';
								}
								if ( isset( $minor[ $i ] ) ) {
									$th_class[] = $minor[ $i ];
								}
								?>
								<th scope="col"<?php echo $th_class ? ' class="' . esc_attr( implode( ' ', $th_class ) ) . '"' : ''; ?>><?php echo esc_html( $label ); ?></th>
							<?php endforeach; ?>
							<?php if ( $args['admin_links'] ) : ?>
								<th scope="col"><?php esc_html_e( 'Aktionen', 'swiss-floorball-api' ); ?></th>
							<?php endif; ?>
						</tr>
					</thead>
				<?php endif; ?>
				<tbody>
					<?php foreach ( $rows as $index => $row ) : ?>
						<?php
						$game_id = self::get_game_id( $row );
						$attrs   = '';
						if ( isset( $args['row_attrs'][ $index ] ) ) {
							foreach ( $args['row_attrs'][ $index ] as $name => $value ) {
								$attrs .= ' ' . esc_attr( $name ) . '="' . esc_attr( $value ) . '"';
							}
						}
						$classes = ! empty( $row['highlight'] ) ? ' class="is-highlight"' : '';
						$hidden  = in_array( $index, $args['hidden'], true ) ? ' hidden' : '';
						?>
						<tr<?php echo $classes . $attrs . $hidden; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from constants and escaped attributes. ?>>
							<?php
							$cells = isset( $row['cells'] ) && is_array( $row['cells'] ) ? $row['cells'] : array();
							foreach ( $cells as $i => $cell ) {
								if ( $i >= $col_count && $col_count > 0 ) {
									break;
								}
								self::render_cell( $cell, isset( $labels[ $i ] ) ? $labels[ $i ] : '', $game_id, isset( $headers[ $i ]['align'] ) ? $headers[ $i ]['align'] : '', isset( $minor[ $i ] ) ? $minor[ $i ] : '' );
							}
							if ( $args['admin_links'] ) {
								echo '<td>';
								if ( $game_id > 0 ) {
									$details_url = add_query_arg(
										array(
											'page'     => 'floorball-api-for-swiss-unihockey-matches',
											'match_id' => $game_id,
										),
										admin_url( 'admin.php' )
									);
									echo '<a href="' . esc_url( $details_url ) . '">' . esc_html__( 'Details', 'swiss-floorball-api' ) . '</a>';
								}
								echo '</td>';
							}
							?>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Render one table cell: text or club logo, linked to the game where it carries game data.
	 *
	 * @since 1.1.0
	 * @param array  $cell    Cell.
	 * @param string $label   Column label, used as the mobile data-label.
	 * @param int    $game_id Game id of the row, 0 when none.
	 * @param string $align   Column alignment (`l`, `r`, `c`).
	 * @param string $minor   Class of a column that is hidden when the table gets narrow, empty otherwise.
	 * @return void
	 */
	private static function render_cell( $cell, $label, $game_id, $align, $minor = '' ) {
		$text  = self::get_cell_text( $cell );
		$image = isset( $cell['image']['url'] ) ? $cell['image']['url'] : ( isset( $cell['image'] ) && is_string( $cell['image'] ) ? $cell['image'] : '' );
		$class = array();
		if ( ! empty( $cell['highlight'] ) ) {
			$class[] = 'is-highlight';
		}
		if ( 'r' === $align ) {
			$class[] = 'sfa-align-right';
		}
		if ( '' !== $minor ) {
			$class[] = $minor;
		}

		echo '<td data-label="' . esc_attr( $label ) . '"' . ( $class ? ' class="' . esc_attr( implode( ' ', $class ) ) . '"' : '' ) . '>';

		if ( '' === $image && isset( $cell['link']['type'], $cell['link']['x'], $cell['link']['y'] ) && 'map' === $cell['link']['type'] && is_numeric( $cell['link']['x'] ) && is_numeric( $cell['link']['y'] ) ) {
			// The API gives the venue coordinates; link them to a map instead of showing plain text only.
			$map_url = add_query_arg(
				array(
					'mlat' => (float) $cell['link']['y'],
					'mlon' => (float) $cell['link']['x'],
				),
				'https://www.openstreetmap.org/'
			) . '#map=16/' . (float) $cell['link']['y'] . '/' . (float) $cell['link']['x'];
			echo '<a class="sfa-map-link" href="' . esc_url( $map_url ) . '" target="_blank" rel="noopener noreferrer">' . Swiss_Floorball_Api_Icons::get( 'place' ) . ' ' . esc_html( $text ) . '</a></td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Icon markup sanitized via wp_kses() in Swiss_Floorball_Api_Icons::get().
			return;
		}

		if ( '' !== $image ) {
			$content = '<span class="sfa-logo-cell"><img class="sfa-club-logo" src="' . esc_url( $image ) . '" alt="" loading="lazy" width="28" height="28" /></span>';
		} else {
			$content = esc_html( $text );
		}

		if ( $game_id > 0 && '' === $image && self::is_game_link_cell( $text ) ) {
			echo '<a href="' . esc_url( self::GAME_LINK_BASE . $game_id ) . '" target="_blank" rel="noopener noreferrer">' . $content . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $content is escaped above.
		} else {
			echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $content is escaped above.
		}

		echo '</td>';
	}

	/**
	 * Render the card title and subtitle of a response.
	 *
	 * @since 1.1.0
	 * @param array  $data     The `data` part of an API response.
	 * @param bool   $show     Whether to show the title.
	 * @param string $fallback Title used when the response has none.
	 * @param string $icon     Optional. Icon name shown before the title.
	 * @return void
	 */
	private static function render_title( $data, $show, $fallback = '', $icon = '' ) {
		if ( ! $show ) {
			return;
		}
		$title = isset( $data['title'] ) && is_string( $data['title'] ) && '' !== $data['title'] ? $data['title'] : $fallback;
		if ( '' !== $title ) {
			echo '<h3 class="sfa-section-title">' . ( '' !== $icon ? Swiss_Floorball_Api_Icons::get( $icon ) . ' ' : '' ) . esc_html( $title ) . '</h3>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Icon markup sanitized via wp_kses() in Swiss_Floorball_Api_Icons::get().
		}
		if ( isset( $data['subtitle'] ) && is_string( $data['subtitle'] ) && '' !== $data['subtitle'] ) {
			echo '<p class="sfa-widget__subtitle">' . esc_html( $data['subtitle'] ) . '</p>';
		}
	}

	/**
	 * Games of a club or team as a plain table in the widget look, for the admin pages.
	 *
	 * Same table and title markup as the shortcodes, without navigation scripts, plus a
	 * details link per game.
	 *
	 * @since 1.1.0
	 * @param string $mode     Games mode, `club` or `team`.
	 * @param int    $id       Club or team id.
	 * @param int    $season   Season (start year).
	 * @param string $fallback Title used when the response has none.
	 * @param string $caption  Visually hidden table caption.
	 * @return void
	 */
	public static function render_admin_games( $mode, $id, $season, $fallback, $caption ) {
		$result = self::fetch_all_pages(
			'games',
			array(
				'mode'        => $mode,
				$mode . '_id' => $id,
				'season'      => $season,
			)
		);
		if ( is_wp_error( $result ) ) {
			self::render_error( $result );
			return;
		}

		$rows = self::prepare_game_rows( $result['rows'] );
		echo '<div class="sfa-widget">';
		self::render_title( $result['data'], true, $fallback, 'hockey' );
		if ( $rows ) {
			self::render_table(
				isset( $result['data']['headers'] ) ? $result['data']['headers'] : array(),
				$rows,
				array(
					'caption'     => $caption,
					'admin_links' => true,
				)
			);
		} else {
			echo '<p class="sfa-empty">' . esc_html__( 'Keine Spiele gefunden.', 'swiss-floorball-api' ) . '</p>';
		}
		echo '</div>';
	}

	/**
	 * Club games, shown week by week (uniho-club-games).
	 *
	 * @since 1.1.0
	 * @param int $club_id Club id.
	 * @param int $season  Season (start year).
	 * @return void
	 */
	public static function render_club_games( $club_id, $season ) {
		if ( ! $club_id ) {
			self::render_missing( __( 'Attribut club_id fehlt.', 'swiss-floorball-api' ) );
			return;
		}

		$result = self::fetch_all_pages(
			'games',
			array(
				'mode'    => 'club',
				'club_id' => $club_id,
				'season'  => $season,
			)
		);
		if ( is_wp_error( $result ) ) {
			self::render_error( $result );
			return;
		}

		$rows       = self::prepare_game_rows( $result['rows'] );
		$today_ts   = strtotime( current_time( 'Y-m-d' ) . ' UTC' );
		$week_start = gmdate( 'Y-m-d', $today_ts - ( (int) gmdate( 'N', $today_ts ) - 1 ) * DAY_IN_SECONDS );
		$week_end   = gmdate( 'Y-m-d', strtotime( $week_start . ' UTC' ) + 6 * DAY_IN_SECONDS );

		// Outside the playing weeks the current week is empty; jump to the next game, or the last one.
		$in_week = false;
		$next    = '';
		$last    = '';
		foreach ( $rows as $row ) {
			$date = $row['sfa_date'];
			if ( '' === $date ) {
				continue;
			}
			if ( $date >= $week_start && $date <= $week_end ) {
				$in_week = true;
				break;
			}
			if ( $date > $week_end && ( '' === $next || $date < $next ) ) {
				$next = $date;
			}
			if ( $date < $week_start && $date > $last ) {
				$last = $date;
			}
		}
		$anchor = '' !== $next ? $next : $last;
		if ( ! $in_week && '' !== $anchor ) {
			$anchor_ts  = strtotime( $anchor . ' UTC' );
			$week_start = gmdate( 'Y-m-d', $anchor_ts - ( (int) gmdate( 'N', $anchor_ts ) - 1 ) * DAY_IN_SECONDS );
			$week_end   = gmdate( 'Y-m-d', strtotime( $week_start . ' UTC' ) + 6 * DAY_IN_SECONDS );
		}

		$row_attrs = array();
		$hidden    = array();
		$visible   = 0;
		foreach ( $rows as $index => $row ) {
			$row_attrs[ $index ] = array( 'data-sfa-date' => $row['sfa_date'] );
			if ( '' === $row['sfa_date'] || $row['sfa_date'] < $week_start || $row['sfa_date'] > $week_end ) {
				$hidden[] = $index;
			} else {
				++$visible;
			}
			$rows[ $index ]['highlight'] = false;
		}

		?>
		<div class="sfa-widget" data-sfa-widget="week" data-sfa-week-start="<?php echo esc_attr( $week_start ); ?>">
			<?php self::render_title( $result['data'], true, __( 'Clubspiele', 'swiss-floorball-api' ), 'hockey' ); ?>
			<div class="sfa-widget__controls">
				<button type="button" class="sfa-btn" data-sfa-action="prev"><?php Swiss_Floorball_Api_Icons::render( 'chevron_left' ); ?> <?php esc_html_e( 'Letzte Woche', 'swiss-floorball-api' ); ?></button>
				<span class="sfa-widget__label" data-sfa-label><?php echo esc_html( self::format_date( $week_start ) . ' – ' . self::format_date( $week_end ) ); ?></span>
				<button type="button" class="sfa-btn" data-sfa-action="next"><?php esc_html_e( 'Nächste Woche', 'swiss-floorball-api' ); ?> <?php Swiss_Floorball_Api_Icons::render( 'chevron_right' ); ?></button>
			</div>
			<?php
			self::render_table(
				isset( $result['data']['headers'] ) ? $result['data']['headers'] : array(),
				$rows,
				array(
					'row_attrs' => $row_attrs,
					'hidden'    => $hidden,
					'caption'   => __( 'Spiele des Clubs', 'swiss-floorball-api' ),
				)
			);
			?>
			<p class="sfa-empty" data-sfa-empty<?php echo $visible > 0 ? ' hidden' : ''; ?>><?php esc_html_e( 'Keine Spiele in dieser Woche gefunden.', 'swiss-floorball-api' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Games of one team, paged around the next game (uniho-team-games).
	 *
	 * @since 1.1.0
	 * @param int  $team_id   Team id.
	 * @param int  $season    Season (start year).
	 * @param int  $page_size Games per page, 4 when 0.
	 * @param bool $embedded  Whether the widget sits inside another one (no extra card styling).
	 * @return void
	 */
	public static function render_team_games( $team_id, $season, $page_size = 0, $embedded = false ) {
		if ( ! $team_id ) {
			self::render_missing( __( 'Attribut team_id fehlt.', 'swiss-floorball-api' ) );
			return;
		}
		$page_size = $page_size > 0 ? min( $page_size, 50 ) : 4;

		$result = self::fetch_all_pages(
			'games',
			array(
				'mode'    => 'team',
				'team_id' => $team_id,
				'season'  => $season,
			)
		);
		if ( is_wp_error( $result ) ) {
			self::render_error( $result );
			return;
		}

		$rows  = self::prepare_game_rows( $result['rows'] );
		$total = count( $rows );
		$today = current_time( 'Y-m-d' );

		// Start half a page before the next game; without a future game show the last page.
		$start = max( 0, $total - $page_size );
		foreach ( $rows as $index => $row ) {
			if ( '' !== $row['sfa_date'] && $row['sfa_date'] >= $today ) {
				$start = max( 0, $index - (int) floor( $page_size / 2 ) );
				break;
			}
		}
		$start = min( $start, max( 0, $total - $page_size ) );

		$hidden = array();
		foreach ( $rows as $index => $row ) {
			if ( $index < $start || $index >= $start + $page_size ) {
				$hidden[] = $index;
			}
			$rows[ $index ]['highlight'] = false;
		}

		?>
		<div class="sfa-widget" data-sfa-widget="pager" data-sfa-page-size="<?php echo esc_attr( $page_size ); ?>" data-sfa-start="<?php echo esc_attr( $start ); ?>">
			<?php self::render_title( $result['data'], ! $embedded, __( 'Teamspiele', 'swiss-floorball-api' ), 'hockey' ); ?>
			<div class="sfa-widget__controls">
				<button type="button" class="sfa-btn" data-sfa-action="prev" <?php disabled( $start <= 0 ); ?>><?php Swiss_Floorball_Api_Icons::render( 'chevron_left' ); ?> <?php esc_html_e( 'Frühere Spiele', 'swiss-floorball-api' ); ?></button>
				<span class="sfa-widget__label" data-sfa-label>
					<?php
					echo esc_html(
						sprintf(
							/* translators: 1: first game number, 2: last game number, 3: total number of games. */
							__( '%1$d–%2$d von %3$d', 'swiss-floorball-api' ),
							$total ? $start + 1 : 0,
							min( $start + $page_size, $total ),
							$total
						)
					);
					?>
				</span>
				<button type="button" class="sfa-btn" data-sfa-action="next" <?php disabled( $start + $page_size >= $total ); ?>><?php esc_html_e( 'Weitere Spiele', 'swiss-floorball-api' ); ?> <?php Swiss_Floorball_Api_Icons::render( 'chevron_right' ); ?></button>
			</div>
			<?php
			self::render_table(
				isset( $result['data']['headers'] ) ? $result['data']['headers'] : array(),
				$rows,
				array(
					'hidden'  => $hidden,
					'caption' => __( 'Spiele des Teams', 'swiss-floorball-api' ),
				)
			);
			if ( 0 === $total ) {
				echo '<p class="sfa-empty">' . esc_html__( 'Keine Teamspiele gefunden.', 'swiss-floorball-api' ) . '</p>';
			}
			?>
		</div>
		<?php
	}

	/**
	 * All teams of a club with a team selector (uniho-club-team-games).
	 *
	 * @since 1.1.0
	 * @param int $club_id   Club id.
	 * @param int $season    Season (start year).
	 * @param int $page_size Games per page.
	 * @return void
	 */
	public static function render_club_team_games( $club_id, $season, $page_size = 0 ) {
		if ( ! $club_id ) {
			self::render_missing( __( 'Attribut club_id fehlt.', 'swiss-floorball-api' ) );
			return;
		}

		$response = self::get_client()->fetch_data(
			'teams',
			array(
				'club_id' => $club_id,
				'season'  => $season,
				'mode'    => 'by_club',
			)
		);
		if ( is_wp_error( $response ) || empty( $response['entries'] ) ) {
			self::render_error( $response );
			return;
		}

		$teams = array();
		foreach ( $response['entries'] as $entry ) {
			if ( isset( $entry['set_in_context']['team_id'], $entry['text'] ) ) {
				$teams[ absint( $entry['set_in_context']['team_id'] ) ] = (string) $entry['text'];
			}
		}
		if ( empty( $teams ) ) {
			self::render_error( new WP_Error( 'swfl_empty', 'No teams' ) );
			return;
		}
		$first_team = (int) key( $teams );
		$select_id  = 'sfa-team-select-' . wp_unique_id();

		?>
		<div class="sfa-widget" data-sfa-widget="team-select" data-sfa-season="<?php echo esc_attr( $season ); ?>" data-sfa-page-size="<?php echo esc_attr( $page_size ); ?>">
			<h3 class="sfa-section-title"><?php Swiss_Floorball_Api_Icons::render( 'group' ); ?> <?php esc_html_e( 'Teamspiele nach Verein', 'swiss-floorball-api' ); ?></h3>
			<label class="sfa-widget__field" for="<?php echo esc_attr( $select_id ); ?>">
				<span><?php esc_html_e( 'Team auswählen', 'swiss-floorball-api' ); ?></span>
				<select id="<?php echo esc_attr( $select_id ); ?>" class="sfa-widget__select" data-sfa-select>
					<?php foreach ( $teams as $team_id => $team_name ) : ?>
						<option value="<?php echo esc_attr( $team_id ); ?>"><?php echo esc_html( $team_name ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<div data-sfa-slot aria-live="polite">
				<?php self::render_team_games( $first_team, $season, $page_size, true ); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Games of a league and group with round navigation (uniho-league-games).
	 *
	 * Rounds with several games between the same two teams (playoff series) are grouped
	 * into accordions; all other games stay in a plain table.
	 *
	 * @since 1.1.0
	 * @param int    $game_class Game class id.
	 * @param int    $league     League id.
	 * @param int    $season     Season (start year).
	 * @param string $group      Group name, e.g. "Gruppe 1".
	 * @param array  $context    Optional. Extra query context from the slider (e.g. round).
	 * @return void
	 */
	public static function render_league_games( $game_class, $league, $season, $group = '', $context = array() ) {
		if ( ! $game_class || ! $league ) {
			self::render_missing( __( 'Attribute game_class und league fehlen.', 'swiss-floorball-api' ) );
			return;
		}

		$base_params = array(
			'game_class' => $game_class,
			'league'     => $league,
			'season'     => $season,
			'group'      => $group,
		);
		$params      = array_merge( array( 'mode' => 'list' ), $base_params, $context );

		$response = self::get_client()->fetch_data( 'games', self::clean_params( $params ) );
		if ( is_wp_error( $response ) || ! isset( $response['data']['regions'] ) ) {
			self::render_error( $response );
			return;
		}

		$data    = $response['data'];
		$headers = isset( $data['headers'] ) && is_array( $data['headers'] ) ? $data['headers'] : array();
		$rows    = self::prepare_game_rows( self::get_rows( $data ) );
		$last    = $headers ? end( $headers ) : array();
		$drop    = isset( $last['text'] ) && '📺' === $last['text'];
		$prev    = self::get_slider_context( $data, 'prev' );
		$next    = self::get_slider_context( $data, 'next' );

		?>
		<div class="sfa-widget" data-sfa-widget="league" data-sfa-params="<?php echo esc_attr( wp_json_encode( $base_params ) ); ?>">
			<div class="sfa-widget__controls">
				<button type="button" class="sfa-btn" data-sfa-action="prev" data-sfa-context="<?php echo esc_attr( wp_json_encode( $prev ) ); ?>" <?php disabled( empty( $prev ) ); ?>><?php Swiss_Floorball_Api_Icons::render( 'chevron_left' ); ?> <?php esc_html_e( 'Frühere Spiele', 'swiss-floorball-api' ); ?></button>
				<span class="sfa-widget__label"><?php echo esc_html( ! empty( $data['slider']['text'] ) ? $data['slider']['text'] : __( 'Aktuelle Runde', 'swiss-floorball-api' ) ); ?></span>
				<button type="button" class="sfa-btn" data-sfa-action="next" data-sfa-context="<?php echo esc_attr( wp_json_encode( $next ) ); ?>" <?php disabled( empty( $next ) ); ?>><?php esc_html_e( 'Weitere Spiele', 'swiss-floorball-api' ); ?> <?php Swiss_Floorball_Api_Icons::render( 'chevron_right' ); ?></button>
			</div>
			<?php
			self::render_title( $data, true, '', 'hockey' );

			$groups  = self::group_playoff_series( $headers, $rows );
			$grouped = array();
			foreach ( $groups as $series ) {
				foreach ( $series['indexes'] as $index ) {
					$grouped[ $index ] = true;
				}
			}
			$single = array();
			foreach ( $rows as $index => $row ) {
				if ( ! isset( $grouped[ $index ] ) ) {
					$single[] = $row;
				}
			}

			foreach ( $groups as $position => $series ) {
				$series_rows = array();
				foreach ( $series['indexes'] as $index ) {
					$series_rows[] = $rows[ $index ];
				}
				?>
				<details class="sfa-playoff-group"<?php echo 0 === $position ? ' open' : ''; ?>>
					<summary>
						<span><?php echo esc_html( $series['title'] ); ?></span>
						<small>
							<?php
							echo esc_html(
								sprintf(
									/* translators: %d: number of games in the series. */
									_n( '%d Spiel', '%d Spiele', count( $series_rows ), 'swiss-floorball-api' ),
									count( $series_rows )
								)
							);
							?>
						</small>
					</summary>
					<?php self::render_table( $headers, $series_rows, array( 'drop_last' => $drop ) ); ?>
				</details>
				<?php
			}

			if ( $single ) {
				self::render_table( $headers, $single, array( 'drop_last' => $drop ) );
			} elseif ( empty( $groups ) ) {
				echo '<p class="sfa-empty">' . esc_html__( 'Keine Spiele gefunden.', 'swiss-floorball-api' ) . '</p>';
			}
			?>
		</div>
		<?php
	}

	/**
	 * Group games by team pairing and keep the pairings that played more than once.
	 *
	 * @since 1.1.0
	 * @param array $headers Table headers.
	 * @param array $rows    Prepared rows.
	 * @return array List of series with `title` and the row `indexes`.
	 */
	private static function group_playoff_series( $headers, $rows ) {
		$home_index = -1;
		$away_index = -1;
		foreach ( $headers as $index => $header ) {
			$label = isset( $header['text'] ) ? strtolower( (string) $header['text'] ) : '';
			if ( -1 === $home_index && false !== strpos( $label, 'heim' ) ) {
				$home_index = $index;
			}
			if ( -1 === $away_index && false !== strpos( $label, 'gast' ) ) {
				$away_index = $index;
			}
		}
		if ( $home_index < 0 || $away_index < 0 ) {
			return array();
		}

		$series = array();
		foreach ( $rows as $index => $row ) {
			$home = isset( $row['cells'][ $home_index ] ) ? self::get_cell_text( $row['cells'][ $home_index ] ) : '';
			$away = isset( $row['cells'][ $away_index ] ) ? self::get_cell_text( $row['cells'][ $away_index ] ) : '';
			if ( '' === $home || '' === $away ) {
				continue;
			}
			$pair = array( $home, $away );
			sort( $pair );
			$key = implode( '::', $pair );
			if ( ! isset( $series[ $key ] ) ) {
				$series[ $key ] = array(
					'title'   => $home . ' – ' . $away,
					'indexes' => array(),
				);
			}
			$series[ $key ]['indexes'][] = $index;
		}

		return array_values(
			array_filter(
				$series,
				static function ( $item ) {
					return count( $item['indexes'] ) > 1;
				}
			)
		);
	}

	/**
	 * Ranking table of a league group (uniho-ranking).
	 *
	 * @since 1.1.0
	 * @param int    $season     Season (start year).
	 * @param int    $league     League id.
	 * @param int    $game_class Game class id.
	 * @param string $group      Group name, e.g. "Gruppe 1".
	 * @param string $view       Optional. View, `full` by default.
	 * @return void
	 */
	public static function render_ranking( $season, $league, $game_class, $group = '', $view = '' ) {
		if ( ! $league || ! $game_class ) {
			self::render_missing( __( 'Attribute league und game_class fehlen.', 'swiss-floorball-api' ) );
			return;
		}

		$params   = array(
			'season'     => $season,
			'league'     => $league,
			'game_class' => $game_class,
			'group'      => $group,
			'view'       => '' !== $view ? $view : 'full',
		);
		$response = self::get_client()->fetch_data( 'rankings', self::clean_params( $params ) );
		if ( is_wp_error( $response ) || ! isset( $response['data']['regions'] ) ) {
			self::render_error( $response );
			return;
		}

		$data = $response['data'];
		$rows = self::get_rows( $data );
		?>
		<div class="sfa-widget" data-sfa-widget="ranking">
			<?php self::render_title( $data, true, __( 'Rangliste', 'swiss-floorball-api' ), 'chart' ); ?>
			<?php
			if ( $rows ) {
				self::render_table(
					isset( $data['headers'] ) ? $data['headers'] : array(),
					$rows,
					array( 'caption' => __( 'Rangliste', 'swiss-floorball-api' ) )
				);
			} else {
				echo '<p class="sfa-empty">' . esc_html__( 'Keine Daten gefunden.', 'swiss-floorball-api' ) . '</p>';
			}
			?>
		</div>
		<?php
	}

	/**
	 * Mobiliar topscorers as cards (uniho-mobiliar-topscorer), of one club or of the whole league.
	 *
	 * The response has four rows: portraits, names, clubs and points, one column per player.
	 *
	 * @since 1.1.0
	 * @param int $club_id Club id, 0 for the topscorers of the whole league.
	 * @param int $season  Season (start year).
	 * @return void
	 */
	public static function render_mobiliar_topscorers( $club_id, $season ) {
		// Without a club id the API returns the Mobiliar topscorers of the whole league.
		$response = self::get_client()->fetch_data(
			'topscorers/mobiliar-highlight',
			self::clean_params(
				array(
					'season'    => $season,
					'club_id'   => $club_id,
					'view_type' => 'table',
				)
			)
		);
		if ( is_wp_error( $response ) || ! isset( $response['data']['regions'] ) ) {
			self::render_error( $response );
			return;
		}

		$rows    = self::get_rows( $response['data'] );
		$players = array();
		$count   = 0;
		foreach ( array_slice( $rows, 0, 4 ) as $row ) {
			$count = max( $count, isset( $row['cells'] ) ? count( $row['cells'] ) : 0 );
		}
		for ( $i = 0; $i < $count; $i++ ) {
			$player = array(
				'image'  => isset( $rows[0]['cells'][ $i ]['image']['url'] ) ? $rows[0]['cells'][ $i ]['image']['url'] : '',
				'name'   => isset( $rows[1]['cells'][ $i ] ) ? self::get_cell_text( $rows[1]['cells'][ $i ] ) : '',
				'club'   => isset( $rows[2]['cells'][ $i ] ) ? self::get_cell_text( $rows[2]['cells'][ $i ] ) : '',
				'points' => isset( $rows[3]['cells'][ $i ] ) ? self::get_cell_text( $rows[3]['cells'][ $i ] ) : '',
			);
			if ( '' !== $player['image'] || '' !== $player['name'] || '' !== $player['club'] || '' !== $player['points'] ) {
				$players[] = $player;
			}
		}

		?>
		<div class="sfa-widget sfa-topscorers" data-sfa-widget="topscorers">
			<?php if ( empty( $players ) ) : ?>
				<p class="sfa-empty"><?php esc_html_e( 'Keine Mobiliar Topscorer gefunden.', 'swiss-floorball-api' ); ?></p>
			<?php else : ?>
				<div class="sfa-topscorers__headline"><?php esc_html_e( 'die Mobiliar Topscorer', 'swiss-floorball-api' ); ?></div>
				<div class="sfa-topscorers__list">
					<?php foreach ( $players as $player ) : ?>
						<article class="sfa-topscorers__item">
							<div class="sfa-topscorers__image">
								<?php if ( '' !== $player['image'] ) : ?>
									<img src="<?php echo esc_url( $player['image'] ); ?>" alt="<?php echo esc_attr( $player['name'] ); ?>" loading="lazy" />
								<?php endif; ?>
							</div>
							<div class="sfa-topscorers__info">
								<h3><?php echo esc_html( $player['name'] ); ?></h3>
								<?php if ( '' !== $player['club'] ) : ?>
									<p><?php echo esc_html( $player['club'] ); ?></p>
								<?php endif; ?>
							</div>
							<div class="sfa-topscorers__points">
								<strong><?php echo esc_html( '' !== $player['points'] ? $player['points'] : '-' ); ?></strong>
								<span><?php esc_html_e( 'Punkte', 'swiss-floorball-api' ); ?></span>
							</div>
						</article>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Format a Y-m-d date as dd.mm.yyyy.
	 *
	 * @since 1.1.0
	 * @param string $date Date as Y-m-d.
	 * @return string Formatted date.
	 */
	private static function format_date( $date ) {
		return gmdate( 'd.m.Y', strtotime( $date . ' UTC' ) );
	}
}
