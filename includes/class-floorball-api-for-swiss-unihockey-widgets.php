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
	 * Namespace of the plugin REST routes.
	 *
	 * @since 2.0.0
	 * @var   string
	 */
	const REST_NAMESPACE = 'swfl/v1';


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
	public static function get_client() {
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
			self::REST_NAMESPACE,
			'/team-games',
			array(
				'methods'             => 'GET',
				'callback'            => array( 'Swiss_Floorball_API_Widgets', 'rest_team_games' ),
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
			self::REST_NAMESPACE,
			'/league-games',
			array(
				'methods'             => 'GET',
				'callback'            => array( 'Swiss_Floorball_API_Widgets', 'rest_league_games' ),
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
			self::REST_NAMESPACE,
			'/calendar',
			array(
				'methods'             => 'GET',
				'callback'            => array( 'Swiss_Floorball_API_Calendar', 'rest_calendar' ),
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
		add_filter( 'rest_pre_serve_request', array( 'Swiss_Floorball_API_Calendar', 'serve_calendar' ), 10, 3 );
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
		$col_count = is_array( $headers ) ? count( $headers ) : 0;
		if ( $args['drop_last'] && $col_count > 0 ) {
			--$col_count;
		}
		$labels = array();
		for ( $i = 0; $i < $col_count; $i++ ) {
			$labels[ $i ] = isset( $headers[ $i ]['text'] ) ? (string) $headers[ $i ]['text'] : '';
		}
		$columns = array(
			'headers' => $headers,
			'labels'  => $labels,
			'minor'   => Swiss_Floorball_API_Table_Data::get_minor_columns( $labels ),
			'count'   => $col_count,
		);
		?>
		<div class="sfa-table-wrap">
			<table class="sfa-data-table">
				<?php if ( '' !== $args['caption'] ) : ?>
					<caption class="sfa-visually-hidden"><?php echo esc_html( $args['caption'] ); ?></caption>
				<?php endif; ?>
				<?php
				if ( $col_count > 0 ) {
					self::render_table_head( $columns, $args['admin_links'] );
				}
				?>
				<tbody>
					<?php
					foreach ( $rows as $index => $row ) {
						self::render_table_row( $row, $index, $columns, $args );
					}
					?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Render the table header row.
	 *
	 * @since 2.0.1
	 * @param array $columns     Column data: headers, labels, minor (hidden-when-narrow classes) and count.
	 * @param bool  $admin_links Whether a trailing "Aktionen" column is added.
	 * @return void
	 */
	private static function render_table_head( $columns, $admin_links ) {
		?>
		<thead>
			<tr>
				<?php foreach ( $columns['labels'] as $i => $label ) : ?>
					<?php
					$th_class = array();
					if ( isset( $columns['headers'][ $i ]['align'] ) && 'r' === $columns['headers'][ $i ]['align'] ) {
						$th_class[] = 'sfa-align-right';
					}
					if ( isset( $columns['minor'][ $i ] ) ) {
						$th_class[] = $columns['minor'][ $i ];
					}
					?>
					<th scope="col"<?php echo $th_class ? ' class="' . esc_attr( implode( ' ', $th_class ) ) . '"' : ''; ?>><?php echo esc_html( $label ); ?></th>
				<?php endforeach; ?>
				<?php if ( $admin_links ) : ?>
					<th scope="col"><?php esc_html_e( 'Aktionen', 'swiss-floorball-api' ); ?></th>
				<?php endif; ?>
			</tr>
		</thead>
		<?php
	}

	/**
	 * Render one table row with its cells.
	 *
	 * @since 2.0.1
	 * @param array      $row     Row.
	 * @param int|string $index   Row index, used for extra attributes and the hidden state.
	 * @param array      $columns Column data, see render_table_head().
	 * @param array      $args    Table arguments of render_table().
	 * @return void
	 */
	private static function render_table_row( $row, $index, $columns, $args ) {
		$game_id = Swiss_Floorball_API_Table_Data::get_game_id( $row );
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
				if ( $i >= $columns['count'] && $columns['count'] > 0 ) {
					break;
				}
				$align = isset( $columns['headers'][ $i ]['align'] ) ? $columns['headers'][ $i ]['align'] : '';
				self::render_cell( $cell, isset( $columns['labels'][ $i ] ) ? $columns['labels'][ $i ] : '', $game_id, $align, isset( $columns['minor'][ $i ] ) ? $columns['minor'][ $i ] : '' );
			}
			if ( $args['admin_links'] ) {
				self::render_admin_link_cell( $game_id );
			}
			?>
		</tr>
		<?php
	}

	/**
	 * Render the trailing cell that links a game to its admin detail page.
	 *
	 * @since 2.0.1
	 * @param int $game_id Game id, 0 when the row has none.
	 * @return void
	 */
	private static function render_admin_link_cell( $game_id ) {
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
		$text  = Swiss_Floorball_API_Table_Data::get_cell_text( $cell );
		$image = '';
		if ( isset( $cell['image']['url'] ) ) {
			$image = $cell['image']['url'];
		} elseif ( isset( $cell['image'] ) && is_string( $cell['image'] ) ) {
			$image = $cell['image'];
		}
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

		if ( $game_id > 0 && '' === $image && Swiss_Floorball_API_Table_Data::is_game_link_cell( $text ) ) {
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
		$result = Swiss_Floorball_API_Table_Data::fetch_all_pages(
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

		$rows = Swiss_Floorball_API_Table_Data::prepare_game_rows( $result['rows'] );
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
	 * Monday and Sunday (Y-m-d) of the week that contains a timestamp.
	 *
	 * @since 2.0.1
	 * @param int $timestamp UTC timestamp.
	 * @return string[] Week start and week end.
	 */
	private static function get_week_bounds( $timestamp ) {
		$start = gmdate( 'Y-m-d', $timestamp - ( (int) gmdate( 'N', $timestamp ) - 1 ) * DAY_IN_SECONDS );
		$end   = gmdate( 'Y-m-d', strtotime( $start . ' UTC' ) + 6 * DAY_IN_SECONDS );

		return array( $start, $end );
	}

	/**
	 * Keep the given week when it has games, otherwise use the week of the next game, or of the last one.
	 *
	 * @since 2.0.1
	 * @param array  $rows       Game rows with an `sfa_date`.
	 * @param string $week_start Start of the current week (Y-m-d).
	 * @param string $week_end   End of the current week (Y-m-d).
	 * @return string[] Week start and week end to show.
	 */
	private static function find_visible_week( $rows, $week_start, $week_end ) {
		$next = '';
		$last = '';
		foreach ( $rows as $row ) {
			$date = $row['sfa_date'];
			if ( '' === $date ) {
				continue;
			}
			if ( $date >= $week_start && $date <= $week_end ) {
				return array( $week_start, $week_end );
			}
			if ( $date > $week_end && ( '' === $next || $date < $next ) ) {
				$next = $date;
			}
			if ( $date < $week_start && $date > $last ) {
				$last = $date;
			}
		}
		$anchor = '' !== $next ? $next : $last;

		return '' !== $anchor ? self::get_week_bounds( strtotime( $anchor . ' UTC' ) ) : array( $week_start, $week_end );
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

		$result = Swiss_Floorball_API_Table_Data::fetch_all_pages(
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

		$rows                          = Swiss_Floorball_API_Table_Data::prepare_game_rows( $result['rows'] );
		list( $week_start, $week_end ) = self::get_week_bounds( strtotime( current_time( 'Y-m-d' ) . ' UTC' ) );

		// Outside the playing weeks the current week is empty; jump to the next game, or the last one.
		list( $week_start, $week_end ) = self::find_visible_week( $rows, $week_start, $week_end );

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

		$result = Swiss_Floorball_API_Table_Data::fetch_all_pages(
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

		$rows  = Swiss_Floorball_API_Table_Data::prepare_game_rows( $result['rows'] );
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
		// Own counter instead of wp_unique_id(), which needs WordPress 5.0.3 while the plugin supports 5.0.
		static $select_count = 0;
		++$select_count;
		$select_id = 'sfa-team-select-' . $select_count;

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

		$response = self::get_client()->fetch_data( 'games', Swiss_Floorball_API_Table_Data::clean_params( $params ) );
		if ( is_wp_error( $response ) || ! isset( $response['data']['regions'] ) ) {
			self::render_error( $response );
			return;
		}

		$data    = $response['data'];
		$headers = isset( $data['headers'] ) && is_array( $data['headers'] ) ? $data['headers'] : array();
		$rows    = Swiss_Floorball_API_Table_Data::prepare_game_rows( Swiss_Floorball_API_Table_Data::get_rows( $data ) );
		$last    = $headers ? end( $headers ) : array();
		$drop    = isset( $last['text'] ) && '📺' === $last['text'];
		$prev    = Swiss_Floorball_API_Table_Data::get_slider_context( $data, 'prev' );
		$next    = Swiss_Floorball_API_Table_Data::get_slider_context( $data, 'next' );

		?>
		<div class="sfa-widget" data-sfa-widget="league" data-sfa-params="<?php echo esc_attr( wp_json_encode( $base_params ) ); ?>">
			<div class="sfa-widget__controls">
				<button type="button" class="sfa-btn" data-sfa-action="prev" data-sfa-context="<?php echo esc_attr( wp_json_encode( $prev ) ); ?>" <?php disabled( empty( $prev ) ); ?>><?php Swiss_Floorball_Api_Icons::render( 'chevron_left' ); ?> <?php esc_html_e( 'Frühere Spiele', 'swiss-floorball-api' ); ?></button>
				<span class="sfa-widget__label"><?php echo esc_html( ! empty( $data['slider']['text'] ) ? $data['slider']['text'] : __( 'Aktuelle Runde', 'swiss-floorball-api' ) ); ?></span>
				<button type="button" class="sfa-btn" data-sfa-action="next" data-sfa-context="<?php echo esc_attr( wp_json_encode( $next ) ); ?>" <?php disabled( empty( $next ) ); ?>><?php esc_html_e( 'Weitere Spiele', 'swiss-floorball-api' ); ?> <?php Swiss_Floorball_Api_Icons::render( 'chevron_right' ); ?></button>
			</div>
			<?php
			self::render_title( $data, true, '', 'hockey' );
			self::render_league_tables( $headers, $rows, $drop );
			?>
		</div>
		<?php
	}

	/**
	 * Render the playoff series as collapsible groups, followed by the games that belong to no series.
	 *
	 * @since 2.0.1
	 * @param array $headers Table headers.
	 * @param array $rows    Prepared rows.
	 * @param bool  $drop    Whether to drop the last (streaming) column.
	 * @return void
	 */
	private static function render_league_tables( $headers, $rows, $drop ) {
		$groups  = self::group_playoff_series( $headers, $rows );
		$grouped = array();
		foreach ( $groups as $series ) {
			foreach ( $series['indexes'] as $index ) {
				$grouped[ $index ] = true;
			}
		}

		foreach ( $groups as $position => $series ) {
			self::render_playoff_group( $series, 0 === $position, $headers, $rows, $drop );
		}

		$single = array_values( array_diff_key( $rows, $grouped ) );
		if ( $single ) {
			self::render_table( $headers, $single, array( 'drop_last' => $drop ) );
		} elseif ( empty( $groups ) ) {
			echo '<p class="sfa-empty">' . esc_html__( 'Keine Spiele gefunden.', 'swiss-floorball-api' ) . '</p>';
		}
	}

	/**
	 * Render one playoff series as a collapsible group.
	 *
	 * @since 2.0.1
	 * @param array $series  Series with `title` and the row `indexes`.
	 * @param bool  $is_open Whether the group starts expanded.
	 * @param array $headers Table headers.
	 * @param array $rows    Prepared rows.
	 * @param bool  $drop    Whether to drop the last (streaming) column.
	 * @return void
	 */
	private static function render_playoff_group( $series, $is_open, $headers, $rows, $drop ) {
		$series_rows = array();
		foreach ( $series['indexes'] as $index ) {
			$series_rows[] = $rows[ $index ];
		}
		?>
		<details class="sfa-playoff-group"<?php echo $is_open ? ' open' : ''; ?>>
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

	/**
	 * Index of the first header whose label contains a text, -1 when there is none.
	 *
	 * @since 2.0.1
	 * @param array  $headers Table headers.
	 * @param string $needle  Lower-case text to look for.
	 * @return int Header index, or -1.
	 */
	private static function find_header_index( $headers, $needle ) {
		foreach ( $headers as $index => $header ) {
			$label = isset( $header['text'] ) ? strtolower( (string) $header['text'] ) : '';
			if ( false !== strpos( $label, $needle ) ) {
				return $index;
			}
		}

		return -1;
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
		$home_index = self::find_header_index( $headers, 'heim' );
		$away_index = self::find_header_index( $headers, 'gast' );
		if ( $home_index < 0 || $away_index < 0 ) {
			return array();
		}

		$series = array();
		foreach ( $rows as $index => $row ) {
			$home = isset( $row['cells'][ $home_index ] ) ? Swiss_Floorball_API_Table_Data::get_cell_text( $row['cells'][ $home_index ] ) : '';
			$away = isset( $row['cells'][ $away_index ] ) ? Swiss_Floorball_API_Table_Data::get_cell_text( $row['cells'][ $away_index ] ) : '';
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
		$response = self::get_client()->fetch_data( 'rankings', Swiss_Floorball_API_Table_Data::clean_params( $params ) );
		if ( is_wp_error( $response ) || ! isset( $response['data']['regions'] ) ) {
			self::render_error( $response );
			return;
		}

		$data = $response['data'];
		$rows = Swiss_Floorball_API_Table_Data::get_rows( $data );
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
			Swiss_Floorball_API_Table_Data::clean_params(
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

		$rows    = Swiss_Floorball_API_Table_Data::get_rows( $response['data'] );
		$players = array();
		$count   = 0;
		foreach ( array_slice( $rows, 0, 4 ) as $row ) {
			$count = max( $count, isset( $row['cells'] ) ? count( $row['cells'] ) : 0 );
		}
		for ( $i = 0; $i < $count; $i++ ) {
			$player = array(
				'image'  => isset( $rows[0]['cells'][ $i ]['image']['url'] ) ? $rows[0]['cells'][ $i ]['image']['url'] : '',
				'name'   => isset( $rows[1]['cells'][ $i ] ) ? Swiss_Floorball_API_Table_Data::get_cell_text( $rows[1]['cells'][ $i ] ) : '',
				'club'   => isset( $rows[2]['cells'][ $i ] ) ? Swiss_Floorball_API_Table_Data::get_cell_text( $rows[2]['cells'][ $i ] ) : '',
				'points' => isset( $rows[3]['cells'][ $i ] ) ? Swiss_Floorball_API_Table_Data::get_cell_text( $rows[3]['cells'][ $i ] ) : '',
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
