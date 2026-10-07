<?php
/**
 * Fired during plugin activation
 *
 * @link       https://flaviowaser.ch
 * @since      1.0.0
 *
 * @package    Swiss_Floorball_Api
 * @subpackage Swiss_Floorball_Api/includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class to handle HTML display of API data.
 *
 * @since      1.0.0
 * @package    Swiss_Floorball_Api
 * @subpackage Swiss_Floorball_Api/includes
 * @author     Flavio Waser <kontakt@flawas.ch>
 */
class Swiss_Floorball_API_Display {

	/**
	 * API Client instance.
	 *
	 * @var Swiss_Floorball_API_Client
	 */
	private static $client;

	/**
	 * Render an error banner for failed or empty API responses.
	 *
	 * @since 1.0.0
	 *
	 * @param string $message Already translated message (escaped here, late).
	 * @return void
	 */
	private static function render_error_notice( $message ) {
		echo '<div class="sfa-info-box sfa-info-box--danger" role="alert"><p>' . Swiss_Floorball_Api_Icons::get( 'error' ) . ' ' . esc_html( $message ) . '</p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Icon markup sanitized via wp_kses() in Swiss_Floorball_Api_Icons::get().
	}

	/**
	 * Render the notice for a failed or empty API response.
	 *
	 * Explains when the chosen API source does not offer the requested endpoint, so
	 * administrators know to change the source instead of suspecting a broken plugin.
	 *
	 * @since 1.1.0
	 *
	 * @param array|WP_Error $api_response Response returned by the client.
	 * @return void
	 */
	public static function render_fetch_error( $api_response ) {
		if ( is_wp_error( $api_response ) && 'swfl_endpoint_unavailable' === $api_response->get_error_code() ) {
			self::render_error_notice( __( 'Diese Daten sind in der kostenlosen API nicht verfügbar. Sie sind nur über die Partner-API verfügbar, die in den Plugin-Einstellungen aktiviert werden kann.', 'swiss-floorball-api' ) );
			return;
		}
		if ( is_wp_error( $api_response ) && in_array( $api_response->get_error_code(), array( 'swfl_partner_credentials', 'swfl_partner_auth' ), true ) ) {
			self::render_error_notice( __( 'Anmeldung an der Partner-API fehlgeschlagen. Bitte API Key und Secret in den Plugin-Einstellungen prüfen.', 'swiss-floorball-api' ) );
			return;
		}
		$message = __( 'Daten konnten nicht geladen werden.', 'swiss-floorball-api' );
		// Visitors only see the generic message; the API's reason is for administrators.
		if ( is_wp_error( $api_response ) && current_user_can( 'manage_options' ) ) {
			$error_data = $api_response->get_error_data();
			if ( is_array( $error_data ) && ! empty( $error_data['api_message'] ) ) {
				/* translators: %s: error message returned by the Swiss Unihockey API. */
				$message .= ' ' . sprintf( __( 'Antwort der API: %s', 'swiss-floorball-api' ), $error_data['api_message'] );
			}
		}
		self::render_error_notice( $message );
	}

	/**
	 * Get the API client instance.
	 *
	 * @return Swiss_Floorball_API_Client
	 */
	public static function get_client() {
		if ( null === self::$client ) {
			self::$client = new Swiss_Floorball_API_Client();
		}
		return self::$client;
	}

	/**
	 * Safely read a text entry from a games grid row.
	 *
	 * Cancelled games come without a time, so cells may hold fewer text entries than usual.
	 *
	 * @since 1.0.0
	 *
	 * @param array $row   Grid row from the API.
	 * @param int   $cell  Cell index.
	 * @param int   $index Text index within the cell.
	 * @return string Raw text, or an empty string if missing.
	 */
	private static function get_cell_text( $row, $cell, $index ) {
		if ( isset( $row['cells'][ $cell ]['text'][ $index ] ) && is_scalar( $row['cells'][ $cell ]['text'][ $index ] ) ) {
			return (string) $row['cells'][ $cell ]['text'][ $index ];
		}
		return '';
	}

	/**
	 * Get the configured season, falling back to the current year.
	 *
	 * An unset or empty option is stored as 0, which get_option() defaults would not
	 * catch, so the fallback is applied here. The year uses the site timezone.
	 *
	 * @since 1.0.0
	 *
	 * @return int Season year.
	 */
	public static function get_current_season() {
		$season = absint( get_option( 'swissfloorball_actual_season', 0 ) );
		if ( 0 === $season ) {
			$season = absint( current_time( 'Y' ) );
		}
		return $season;
	}

	/**
	 * Retrieve all teams of the club (Admin)
	 *
	 * @param int|string $swissfloorball_club_number Club ID.
	 * @return void
	 */
	public static function render_club_teams( $swissfloorball_club_number ) {
		if ( empty( $swissfloorball_club_number ) ) {
			return;
		}

		$client       = self::get_client();
		$api_response = $client->fetch_data( 'clubs/' . $swissfloorball_club_number . '/statistics' );

		if ( is_wp_error( $api_response ) || ! isset( $api_response['data']['regions'][0]['rows'] ) ) {
			self::render_fetch_error( $api_response );
			return;
		}

		$rows           = $api_response['data']['regions'][0]['rows'];
		$team_count     = count( $rows );
		$title          = isset( $api_response['data']['title'] ) ? $api_response['data']['title'] : '';
		$current_season = self::get_current_season();

		?>
		<h2 class="sfa-section-title"><?php Swiss_Floorball_Api_Icons::render( 'group' ); ?> <?php echo esc_html( $title ); ?></h2>
		<?php /* translators: %d: number of teams registered with Swiss Floorball. */ ?>
		<p><?php printf( esc_html__( 'Teams bei Swiss Floorball angemeldet: %d', 'swiss-floorball-api' ), intval( $team_count ) ); ?></p>

		<div class="sfa-table-wrap"><table class="sfa-data-table">
			<caption class="sfa-visually-hidden"><?php esc_html_e( 'Teams des Clubs', 'swiss-floorball-api' ); ?></caption>
			<thead>
				<tr>
					<th scope="col" class="sfa-col-minor"><?php esc_html_e( 'Team ID', 'swiss-floorball-api' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Team Name', 'swiss-floorball-api' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Meisterschaft', 'swiss-floorball-api' ); ?></th>
					<th scope="col" class="sfa-col-minor"><?php esc_html_e( 'League ID', 'swiss-floorball-api' ); ?></th>
					<th scope="col" class="sfa-col-minor"><?php esc_html_e( 'Game Class ID', 'swiss-floorball-api' ); ?></th>
					<th scope="col" class="sfa-col-minor"><?php esc_html_e( 'Group ID', 'swiss-floorball-api' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Cup', 'swiss-floorball-api' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				foreach ( $rows as $row ) :
					$team_id       = $row['team_id'];
					$league_id     = '-';
					$game_class_id = '-';
					$group_id      = '-';

					// Fetch team games to get league/game_class/group IDs.
					$games_response = $client->fetch_data(
						'games',
						array(
							'mode'    => 'team',
							'team_id' => $team_id,
							'season'  => $current_season,
						)
					);

					if ( ! is_wp_error( $games_response ) && isset( $games_response['data']['tabs'][0]['link']['ids'] ) ) {
						$ids = $games_response['data']['tabs'][0]['link']['ids'];
						// IDs format: [season, league, game_class, group].
						if ( count( $ids ) >= 4 ) {
							$league_id     = $ids[1];
							$game_class_id = $ids[2];
							$group_id      = $ids[3];
						}
					}
					?>
					<tr>
						<td class="sfa-col-minor" data-label="<?php esc_attr_e( 'Team ID', 'swiss-floorball-api' ); ?>"><?php echo esc_html( $team_id ); ?></td>
						<td data-label="<?php esc_attr_e( 'Team Name', 'swiss-floorball-api' ); ?>"><?php echo esc_html( $row['cells'][0]['text'][0] ); ?></td>
						<td data-label="<?php esc_attr_e( 'Meisterschaft', 'swiss-floorball-api' ); ?>"><?php echo esc_html( $row['cells'][1]['text'][0] ); ?></td>
						<td class="sfa-col-minor" data-label="<?php esc_attr_e( 'League ID', 'swiss-floorball-api' ); ?>"><?php echo esc_html( $league_id ); ?></td>
						<td class="sfa-col-minor" data-label="<?php esc_attr_e( 'Game Class ID', 'swiss-floorball-api' ); ?>"><?php echo esc_html( $game_class_id ); ?></td>
						<td class="sfa-col-minor" data-label="<?php esc_attr_e( 'Group ID', 'swiss-floorball-api' ); ?>"><?php echo esc_html( $group_id ); ?></td>
						<td data-label="<?php esc_attr_e( 'Cup', 'swiss-floorball-api' ); ?>"><?php echo esc_html( $row['cells'][2]['text'][0] ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table></div>
		<?php
	}

	/**
	 * Retrieve all teams of the club (Public)
	 *
	 * @param int|string $swissfloorball_club_number Club ID.
	 * @return void
	 */
	public static function render_club_teams_pub( $swissfloorball_club_number ) {
		if ( empty( $swissfloorball_club_number ) ) {
			return;
		}

		$client       = self::get_client();
		$api_response = $client->fetch_data( 'clubs/' . $swissfloorball_club_number . '/statistics' );

		if ( is_wp_error( $api_response ) || ! isset( $api_response['data']['regions'][0]['rows'] ) ) {
			self::render_fetch_error( $api_response );
			return;
		}

		$rows  = $api_response['data']['regions'][0]['rows'];
		$title = isset( $api_response['data']['title'] ) ? $api_response['data']['title'] : '';

		?>
		<h3 class="sfa-section-title"><?php Swiss_Floorball_Api_Icons::render( 'group' ); ?> <?php echo esc_html( $title ); ?></h3>
		<div class="sfa-table-wrap"><table class="sfa-data-table">
			<caption class="sfa-visually-hidden"><?php esc_html_e( 'Platzierungen der Club-Teams', 'swiss-floorball-api' ); ?></caption>
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Team Name', 'swiss-floorball-api' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Meisterschaft Platzierung', 'swiss-floorball-api' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $rows as $row ) : ?>
					<tr>
						<td data-label="<?php esc_attr_e( 'Team Name', 'swiss-floorball-api' ); ?>"><?php echo esc_html( $row['cells'][0]['text'][0] ); ?></td>
						<td data-label="<?php esc_attr_e( 'Meisterschaft Platzierung', 'swiss-floorball-api' ); ?>"><?php echo esc_html( $row['cells'][1]['text'][0] ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table></div>
		<?php
	}

	/**
	 * Get club games.
	 *
	 * @param int|string $swissfloorball_club_number Club ID.
	 * @param int|string $season Season ID.
	 * @return void
	 */
	public static function render_club_games( $swissfloorball_club_number, $season ) {
		Swiss_Floorball_API_Widgets::render_admin_games( 'club', absint( $swissfloorball_club_number ), absint( $season ), __( 'Clubspiele', 'swiss-floorball-api' ), __( 'Spiele des Clubs', 'swiss-floorball-api' ) );
	}

	/**
	 * Get team games.
	 *
	 * @param int|string $swissfloorball_team_number Team ID.
	 * @param int|string $season Season ID.
	 * @return void
	 */
	public static function render_team_games( $swissfloorball_team_number, $season ) {
		Swiss_Floorball_API_Widgets::render_admin_games( 'team', absint( $swissfloorball_team_number ), absint( $season ), __( 'Teamspiele', 'swiss-floorball-api' ), __( 'Spiele des Teams', 'swiss-floorball-api' ) );
	}

	/**
	 * Get leagues.
	 *
	 * @return void
	 */
	public static function render_leagues() {
		$client       = self::get_client();
		$api_response = $client->fetch_data( 'leagues' );

		if ( is_wp_error( $api_response ) || ! isset( $api_response['entries'] ) ) {
			self::render_fetch_error( $api_response );
			return;
		}

		$entries = $api_response['entries'];
		$title   = isset( $api_response['text'] ) ? $api_response['text'] : '';

		?>
		<h2 class="sfa-section-title"><?php Swiss_Floorball_Api_Icons::render( 'trophy' ); ?> <?php echo esc_html( $title ); ?></h2>

		<div class="sfa-table-wrap"><table class="sfa-data-table">
			<caption class="sfa-visually-hidden"><?php esc_html_e( 'Ligen', 'swiss-floorball-api' ); ?></caption>
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Name', 'swiss-floorball-api' ); ?></th>
					<th scope="col"><?php esc_html_e( 'League Nummer', 'swiss-floorball-api' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Game_class', 'swiss-floorball-api' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				foreach ( $entries as $entry ) {
					$name       = $entry['text'];
					$league     = $entry['set_in_context']['league'];
					$game_class = $entry['set_in_context']['game_class'];
					?>
					<tr>
						<td data-label="<?php esc_attr_e( 'Name', 'swiss-floorball-api' ); ?>"><?php echo esc_html( $name ); ?></td>
						<td data-label="<?php esc_attr_e( 'League Nummer', 'swiss-floorball-api' ); ?>"><?php echo esc_html( $league ); ?></td>
						<td data-label="<?php esc_attr_e( 'Game_class', 'swiss-floorball-api' ); ?>"><?php echo esc_html( $game_class ); ?></td>
					</tr>
					<?php
				}
				?>
			</tbody>
		</table></div>
		<?php
	}

	/**
	 * Get seasons.
	 *
	 * @return void
	 */
	public static function render_seasons() {
		$client       = self::get_client();
		$api_response = $client->fetch_data( 'seasons' );

		if ( is_wp_error( $api_response ) || ! isset( $api_response['entries'] ) ) {
			self::render_fetch_error( $api_response );
			return;
		}

		$entries = $api_response['entries'];
		$title   = isset( $api_response['text'] ) ? $api_response['text'] : '';

		?>
		<h2 class="sfa-section-title"><?php Swiss_Floorball_Api_Icons::render( 'calendar' ); ?> <?php echo esc_html( $title ); ?></h2>

		<div class="sfa-table-wrap"><table class="sfa-data-table">
			<caption class="sfa-visually-hidden"><?php esc_html_e( 'Saisons', 'swiss-floorball-api' ); ?></caption>
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Club Name', 'swiss-floorball-api' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Season_id', 'swiss-floorball-api' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				foreach ( $entries as $entry ) {
					$name      = $entry['text'];
					$season_id = $entry['set_in_context']['season'];
					?>
					<tr>
						<td data-label="<?php esc_attr_e( 'Club Name', 'swiss-floorball-api' ); ?>"><?php echo esc_html( $name ); ?></td>
						<td data-label="<?php esc_attr_e( 'Season_id', 'swiss-floorball-api' ); ?>"><?php echo esc_html( $season_id ); ?></td>
					</tr>
					<?php
				}
				?>
			</tbody>
		</table></div>
		<?php
	}

	/**
	 * Get clubs.
	 *
	 * @return void
	 */
	public static function render_clubs() {
		$client       = self::get_client();
		$api_response = $client->fetch_data( 'clubs' );

		if ( is_wp_error( $api_response ) || ! isset( $api_response['entries'] ) ) {
			self::render_fetch_error( $api_response );
			return;
		}

		$entries = $api_response['entries'];
		$title   = isset( $api_response['text'] ) ? $api_response['text'] : '';

		?>
		<h2 class="sfa-section-title"><?php Swiss_Floorball_Api_Icons::render( 'group' ); ?> <?php echo esc_html( $title ); ?></h2>

		<div class="sfa-table-wrap"><table class="sfa-data-table">
			<caption class="sfa-visually-hidden"><?php esc_html_e( 'Clubs', 'swiss-floorball-api' ); ?></caption>
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Club Name', 'swiss-floorball-api' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Club_id', 'swiss-floorball-api' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				foreach ( $entries as $entry ) {
					$name    = $entry['text'];
					$club_id = $entry['set_in_context']['club_id'];
					?>
					<tr>
						<td data-label="<?php esc_attr_e( 'Club Name', 'swiss-floorball-api' ); ?>"><?php echo esc_html( $name ); ?></td>
						<td data-label="<?php esc_attr_e( 'Club_id', 'swiss-floorball-api' ); ?>"><?php echo esc_html( $club_id ); ?></td>
					</tr>
					<?php
				}
				?>
			</tbody>
		</table></div>
		<?php
	}

	/**
	 * Get calendars (Webcal link).
	 *
	 * @param int|null $team_id Team ID.
	 * @param int|null $club_id Club ID.
	 * @param int|null $season Season ID.
	 * @param int|null $league League ID.
	 * @param int|null $game_class Game Class ID.
	 * @param int|null $group Group ID.
	 * @return void
	 */
	public static function render_calendars( $team_id = null, $club_id = null, $season = null, $league = null, $game_class = null, $group = null ) {
		$request = self::get_calendar_request( $team_id, $club_id, $season, $league, $game_class, $group );
		if ( null === $request ) {
			echo '<p>' . esc_html__( 'Fehlende Parameter für Kalender.', 'swiss-floorball-api' ) . '</p>';
			return;
		}

		// The feed is generated by the plugin itself (REST route), as the former calendar export no longer exists.
		$url = add_query_arg( $request['params'], rest_url( 'swfl/v1/calendar' ) );

		$games_params           = $request['games_params'];
		$games_params['mode']   = $request['mode'];
		$games_params['season'] = empty( $season ) ? self::get_current_season() : $season;

		$api_response = self::get_client()->fetch_data( 'games', $games_params );
		if ( ! is_wp_error( $api_response ) && isset( $api_response['data']['regions'][0]['rows'] ) ) {
			self::render_upcoming_games( $api_response['data']['regions'][0]['rows'], $request['mode'] );
		}

		?>
		<div class="sfa-calendar-link">
			<a href="<?php echo esc_url( preg_replace( '#^https?://#', 'webcal://', $url ), array( 'webcal' ) ); ?>" target="_blank" rel="noopener noreferrer" class="sfa-calendar-subscribe">
				<?php Swiss_Floorball_Api_Icons::render( 'calendar' ); ?> <?php esc_html_e( 'Kalender abonnieren', 'swiss-floorball-api' ); ?>
			</a>
		</div>
		<?php
	}

	/**
	 * Work out the feed parameters and the games query of a calendar from the given identifiers.
	 *
	 * @since 2.0.1
	 * @param int|null $team_id    Team ID.
	 * @param int|null $club_id    Club ID.
	 * @param int|null $season     Season ID.
	 * @param int|null $league     League ID.
	 * @param int|null $game_class Game Class ID.
	 * @param int|null $group      Group ID.
	 * @return array|null Array with `params` (feed URL arguments), `games_params` and `mode`, or null without usable identifiers.
	 */
	private static function get_calendar_request( $team_id, $club_id, $season, $league, $game_class, $group ) {
		$request = null;
		if ( $team_id ) {
			$request = array(
				'params'       => array( 'team_id' => $team_id ),
				'games_params' => array( 'team_id' => $team_id ),
				'mode'         => 'team',
			);
		} elseif ( $club_id ) {
			$request = array(
				'params'       => array( 'club_id' => $club_id ),
				'games_params' => array( 'club_id' => $club_id ),
				'mode'         => 'club',
			);
		} elseif ( $season && $league && $game_class && $group ) {
			$request = array(
				'params'       => array(
					'season'     => $season,
					'league'     => $league,
					'game_class' => $game_class,
					'group'      => $group,
				),
				'games_params' => array(),
				'mode'         => 'group',
			);
		}

		return $request;
	}

	/**
	 * Keep the rows of games that have not started yet.
	 *
	 * Unparseable dates (e.g. cancelled games) are kept instead of silently dropped.
	 *
	 * @since 2.0.1
	 * @param array $rows Game rows.
	 * @return array Upcoming game rows.
	 */
	private static function filter_upcoming_games( $rows ) {
		$upcoming = array();
		$now      = time();

		foreach ( $rows as $row ) {
			$date_str = self::get_cell_text( $row, 0, 0 ); // E.g. "26.11.2025" or "Abgesagt".
			$time_str = self::get_cell_text( $row, 0, 1 ); // E.g. "20:00"; missing for cancelled games.

			if ( '' !== $time_str ) {
				$dt = DateTime::createFromFormat( 'd.m.Y H:i', $date_str . ' ' . $time_str );
			} else {
				// Without a time, compare against the end of the day.
				$dt = DateTime::createFromFormat( 'd.m.Y H:i:s', $date_str . ' 23:59:59' );
			}

			if ( ! $dt || $dt->getTimestamp() >= $now ) {
				$upcoming[] = $row;
			}
		}

		return $upcoming;
	}

	/**
	 * Render the table of upcoming games, or a notice when there are none.
	 *
	 * @since 2.0.1
	 * @param array  $rows Game rows.
	 * @param string $mode `team` or `club`, which decides the columns that hold the team names.
	 * @return void
	 */
	private static function render_upcoming_games( $rows, $mode ) {
		$upcoming_games = self::filter_upcoming_games( $rows );
		if ( empty( $upcoming_games ) ) {
			echo '<p>' . esc_html__( 'Keine kommenden Spiele gefunden.', 'swiss-floorball-api' ) . '</p>';
			return;
		}

		// Read team names from the games list to avoid one detail request per game.
		$home_column = 'club' === $mode ? 3 : 2;
		?>
		<h3 class="sfa-calendar-title"><?php Swiss_Floorball_Api_Icons::render( 'schedule' ); ?> <?php esc_html_e( 'Nächste Spiele', 'swiss-floorball-api' ); ?></h3>
		<div class="sfa-table-wrap"><table class="sfa-data-table sfa-calendar-table">
			<caption class="sfa-visually-hidden"><?php esc_html_e( 'Spielkalender', 'swiss-floorball-api' ); ?></caption>
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Datum', 'swiss-floorball-api' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Heim', 'swiss-floorball-api' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Gast', 'swiss-floorball-api' ); ?></th>
					<th scope="col" class="sfa-col-tiny"><?php esc_html_e( 'Ort', 'swiss-floorball-api' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $upcoming_games as $game ) : ?>
					<tr>
						<td data-label="<?php esc_attr_e( 'Datum', 'swiss-floorball-api' ); ?>"><?php echo esc_html( trim( self::get_cell_text( $game, 0, 0 ) . ' ' . self::get_cell_text( $game, 0, 1 ) ) ); ?></td>
						<td data-label="<?php esc_attr_e( 'Heim', 'swiss-floorball-api' ); ?>">
							<?php echo esc_html( self::get_cell_text( $game, $home_column, 0 ) ); ?>
						</td>
						<td data-label="<?php esc_attr_e( 'Gast', 'swiss-floorball-api' ); ?>">
							<?php echo esc_html( self::get_cell_text( $game, $home_column + 1, 0 ) ); ?>
						</td>
						<td class="sfa-col-tiny" data-label="<?php esc_attr_e( 'Ort', 'swiss-floorball-api' ); ?>"><?php echo esc_html( self::get_cell_text( $game, 1, 0 ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table></div>
		<?php
	}

	/**
	 * Get cups.
	 *
	 * @return void
	 */
	public static function render_cups() {
		$client       = self::get_client();
		$api_response = $client->fetch_data( 'cups' );

		if ( is_wp_error( $api_response ) || ! isset( $api_response['data']['regions'][0]['rows'] ) ) {
			self::render_fetch_error( $api_response );
			return;
		}

		$rows  = $api_response['data']['regions'][0]['rows'];
		$title = isset( $api_response['data']['title'] ) ? $api_response['data']['title'] : 'Cups';

		?>
		<h2 class="sfa-section-title"><?php Swiss_Floorball_Api_Icons::render( 'trophy' ); ?> <?php echo esc_html( $title ); ?></h2>
		<div class="sfa-table-wrap"><table class="sfa-data-table">
			<caption class="sfa-visually-hidden"><?php esc_html_e( 'Cups', 'swiss-floorball-api' ); ?></caption>
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Runde', 'swiss-floorball-api' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $rows as $row ) : ?>
					<tr>
						<td data-label="<?php esc_attr_e( 'Runde', 'swiss-floorball-api' ); ?>"><?php echo esc_html( $row['cells'][0]['text'][0] ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table></div>
		<?php
	}

	/**
	 * Get groups.
	 *
	 * @param int|string $season Season ID.
	 * @param int|string $league League ID.
	 * @param int|string $game_class Game Class ID.
	 * @return void
	 */
	public static function render_groups( $season, $league, $game_class ) {
		$client       = self::get_client();
		$api_response = $client->fetch_data(
			'groups',
			array(
				'season'     => $season,
				'league'     => $league,
				'game_class' => $game_class,
				'format'     => 'dropdown',
			)
		);

		if ( is_wp_error( $api_response ) || ! isset( $api_response['entries'] ) ) {
			self::render_fetch_error( $api_response );
			return;
		}

		$entries = $api_response['entries'];
		$title   = isset( $api_response['text'] ) ? $api_response['text'] : 'Gruppen';

		?>
		<h2 class="sfa-section-title"><?php Swiss_Floorball_Api_Icons::render( 'group' ); ?> <?php echo esc_html( $title ); ?></h2>
		<div class="sfa-table-wrap"><table class="sfa-data-table">
			<caption class="sfa-visually-hidden"><?php esc_html_e( 'Gruppen', 'swiss-floorball-api' ); ?></caption>
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Gruppe', 'swiss-floorball-api' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $entries as $entry ) : ?>
					<tr>
						<td data-label="<?php esc_attr_e( 'Gruppe', 'swiss-floorball-api' ); ?>"><?php echo esc_html( $entry['text'] ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table></div>
		<?php
	}

	/**
	 * Get teams.
	 *
	 * @return void
	 */
	public static function render_teams() {
		$client       = self::get_client();
		$api_response = $client->fetch_data( 'teams' );

		if ( is_wp_error( $api_response ) || ! isset( $api_response['data']['regions'][0]['rows'] ) ) {
			self::render_fetch_error( $api_response );
			return;
		}

		$rows  = $api_response['data']['regions'][0]['rows'];
		$title = isset( $api_response['data']['title'] ) ? $api_response['data']['title'] : 'Teams';

		?>
		<h2 class="sfa-section-title"><?php Swiss_Floorball_Api_Icons::render( 'group' ); ?> <?php echo esc_html( $title ); ?></h2>
		<div class="sfa-table-wrap"><table class="sfa-data-table">
			<caption class="sfa-visually-hidden"><?php esc_html_e( 'Teams', 'swiss-floorball-api' ); ?></caption>
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Name', 'swiss-floorball-api' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Website', 'swiss-floorball-api' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				foreach ( $rows as $row ) {
					$name    = $row['cells'][0]['text'][0];
					$website = isset( $row['cells'][2]['url']['href'] ) ? $row['cells'][2]['url']['href'] : '';
					?>
					<tr>
						<td data-label="<?php esc_attr_e( 'Name', 'swiss-floorball-api' ); ?>"><?php echo esc_html( $name ); ?></td>
						<td data-label="<?php esc_attr_e( 'Website', 'swiss-floorball-api' ); ?>"><a href="<?php echo esc_url( $website ); ?>" target="_blank"><?php echo esc_html( $website ); ?></a></td>
					</tr>
					<?php
				}
				?>
			</tbody>
		</table></div>
		<?php
	}

	/**
	 * Get club games callout.
	 *
	 * @param int|string $swissfloorball_club_number Club ID.
	 * @param int|string $season Season ID.
	 * @return void
	 */
	public static function render_club_games_callout( $swissfloorball_club_number, $season ) {
		$client       = self::get_client();
		$api_response = $client->fetch_data(
			'games',
			array(
				'mode'    => 'club',
				'club_id' => $swissfloorball_club_number,
				'season'  => $season,
			)
		);

		if ( is_wp_error( $api_response ) || ! isset( $api_response['data']['regions'][0]['rows'] ) ) {
			return;
		}

		$rows  = $api_response['data']['regions'][0]['rows'];
		$count = count( $rows );

		$limit = max( 0, $count - 3 );

		for ( $i = 0; $i < $limit; $i++ ) {
			if ( ! isset( $rows[ $i ]['link']['ids'][0] ) ) {
				continue;
			}
			$game_id      = $rows[ $i ]['link']['ids'][0];
			$game_details = Swiss_Floorball_API_Display_Stats::get_gamedetails( $game_id );

			if ( ! $game_details ) {
				continue;
			}

			$result     = explode( ':', $game_details[6] );
			$score_home = isset( $result[0] ) ? $result[0] : '-';
			$score_away = isset( $result[1] ) ? $result[1] : '-';

			?>
			<div class="card">
				<div class="row card-body">
					<div class="col">
						<img src="<?php echo esc_url( $game_details[3] ); ?>" alt="Vereinslogo" class="img-fluid rounded-start">
						<h5 class="card-title text-center"><?php echo esc_html( $score_home ); ?></h5>
					</div>
					<div class="col-6">
						<div class="card-body text-center">
							<p class="card-text"><?php echo esc_html( $game_details[1] ); ?></p>
							<p class="card-text"><?php echo esc_html( $game_details[9] ); ?></p>
							<p class="card-text"><?php echo esc_html( $game_details[7] . ', ' . $game_details[8] ); ?></p>
						</div>
					</div>
					<div class="col">
						<img src="<?php echo esc_url( $game_details[5] ); ?>" alt="Vereinslogo" class="img-fluid rounded-start">
						<h5 class="card-title text-center"><?php echo esc_html( $score_away ); ?></h5>
					</div>
				</div>
			</div>
			<?php
		}
	}

	/**
	 * Get club games cards.
	 *
	 * @param int|string $swissfloorball_club_number Club ID.
	 * @param int|string $season Season ID.
	 * @return void
	 */
	public static function render_club_games_cards( $swissfloorball_club_number, $season ) {
		$client       = self::get_client();
		$api_response = $client->fetch_data(
			'games',
			array(
				'mode'    => 'club',
				'club_id' => $swissfloorball_club_number,
				'season'  => $season,
			)
		);

		if ( is_wp_error( $api_response ) || ! isset( $api_response['data']['regions'][0]['rows'] ) ) {
			self::render_fetch_error( $api_response );
			return;
		}

		$rows  = $api_response['data']['regions'][0]['rows'];
		$count = count( $rows );

		?>
		<div class="container">
			<div class="row">
				<?php
				$start = 2;
				$end   = max( 0, $count - 2 );

				for ( $i = $start; $i < $end; $i++ ) {
					if ( ! isset( $rows[ $i ]['link']['ids'][0] ) ) {
						continue;
					}
					$game_id      = $rows[ $i ]['link']['ids'][0];
					$game_details = Swiss_Floorball_API_Display_Stats::get_gamedetails( $game_id );

					if ( ! $game_details ) {
						continue;
					}
					?>
					<div class="col-sm-6 md-4 mb-3">
						<div class="card">
							<div class="col">
								<div class="text-center">
									<h5><?php echo esc_html( $game_details[0] ); ?></h5>
									<div class="sfa-table-wrap"><table class="sfa-data-table">
										<caption class="sfa-visually-hidden"><?php esc_html_e( 'Spielpaarung', 'swiss-floorball-api' ); ?></caption>
										<tr>
											<th scope="row"></th>
											<td><img src="<?php echo esc_url( $game_details[3] ); ?>" alt="Vereinslogo" class="img-fluid rounded-start sfa-card-logo"></td>
											<td>
												<?php echo esc_html( $game_details[6] ); ?><br>
												<p><?php echo esc_html( $game_details[7] . ', ' . $game_details[8] ); ?></p>
											</td>
											<td><img src="<?php echo esc_url( $game_details[5] ); ?>" alt="Vereinslogo" class="img-fluid rounded-start sfa-card-logo"></td>
										</tr>
									</table></div>
									<p><?php echo esc_html( $game_details[1] ); ?></p>
								</div>
							</div>
						</div>
					</div>
					<?php
				}
				?>
			</div>
		</div>
		<?php
	}
}
