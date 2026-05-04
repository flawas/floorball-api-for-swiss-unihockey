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
	 * Get the API client instance.
	 *
	 * @return Swiss_Floorball_API_Client
	 */
	private static function get_client() {
		if ( null === self::$client ) {
			self::$client = new Swiss_Floorball_API_Client();
		}
		return self::$client;
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

		$client = self::get_client();
		$api_response = $client->fetch_data( 'clubs/' . $swissfloorball_club_number . '/statistics' );

		if ( is_wp_error( $api_response ) || ! isset( $api_response['data']['regions'][0]['rows'] ) ) {
			echo '<p>' . esc_html__( 'Daten konnten nicht geladen werden.', 'floorball-api-for-swiss-unihockey' ) . '</p>';
			return;
		}

		$rows = $api_response['data']['regions'][0]['rows'];
		$team_count = count( $rows );
		$title = isset( $api_response['data']['title'] ) ? $api_response['data']['title'] : '';
		$current_season = get_option('swissfloorball_actual_season', date('Y'));

		?>
		<h2 class="sfa-section-title"><?php echo esc_html( $title ); ?></h2>
		<p><?php printf( esc_html__( 'Teams bei Swiss Floorball angemeldet: %d', 'floorball-api-for-swiss-unihockey' ), intval( $team_count ) ); ?></p>

		<table class="sfa-data-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Team ID', 'floorball-api-for-swiss-unihockey' ); ?></th>
					<th><?php esc_html_e( 'Team Name', 'floorball-api-for-swiss-unihockey' ); ?></th>
					<th><?php esc_html_e( 'Meisterschaft', 'floorball-api-for-swiss-unihockey' ); ?></th>
					<th><?php esc_html_e( 'League ID', 'floorball-api-for-swiss-unihockey' ); ?></th>
					<th><?php esc_html_e( 'Game Class ID', 'floorball-api-for-swiss-unihockey' ); ?></th>
					<th><?php esc_html_e( 'Group ID', 'floorball-api-for-swiss-unihockey' ); ?></th>
					<th><?php esc_html_e( 'Cup', 'floorball-api-for-swiss-unihockey' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $rows as $row ) : 
					$team_id = $row['team_id'];
					$league_id = '-';
					$game_class_id = '-';
					$group_id = '-';
					
					// Fetch team games to get league/game_class/group IDs
					$games_response = $client->fetch_data( 'games', array(
						'mode' => 'team',
						'team_id' => $team_id,
						'season' => $current_season,
					) );
					
					if ( ! is_wp_error( $games_response ) && isset( $games_response['data']['tabs'][0]['link']['ids'] ) ) {
						$ids = $games_response['data']['tabs'][0]['link']['ids'];
						// IDs format: [season, league, game_class, group]
						if ( count( $ids ) >= 4 ) {
							$league_id = $ids[1];
							$game_class_id = $ids[2];
							$group_id = $ids[3];
						}
					}
				?>
					<tr>
						<td data-label="<?php esc_attr_e( 'Team ID', 'floorball-api-for-swiss-unihockey' ); ?>"><?php echo esc_html( $team_id ); ?></td>
						<td data-label="<?php esc_attr_e( 'Team Name', 'floorball-api-for-swiss-unihockey' ); ?>"><?php echo esc_html( $row['cells'][0]['text'][0] ); ?></td>
						<td data-label="<?php esc_attr_e( 'Meisterschaft', 'floorball-api-for-swiss-unihockey' ); ?>"><?php echo esc_html( $row['cells'][1]['text'][0] ); ?></td>
						<td data-label="<?php esc_attr_e( 'League ID', 'floorball-api-for-swiss-unihockey' ); ?>"><?php echo esc_html( $league_id ); ?></td>
						<td data-label="<?php esc_attr_e( 'Game Class ID', 'floorball-api-for-swiss-unihockey' ); ?>"><?php echo esc_html( $game_class_id ); ?></td>
						<td data-label="<?php esc_attr_e( 'Group ID', 'floorball-api-for-swiss-unihockey' ); ?>"><?php echo esc_html( $group_id ); ?></td>
						<td data-label="<?php esc_attr_e( 'Cup', 'floorball-api-for-swiss-unihockey' ); ?>"><?php echo esc_html( $row['cells'][2]['text'][0] ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
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

		$client = self::get_client();
		$api_response = $client->fetch_data( 'clubs/' . $swissfloorball_club_number . '/statistics' );

		if ( is_wp_error( $api_response ) || ! isset( $api_response['data']['regions'][0]['rows'] ) ) {
			echo '<p>' . esc_html__( 'Daten konnten nicht geladen werden.', 'floorball-api-for-swiss-unihockey' ) . '</p>';
			return;
		}

		$rows = $api_response['data']['regions'][0]['rows'];
		$title = isset( $api_response['data']['title'] ) ? $api_response['data']['title'] : '';

		?>
		<h5><?php echo esc_html( $title ); ?></h5>
		<table class="sfa-data-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Team Name', 'floorball-api-for-swiss-unihockey' ); ?></th>
					<th><?php esc_html_e( 'Meisterschaft Platzierung', 'floorball-api-for-swiss-unihockey' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $rows as $row ) : ?>
					<tr>
						<td data-label="<?php esc_attr_e( 'Team Name', 'floorball-api-for-swiss-unihockey' ); ?>"><?php echo esc_html( $row['cells'][0]['text'][0] ); ?></td>
						<td data-label="<?php esc_attr_e( 'Meisterschaft Platzierung', 'floorball-api-for-swiss-unihockey' ); ?>"><?php echo esc_html( $row['cells'][1]['text'][0] ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Get club games.
	 *
	 * @param int|string $swissfloorball_club_number Club ID.
	 * @param int|string $season Season ID.
	 * @param bool       $is_backend Optional. Whether to render for backend. Default false.
	 * @return void
	 */
	public static function render_club_games( $swissfloorball_club_number, $season, $is_backend = false ) {
		$client = self::get_client();
		$api_response = $client->fetch_data( 'games', array(
			'mode'    => 'club',
			'club_id' => $swissfloorball_club_number,
			'season'  => $season,
		) );

		if ( is_wp_error( $api_response ) || ! isset( $api_response['data']['regions'][0]['rows'] ) ) {
			echo '<p>' . esc_html__( 'Daten konnten nicht geladen werden.', 'floorball-api-for-swiss-unihockey' ) . '</p>';
			return;
		}

		$rows = $api_response['data']['regions'][0]['rows'];
		$title = isset( $api_response['data']['title'] ) ? $api_response['data']['title'] : '';

		?>
		<h2 class="sfa-section-title"><?php echo esc_html( $title ); ?></h2>
		<table class="sfa-data-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Datum / Zeit', 'floorball-api-for-swiss-unihockey' ); ?></th>
					<th><?php esc_html_e( 'Liga / Gruppe', 'floorball-api-for-swiss-unihockey' ); ?></th>
					<th><?php esc_html_e( 'Heimteam', 'floorball-api-for-swiss-unihockey' ); ?></th>
					<th><?php esc_html_e( 'Gastteam', 'floorball-api-for-swiss-unihockey' ); ?></th>
					<th><?php esc_html_e( 'Ort', 'floorball-api-for-swiss-unihockey' ); ?></th>
					<th><?php esc_html_e( 'Resultat', 'floorball-api-for-swiss-unihockey' ); ?></th>
					<?php if ( $is_backend ) : ?>
						<th><?php esc_html_e( 'Aktionen', 'floorball-api-for-swiss-unihockey' ); ?></th>
					<?php endif; ?>
				</tr>
			</thead>
			<tbody>
				<?php
				foreach ( $rows as $row ) {
					$date           = $row['cells'][0]['text'][0];
					$time           = $row['cells'][0]['text'][1];
					$place_location = $row['cells'][1]['text'][0];
					$place_name     = $row['cells'][1]['text'][1];
					$league         = $row['cells'][2]['text'][0];
					$team_home      = $row['cells'][3]['text'][0];
					$team_away      = $row['cells'][4]['text'][0];
					$result         = $row['cells'][5]['text'][0];
					?>
					<tr>
						<td data-label="<?php esc_attr_e( 'Datum / Zeit', 'floorball-api-for-swiss-unihockey' ); ?>"><?php echo esc_html( $date . ' ' . $time ); ?></td>
						<td data-label="<?php esc_attr_e( 'Liga / Gruppe', 'floorball-api-for-swiss-unihockey' ); ?>"><?php echo esc_html( $league ); ?></td>
						<td data-label="<?php esc_attr_e( 'Heimteam', 'floorball-api-for-swiss-unihockey' ); ?>"><?php echo esc_html( $team_home ); ?></td>
						<td data-label="<?php esc_attr_e( 'Gastteam', 'floorball-api-for-swiss-unihockey' ); ?>"><?php echo esc_html( $team_away ); ?></td>
						<td data-label="<?php esc_attr_e( 'Ort', 'floorball-api-for-swiss-unihockey' ); ?>"><?php echo esc_html( $place_location . ' ' . $place_name ); ?></td>
						<td data-label="<?php esc_attr_e( 'Resultat', 'floorball-api-for-swiss-unihockey' ); ?>"><?php echo esc_html( $result ); ?></td>
						<?php if ( $is_backend ) : 
							$game_id = isset( $row['link']['ids'][0] ) ? $row['link']['ids'][0] : '';
							$details_url = add_query_arg( 'match_id', $game_id );
						?>
							<td>
								<?php if ( $game_id ) : ?>
									<a href="<?php echo esc_url( $details_url ); ?>" class="button button-small">
										<?php esc_html_e( 'Details', 'floorball-api-for-swiss-unihockey' ); ?>
									</a>
								<?php endif; ?>
							</td>
						<?php endif; ?>
					</tr>
					<?php
				}
				?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Get team games.
	 *
	 * @param int|string $swissfloorball_team_number Team ID.
	 * @param int|string $season Season ID.
	 * @param bool       $is_backend Optional. Whether to render for backend (includes Game ID). Default false.
	 * @return void
	 */
	public static function render_team_games( $swissfloorball_team_number, $season, $is_backend = false ) {
		$client = self::get_client();
		$api_response = $client->fetch_data( 'games', array(
			'mode'    => 'team',
			'team_id' => $swissfloorball_team_number,
			'season'  => $season,
		) );

		if ( is_wp_error( $api_response ) || ! isset( $api_response['data']['regions'][0]['rows'] ) ) {
			echo '<p>' . esc_html__( 'Daten konnten nicht geladen werden.', 'floorball-api-for-swiss-unihockey' ) . '</p>';
			return;
		}

		$rows = $api_response['data']['regions'][0]['rows'];
		$title = isset( $api_response['data']['title'] ) ? $api_response['data']['title'] : '';

		?>
		<h2 class="sfa-section-title"><?php echo esc_html( $title ); ?></h2>

		<table class="sfa-data-table">
			<thead>
				<tr>
					<?php if ( $is_backend ) : ?>
						<th><?php esc_html_e( 'Game ID', 'floorball-api-for-swiss-unihockey' ); ?></th>
					<?php endif; ?>
					<th><?php esc_html_e( 'Datum / Zeit', 'floorball-api-for-swiss-unihockey' ); ?></th>
					<th><?php esc_html_e( 'Heimteam', 'floorball-api-for-swiss-unihockey' ); ?></th>
					<th><?php esc_html_e( 'Gastteam', 'floorball-api-for-swiss-unihockey' ); ?></th>
					<th><?php esc_html_e( 'Ort', 'floorball-api-for-swiss-unihockey' ); ?></th>
					<th><?php esc_html_e( 'Resultat', 'floorball-api-for-swiss-unihockey' ); ?></th>
					<?php if ( $is_backend ) : ?>
						<th><?php esc_html_e( 'Aktionen', 'floorball-api-for-swiss-unihockey' ); ?></th>
					<?php endif; ?>
				</tr>
			</thead>
			<tbody>
				<?php
				foreach ( $rows as $row ) {
					$date           = $row['cells'][0]['text'][0];
					$time           = $row['cells'][0]['text'][1];
					$place_location = $row['cells'][1]['text'][0];
					$place_name     = $row['cells'][1]['text'][1];
					$team_home      = $row['cells'][2]['text'][0];
					$team_away      = $row['cells'][3]['text'][0];
					$result         = $row['cells'][4]['text'][0];
					$game_id        = isset( $row['link']['ids'][0] ) ? $row['link']['ids'][0] : '';
					?>
					<tr>
						<?php if ( $is_backend ) : ?>
							<td data-label="<?php esc_attr_e( 'Game ID', 'floorball-api-for-swiss-unihockey' ); ?>"><?php echo esc_html( $game_id ); ?></td>
						<?php endif; ?>
						<td data-label="<?php esc_attr_e( 'Datum / Zeit', 'floorball-api-for-swiss-unihockey' ); ?>"><?php echo esc_html( $date . ' ' . $time ); ?></td>
						<td data-label="<?php esc_attr_e( 'Heimteam', 'floorball-api-for-swiss-unihockey' ); ?>"><?php echo esc_html( $team_home ); ?></td>
						<td data-label="<?php esc_attr_e( 'Gastteam', 'floorball-api-for-swiss-unihockey' ); ?>"><?php echo esc_html( $team_away ); ?></td>
						<td data-label="<?php esc_attr_e( 'Ort', 'floorball-api-for-swiss-unihockey' ); ?>"><?php echo esc_html( $place_location . ', ' . $place_name ); ?></td>
						<td data-label="<?php esc_attr_e( 'Resultat', 'floorball-api-for-swiss-unihockey' ); ?>"><?php echo esc_html( $result ); ?></td>
						<?php if ( $is_backend ) : ?>
							<td>
								<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'floorball-api-for-swiss-unihockey-matches', 'match_id' => $game_id ), admin_url( 'admin.php' ) ) ); ?>" class="button button-small">
									<?php esc_html_e( 'Details', 'floorball-api-for-swiss-unihockey' ); ?>
								</a>
							</td>
						<?php endif; ?>
					</tr>
					<?php
				}
				?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Get leagues.
	 *
	 * @return void
	 */
	public static function render_leagues() {
		$client = self::get_client();
		$api_response = $client->fetch_data( 'leagues' );

		if ( is_wp_error( $api_response ) || ! isset( $api_response['entries'] ) ) {
			echo '<p>' . esc_html__( 'Daten konnten nicht geladen werden.', 'floorball-api-for-swiss-unihockey' ) . '</p>';
			return;
		}

		$entries = $api_response['entries'];
		$title = isset( $api_response['text'] ) ? $api_response['text'] : '';

		?>
		<h2 class="sfa-section-title"><?php echo esc_html( $title ); ?></h2>

		<table class="sfa-data-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Name', 'floorball-api-for-swiss-unihockey' ); ?></th>
					<th><?php esc_html_e( 'League Nummer', 'floorball-api-for-swiss-unihockey' ); ?></th>
					<th><?php esc_html_e( 'Game_class', 'floorball-api-for-swiss-unihockey' ); ?></th>
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
						<td data-label="<?php esc_attr_e( 'Name', 'floorball-api-for-swiss-unihockey' ); ?>"><?php echo esc_html( $name ); ?></td>
						<td data-label="<?php esc_attr_e( 'League Nummer', 'floorball-api-for-swiss-unihockey' ); ?>"><?php echo esc_html( $league ); ?></td>
						<td data-label="<?php esc_attr_e( 'Game_class', 'floorball-api-for-swiss-unihockey' ); ?>"><?php echo esc_html( $game_class ); ?></td>
					</tr>
					<?php
				}
				?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Get seasons.
	 *
	 * @return void
	 */
	public static function render_seasons() {
		$client = self::get_client();
		$api_response = $client->fetch_data( 'seasons' );

		if ( is_wp_error( $api_response ) || ! isset( $api_response['entries'] ) ) {
			echo '<p>' . esc_html__( 'Daten konnten nicht geladen werden.', 'floorball-api-for-swiss-unihockey' ) . '</p>';
			return;
		}

		$entries = $api_response['entries'];
		$title = isset( $api_response['text'] ) ? $api_response['text'] : '';

		?>
		<h2 class="sfa-section-title"><?php echo esc_html( $title ); ?></h2>

		<table class="sfa-data-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Club Name', 'floorball-api-for-swiss-unihockey' ); ?></th>
					<th><?php esc_html_e( 'Season_id', 'floorball-api-for-swiss-unihockey' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				foreach ( $entries as $entry ) {
					$name      = $entry['text'];
					$season_id = $entry['set_in_context']['season'];
					?>
					<tr>
						<td data-label="<?php esc_attr_e( 'Club Name', 'floorball-api-for-swiss-unihockey' ); ?>"><?php echo esc_html( $name ); ?></td>
						<td data-label="<?php esc_attr_e( 'Season_id', 'floorball-api-for-swiss-unihockey' ); ?>"><?php echo esc_html( $season_id ); ?></td>
					</tr>
					<?php
				}
				?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Get clubs.
	 *
	 * @return void
	 */
	public static function render_clubs() {
		$client = self::get_client();
		$api_response = $client->fetch_data( 'clubs' );

		if ( is_wp_error( $api_response ) || ! isset( $api_response['entries'] ) ) {
			echo '<p>' . esc_html__( 'Daten konnten nicht geladen werden.', 'floorball-api-for-swiss-unihockey' ) . '</p>';
			return;
		}

		$entries = $api_response['entries'];
		$title = isset( $api_response['text'] ) ? $api_response['text'] : '';

		?>
		<h2 class="sfa-section-title"><?php echo esc_html( $title ); ?></h2>

		<table class="sfa-data-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Club Name', 'floorball-api-for-swiss-unihockey' ); ?></th>
					<th><?php esc_html_e( 'Club_id', 'floorball-api-for-swiss-unihockey' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				foreach ( $entries as $entry ) {
					$name    = $entry['text'];
					$club_id = $entry['set_in_context']['club_id'];
					?>
					<tr>
						<td data-label="<?php esc_attr_e( 'Club Name', 'floorball-api-for-swiss-unihockey' ); ?>"><?php echo esc_html( $name ); ?></td>
						<td data-label="<?php esc_attr_e( 'Club_id', 'floorball-api-for-swiss-unihockey' ); ?>"><?php echo esc_html( $club_id ); ?></td>
					</tr>
					<?php
				}
				?>
			</tbody>
		</table>
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
		// 1. Construct WebCal Link URL (keep existing logic)
		$url = 'https://api-v2.swissunihockey.ch/api/calendars?';
		$params = array();

		// Determine mode for games API
		$games_params = array();
		$mode = '';

		if ( $team_id ) {
			$params['team_id'] = $team_id;
			$mode = 'team';
			$games_params['team_id'] = $team_id;
		} elseif ( $club_id ) {
			$params['club_id'] = $club_id;
			$mode = 'club';
			$games_params['club_id'] = $club_id;
		} elseif ( $season && $league && $game_class && $group ) {
			$params['season']     = $season;
			$params['league']     = $league;
			$params['game_class'] = $game_class;
			$params['group']      = $group;
			$mode = 'group'; // Assuming group mode exists or fallback to filtering? API docs for 'games' usually support team/club. Let's try to infer or use what we have.
            // If specific group params are passed, we might not be able to fetch "games" easily without a specific mode if the API doesn't support it directly for groups in the same way.
            // However, the user request specifically mentioned team_id example.
            // Let's assume for now we try to fetch games if we have a team or club.
            // If we only have group params, we might need a different endpoint or strategy, but let's focus on team/club first as per request.
		} else {
			echo '<p>' . esc_html__( 'Fehlende Parameter für Kalender.', 'floorball-api-for-swiss-unihockey' ) . '</p>';
			return;
		}

		$url = add_query_arg( $params, 'https://api-v2.swissunihockey.ch/api/calendars' );

        // 2. Fetch Games if possible
        if ( ! empty( $mode ) ) {
            $games_params['mode'] = $mode;
            // If season is not set in params but needed for games, we might need to default it.
            // The shortcode might not pass season. If not, we should probably use the current season option.
            if ( empty( $season ) ) {
                 $season = get_option('swissfloorball_actual_season');
            }
            $games_params['season'] = $season;

            // If we are in group mode (custom params), we might need to pass them to games endpoint if supported,
            // or we might skip games display if not supported.
            // For now, let's proceed with team/club which are the main use cases.
            
            $client = self::get_client();
            $api_response = $client->fetch_data( 'games', $games_params );

            if ( ! is_wp_error( $api_response ) && isset( $api_response['data']['regions'][0]['rows'] ) ) {
                $rows = $api_response['data']['regions'][0]['rows'];
                
                // Filter for upcoming games
                $upcoming_games = array();
                $now = current_time( 'timestamp' ); // Use WP time

                foreach ( $rows as $row ) {
                    $date_str = $row['cells'][0]['text'][0]; // e.g. "26.11.2025"
                    $time_str = $row['cells'][0]['text'][1]; // e.g. "20:00"
                    
                    // Parse date
                    $dt = DateTime::createFromFormat( 'd.m.Y H:i', $date_str . ' ' . $time_str );
                    if ( $dt && $dt->getTimestamp() >= $now ) {
                        $upcoming_games[] = $row;
                    }
                }

                // Render Table if we have upcoming games
                if ( ! empty( $upcoming_games ) ) {
                    ?>
                    <h3 class="sfa-calendar-title"><?php esc_html_e( 'Nächste Spiele', 'floorball-api-for-swiss-unihockey' ); ?></h3>
                    <table class="sfa-data-table sfa-calendar-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Datum', 'floorball-api-for-swiss-unihockey' ); ?></th>
                                <th><?php esc_html_e( 'Goal', 'floorball-api-for-swiss-unihockey' ); ?></th>
                                <th><?php esc_html_e( 'Resultat', 'floorball-api-for-swiss-unihockey' ); ?></th>
                        <?php if ( $is_backend ) : ?>
                            <th><?php esc_html_e( 'Aktionen', 'floorball-api-for-swiss-unihockey' ); ?></th>
                        <?php endif; ?>
                                <th><?php esc_html_e( 'Ort', 'floorball-api-for-swiss-unihockey' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $upcoming_games as $game ) : 
                                $date           = $game['cells'][0]['text'][0];
                                $time           = $game['cells'][0]['text'][1];
                                $place_location = $game['cells'][1]['text'][0];
                                $place_name     = $game['cells'][1]['text'][1]; // Sometimes location is split
                                
                                // Fetch game details for logos
                                $game_id = isset( $game['link']['ids'][0] ) ? $game['link']['ids'][0] : '';
                                $logo_home = '';
                                $logo_away = '';
                                $team_home = '';
                                $team_away = '';

                                if ( $game_id ) {
                                    $details = self::get_gamedetails( $game_id );
                                    if ( $details ) {
                                        $team_home = $details[2];
                                        $logo_home = $details[3];
                                        $team_away = $details[4];
                                        $logo_away = $details[5];
                                    }
                                }

                                // Fallback if details fail or no ID (though unlikely for valid games)
                                if ( empty( $team_home ) ) {
                                    if ( $mode === 'club' ) {
                                         $team_home = $game['cells'][3]['text'][0];
                                         $team_away = $game['cells'][4]['text'][0];
                                    } else {
                                         $team_home = $game['cells'][2]['text'][0];
                                         $team_away = $game['cells'][3]['text'][0];
                                    }
                                }

                                ?>
                                <tr>
                                    <td data-label="<?php esc_attr_e( 'Datum', 'floorball-api-for-swiss-unihockey' ); ?>"><?php echo esc_html( $date . ' ' . $time ); ?></td>
                                    <td data-label="<?php esc_attr_e( 'Heim', 'floorball-api-for-swiss-unihockey' ); ?>">
                                        <?php if ( $logo_home ) : ?>
                                            <img src="<?php echo esc_url( $logo_home ); ?>" alt="<?php echo esc_attr( $team_home ); ?>" class="sfa-logo-inline">
                                        <?php endif; ?>
                                        <?php echo esc_html( $team_home ); ?>
                                    </td>
                                    <td data-label="<?php esc_attr_e( 'Gast', 'floorball-api-for-swiss-unihockey' ); ?>">
                                        <?php if ( $logo_away ) : ?>
                                            <img src="<?php echo esc_url( $logo_away ); ?>" alt="<?php echo esc_attr( $team_away ); ?>" class="sfa-logo-inline">
                                        <?php endif; ?>
                                        <?php echo esc_html( $team_away ); ?>
                                    </td>
                                    <td data-label="<?php esc_attr_e( 'Ort', 'floorball-api-for-swiss-unihockey' ); ?>"><?php echo esc_html( $place_location ); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php
                } else {
                     echo '<p>' . esc_html__( 'Keine kommenden Spiele gefunden.', 'floorball-api-for-swiss-unihockey' ) . '</p>';
                }
            }
        }

		?>
		<div class="sfa-calendar-link">
            <a href="<?php echo esc_url( str_replace( 'https://', 'webcal://', $url ) ); ?>" target="_blank" class="sfa-calendar-subscribe">
                <?php esc_html_e( 'Kalender abonnieren', 'floorball-api-for-swiss-unihockey' ); ?>
            </a>
        </div>
		<?php
	}

	/**
	 * Get cups.
	 *
	 * @return void
	 */
	public static function render_cups() {
		$client = self::get_client();
		$api_response = $client->fetch_data( 'cups' );

		if ( is_wp_error( $api_response ) || ! isset( $api_response['data']['regions'][0]['rows'] ) ) {
			echo '<p>' . esc_html__( 'Daten konnten nicht geladen werden.', 'floorball-api-for-swiss-unihockey' ) . '</p>';
			return;
		}

		$rows = $api_response['data']['regions'][0]['rows'];
		$title = isset( $api_response['data']['title'] ) ? $api_response['data']['title'] : 'Cups';

		?>
		<h2 class="sfa-section-title"><?php echo esc_html( $title ); ?></h2>
		<table class="sfa-data-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Runde', 'floorball-api-for-swiss-unihockey' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $rows as $row ) : ?>
					<tr>
						<td data-label="<?php esc_attr_e( 'Runde', 'floorball-api-for-swiss-unihockey' ); ?>"><?php echo esc_html( $row['cells'][0]['text'][0] ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
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
		$client = self::get_client();
		$api_response = $client->fetch_data( 'groups', array(
			'season'     => $season,
			'league'     => $league,
			'game_class' => $game_class,
			'format'     => 'dropdown',
		) );

		if ( is_wp_error( $api_response ) || ! isset( $api_response['entries'] ) ) {
			echo '<p>' . esc_html__( 'Daten konnten nicht geladen werden.', 'floorball-api-for-swiss-unihockey' ) . '</p>';
			return;
		}

		$entries = $api_response['entries'];
		$title = isset( $api_response['text'] ) ? $api_response['text'] : 'Gruppen';

		?>
		<h2 class="sfa-section-title"><?php echo esc_html( $title ); ?></h2>
		<table class="sfa-data-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Gruppe', 'floorball-api-for-swiss-unihockey' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $entries as $entry ) : ?>
					<tr>
						<td data-label="<?php esc_attr_e( 'Gruppe', 'floorball-api-for-swiss-unihockey' ); ?>"><?php echo esc_html( $entry['text'] ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Get teams.
	 *
	 * @return void
	 */
	public static function render_teams() {
		$client = self::get_client();
		$api_response = $client->fetch_data( 'teams' );

		if ( is_wp_error( $api_response ) || ! isset( $api_response['data']['regions'][0]['rows'] ) ) {
			echo '<p>' . esc_html__( 'Daten konnten nicht geladen werden.', 'floorball-api-for-swiss-unihockey' ) . '</p>';
			return;
		}

		$rows = $api_response['data']['regions'][0]['rows'];
		$title = isset( $api_response['data']['title'] ) ? $api_response['data']['title'] : 'Teams';

		?>
		<h2 class="sfa-section-title"><?php echo esc_html( $title ); ?></h2>
		<table class="sfa-data-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Name', 'floorball-api-for-swiss-unihockey' ); ?></th>
					<th><?php esc_html_e( 'Website', 'floorball-api-for-swiss-unihockey' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				foreach ( $rows as $row ) {
					$name = $row['cells'][0]['text'][0];
					$website = isset( $row['cells'][2]['url']['href'] ) ? $row['cells'][2]['url']['href'] : '';
					?>
					<tr>
						<td data-label="<?php esc_attr_e( 'Name', 'floorball-api-for-swiss-unihockey' ); ?>"><?php echo esc_html( $name ); ?></td>
						<td data-label="<?php esc_attr_e( 'Website', 'floorball-api-for-swiss-unihockey' ); ?>"><a href="<?php echo esc_url( $website ); ?>" target="_blank"><?php echo esc_html( $website ); ?></a></td>
					</tr>
					<?php
				}
				?>
			</tbody>
		</table>
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
		$client = self::get_client();
		$api_response = $client->fetch_data( 'games', array(
			'mode'    => 'club',
			'club_id' => $swissfloorball_club_number,
			'season'  => $season,
		) );

		if ( is_wp_error( $api_response ) || ! isset( $api_response['data']['regions'][0]['rows'] ) ) {
			return;
		}

		$rows = $api_response['data']['regions'][0]['rows'];
		$count = count( $rows );

		$limit = max( 0, $count - 3 );

		for ( $i = 0; $i < $limit; $i++ ) {
			if ( ! isset( $rows[ $i ]['link']['ids'][0] ) ) {
				continue;
			}
			$game_id = $rows[ $i ]['link']['ids'][0];
			$game_details = self::get_gamedetails( $game_id );

			if ( ! $game_details ) {
				continue;
			}

			$result = explode( ':', $game_details[6] );
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
		$client = self::get_client();
		$api_response = $client->fetch_data( 'games', array(
			'mode'    => 'club',
			'club_id' => $swissfloorball_club_number,
			'season'  => $season,
		) );

		if ( is_wp_error( $api_response ) || ! isset( $api_response['data']['regions'][0]['rows'] ) ) {
			echo '<p>' . esc_html__( 'Daten konnten nicht geladen werden.', 'floorball-api-for-swiss-unihockey' ) . '</p>';
			return;
		}

		$rows = $api_response['data']['regions'][0]['rows'];
		$count = count( $rows );

		?>
		<div class="container">
			<div class="row">
				<?php
				$start = 2;
				$end = max( 0, $count - 2 );

				for ( $i = $start; $i < $end; $i++ ) {
					if ( ! isset( $rows[ $i ]['link']['ids'][0] ) ) {
						continue;
					}
					$game_id = $rows[ $i ]['link']['ids'][0];
					$game_details = self::get_gamedetails( $game_id );

					if ( ! $game_details ) {
						continue;
					}
					?>
					<div class="col-sm-6 md-4 mb-3">
						<div class="card">
							<div class="col">
								<div class="text-center">
									<h5><?php echo esc_html( $game_details[0] ); ?></h5>
									<table class="sfa-data-table">
										<tr>
											<th><h5></th>
											<td><img src="<?php echo esc_url( $game_details[3] ); ?>" alt="Vereinslogo" class="img-fluid rounded-start sfa-card-logo"></td>
											<td>
												<?php echo esc_html( $game_details[6] ); ?><br>
												<p><?php echo esc_html( $game_details[7] . ', ' . $game_details[8] ); ?></p>
											</td>
											<td><img src="<?php echo esc_url( $game_details[5] ); ?>" alt="Vereinslogo" class="img-fluid rounded-start sfa-card-logo"></td>
										</tr>
									</table>
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

	/**
	 * Get team details image.
	 *
	 * @param int|string $team_id Team ID.
	 * @return string
	 */
	public static function get_teamdetails_image( $team_id ) {
		$client = self::get_client();
		$api_response = $client->fetch_data( 'teams/' . $team_id );

		if ( is_wp_error( $api_response ) || ! isset( $api_response['data']['regions'][0]['rows'][0]['cells'][1]['image']['url'] ) ) {
			return '';
		}

		return $api_response['data']['regions'][0]['rows'][0]['cells'][1]['image']['url'];
	}

	/**
	 * Get game details.
	 *
	 * @param int|string $game_id Game ID.
	 * @return array|false
	 */
	public static function get_gamedetails( $game_id ) {
		$client = self::get_client();
		$api_response = $client->fetch_data( 'games/' . $game_id );

		if ( is_wp_error( $api_response ) || ! isset( $api_response['data']['regions'][0]['rows'][0]['cells'] ) ) {
			return false;
		}

		$cells = $api_response['data']['regions'][0]['rows'][0]['cells'];

		$gamedetails_team_home_image    = isset( $cells[0]['image']['url'] ) ? $cells[0]['image']['url'] : '';
		$gamedetails_team_home_teamname = isset( $cells[1]['text'][0] ) ? $cells[1]['text'][0] : '';
		$gamedetails_team_away_image    = isset( $cells[2]['image']['url'] ) ? $cells[2]['image']['url'] : '';
		$gamedetails_team_away_teamname = isset( $cells[3]['text'][0] ) ? $cells[3]['text'][0] : '';
		$gamedetails_result             = isset( $cells[4]['text'][0] ) ? $cells[4]['text'][0] : '';
		$gamedetails_playdate           = isset( $cells[5]['text'][0] ) ? $cells[5]['text'][0] : '';
		$gamedetails_playtime           = isset( $cells[6]['text'][0] ) ? $cells[6]['text'][0] : '';
		$gamedetails_venue              = isset( $cells[7]['text'][0] ) ? $cells[7]['text'][0] : '';

		$gamedetails_title    = isset( $api_response['data']['title'] ) ? $api_response['data']['title'] : '';
		$gamedetails_subtitle = isset( $api_response['data']['subtitle'] ) ? $api_response['data']['subtitle'] : '';

		return array(
			$gamedetails_title,
			$gamedetails_subtitle,
			$gamedetails_team_home_teamname,
			$gamedetails_team_home_image,
			$gamedetails_team_away_teamname,
			$gamedetails_team_away_image,
			$gamedetails_result,
			$gamedetails_playdate,
			$gamedetails_playtime,
			$gamedetails_venue,
		);
	}

	/**
	 * Render game details table.
	 *
	 * @param int|string $game_id Game ID.
	 * @return void
	 */
	public static function render_game_details_table( $game_id ) {
		$client = self::get_client();
		$api_response = $client->fetch_data( 'games/' . $game_id );

		if ( is_wp_error( $api_response ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Fehler beim Laden der Match-Details.', 'floorball-api-for-swiss-unihockey' ) . '</p></div>';
			return;
		}

		if ( isset( $api_response['data'] ) ) {
			echo '<div class="sfa-card">';
			
			// Title/Header
			$title = isset($api_response['data']['title']) ? $api_response['data']['title'] : 'Match Details';
			echo '<h3>' . esc_html($title) . '</h3>';
			
			if ( isset( $api_response['data']['regions'][0]['rows'] ) ) {
				foreach($api_response['data']['regions'] as $region) {
					if (!empty($region['title'])) {
					echo '<h4 class="sfa-region-title">' . esc_html($region['title']) . '</h4>';
					}
					if(isset($region['rows'])) {
						echo '<div class="sfa-table-container sfa-table-container-flat">';
						echo '<table class="sfa-data-table">';
						
						// Check for headers
						$headers = isset($api_response['data']['headers']) ? $api_response['data']['headers'] : null;
						if ( ! empty( $headers ) ) {
							echo '<thead><tr>';
							foreach ( $headers as $header ) {
								$header_text = isset($header['text']) ? $header['text'] : (is_string($header) ? $header : '');
								echo '<th>' . esc_html( $header_text ) . '</th>';
							}
							echo '</tr></thead>';
						}
						
						foreach($region['rows'] as $row) {
							echo '<tr>';
							if(isset($row['cells'])) {
								foreach($row['cells'] as $cell) {
									echo '<td>';
									// Check if cell contains an image
									if(isset($cell['image'])) {
										$img_url = isset($cell['image']['url']) ? $cell['image']['url'] : '';
										$img_alt = isset($cell['image']['alt']) ? $cell['image']['alt'] : '';
										if (!empty($img_url)) {
											echo '<img src="' . esc_url($img_url) . '" alt="' . esc_attr($img_alt) . '" class="sfa-cell-image">';
										}
									}
									// Display text content
									if(isset($cell['text'])) {
										foreach($cell['text'] as $text) {
											echo esc_html($text) . '<br>';
										}
									}
									echo '</td>';
								}
							}
							echo '</tr>';
						}
						echo '</table>';
						echo '</div>';
					}
				}
			} else {
				echo '<pre>' . esc_html( print_r( $api_response, true ) ) . '</pre>';
			}
			
			echo '</div>';
		} else {
			echo '<div class="sfa-empty-state"><p class="sfa-empty-state-text">' . esc_html__( 'Keine Details verfügbar.', 'floorball-api-for-swiss-unihockey' ) . '</p></div>';
		}
	}

	/**
	 * Get team ranking.
	 *
	 * @param int|string $season Season ID.
	 * @param int|string $league League ID.
	 * @param int|string $game_class Game Class ID.
	 * @param int|string $group Group ID.
	 * @return void
	 */
	public static function render_team_ranking( $season, $league, $game_class, $group ) {
		$client = self::get_client();
		$api_response = $client->fetch_data( 'rankings', array(
			'season'     => $season,
			'league'     => $league,
			'game_class' => $game_class,
			'group'      => $group,
		) );

		if ( is_wp_error( $api_response ) || ! isset( $api_response['data']['regions'][0]['rows'] ) ) {
			echo '<p>' . esc_html__( 'Daten konnten nicht geladen werden.', 'floorball-api-for-swiss-unihockey' ) . '</p>';
			return;
		}

		$rows = $api_response['data']['regions'][0]['rows'];
		$title = isset( $api_response['data']['title'] ) ? $api_response['data']['title'] : 'Rangliste';

		?>
		<h3><?php echo esc_html( $title ); ?></h3>
		<table class="sfa-data-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Rang', 'floorball-api-for-swiss-unihockey' ); ?></th>
					<th><?php esc_html_e( 'Team', 'floorball-api-for-swiss-unihockey' ); ?></th>
					<th><?php esc_html_e( 'Spiele', 'floorball-api-for-swiss-unihockey' ); ?></th>
					<th><?php esc_html_e( 'Tordifferenz', 'floorball-api-for-swiss-unihockey' ); ?></th>
					<th><?php esc_html_e( 'Punkte', 'floorball-api-for-swiss-unihockey' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				foreach ( $rows as $row ) {
					$rank       = isset( $row['cells'][0]['text'][0] ) ? $row['cells'][0]['text'][0] : '';
					$team       = isset( $row['cells'][2]['text'][0] ) ? $row['cells'][2]['text'][0] : '';
					$games      = isset( $row['cells'][3]['text'][0] ) ? $row['cells'][3]['text'][0] : '';
					$goal_diff  = isset( $row['cells'][10]['text'][0] ) ? $row['cells'][10]['text'][0] : '';
					$points     = isset( $row['cells'][12]['text'][0] ) ? $row['cells'][12]['text'][0] : '';
					?>
					<tr>
						<td data-label="<?php esc_attr_e( 'Rang', 'floorball-api-for-swiss-unihockey' ); ?>"><?php echo esc_html( $rank ); ?></td>
						<td data-label="<?php esc_attr_e( 'Team', 'floorball-api-for-swiss-unihockey' ); ?>"><?php echo esc_html( $team ); ?></td>
						<td data-label="<?php esc_attr_e( 'Spiele', 'floorball-api-for-swiss-unihockey' ); ?>"><?php echo esc_html( $games ); ?></td>
						<td data-label="<?php esc_attr_e( 'Tordifferenz', 'floorball-api-for-swiss-unihockey' ); ?>"><?php echo esc_html( $goal_diff ); ?></td>
						<td data-label="<?php esc_attr_e( 'Punkte', 'floorball-api-for-swiss-unihockey' ); ?>"><?php echo esc_html( $points ); ?></td>
					</tr>
					<?php
				}
				?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Get rankings (alias).
	 *
	 * @param int|string $season Season ID.
	 * @param int|string $league League ID.
	 * @param int|string $game_class Game Class ID.
	 * @param int|string $group Group ID.
	 * @return void
	 */
	public static function render_rankings( $season, $league, $game_class, $group ) {
		return self::render_team_ranking( $season, $league, $game_class, $group );
	}

	/**
	 * Get player details.
	 *
	 * @param int|string $player_id Player ID.
	 * @return void
	 */
	public static function render_player( $player_id ) {
		$client = self::get_client();
		$api_response = $client->fetch_data( 'players/' . $player_id );

		if ( is_wp_error( $api_response ) ) {
			echo '<p>' . esc_html__( 'Daten konnten nicht geladen werden.', 'floorball-api-for-swiss-unihockey' ) . '</p>';
			return;
		}

		// Basic implementation to dump player details.
		echo '<pre>';
		print_r( $api_response );
		echo '</pre>';
	}

	/**
	 * Get national players.
	 *
	 * @return void
	 */
	public static function render_national_players() {
		$client = self::get_client();
		$api_response = $client->fetch_data( 'national_players' );

		if ( is_wp_error( $api_response ) || ! isset( $api_response['data']['regions'][0]['rows'] ) ) {
			echo '<p>' . esc_html__( 'Daten konnten nicht geladen werden.', 'floorball-api-for-swiss-unihockey' ) . '</p>';
			return;
		}

		$rows = $api_response['data']['regions'][0]['rows'];
		$title = isset( $api_response['data']['title'] ) ? $api_response['data']['title'] : 'Nationalspieler';

		?>
		<h2 class="sfa-section-title"><?php echo esc_html( $title ); ?></h2>
		<table class="sfa-data-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Nr', 'floorball-api-for-swiss-unihockey' ); ?></th>
					<th><?php esc_html_e( 'Position', 'floorball-api-for-swiss-unihockey' ); ?></th>
					<th><?php esc_html_e( 'Name', 'floorball-api-for-swiss-unihockey' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				foreach ( $rows as $row ) {
					$nr   = isset( $row['cells'][0]['text'][0] ) ? $row['cells'][0]['text'][0] : '';
					$pos  = isset( $row['cells'][1]['text'][0] ) ? $row['cells'][1]['text'][0] : '';
					$name = isset( $row['cells'][2]['text'][0] ) ? $row['cells'][2]['text'][0] : '';
					?>
					<tr>
						<td data-label="<?php esc_attr_e( 'Nr', 'floorball-api-for-swiss-unihockey' ); ?>"><?php echo esc_html( $nr ); ?></td>
						<td data-label="<?php esc_attr_e( 'Position', 'floorball-api-for-swiss-unihockey' ); ?>"><?php echo esc_html( $pos ); ?></td>
						<td data-label="<?php esc_attr_e( 'Name', 'floorball-api-for-swiss-unihockey' ); ?>"><?php echo esc_html( $name ); ?></td>
					</tr>
					<?php
				}
				?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Get topscorers.
	 *
	 * @param int|string $season Season ID.
	 * @param int|string $league League ID.
	 * @param int|string $game_class Game Class ID.
	 * @param int|string $group Group ID.
	 * @return void
	 */
	public static function render_topscorers( $season, $league, $game_class, $group ) {
		$client = self::get_client();
		$api_response = $client->fetch_data( 'topscorers', array(
			'season'     => $season,
			'league'     => $league,
			'game_class' => $game_class,
			'group'      => $group,
		) );

		if ( is_wp_error( $api_response ) || ! isset( $api_response['data']['regions'][0]['rows'] ) ) {
			echo '<p>' . esc_html__( 'Daten konnten nicht geladen werden.', 'floorball-api-for-swiss-unihockey' ) . '</p>';
			return;
		}

		$rows = $api_response['data']['regions'][0]['rows'];
		$title = isset( $api_response['data']['title'] ) ? $api_response['data']['title'] : 'Topscorer';

		?>
		<h3><?php echo esc_html( $title ); ?></h3>
		<table class="sfa-data-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Rang', 'floorball-api-for-swiss-unihockey' ); ?></th>
					<th><?php esc_html_e( 'Spieler', 'floorball-api-for-swiss-unihockey' ); ?></th>
					<th><?php esc_html_e( 'Team', 'floorball-api-for-swiss-unihockey' ); ?></th>
					<th><?php esc_html_e( 'Tore', 'floorball-api-for-swiss-unihockey' ); ?></th>
					<th><?php esc_html_e( 'Assists', 'floorball-api-for-swiss-unihockey' ); ?></th>
					<th><?php esc_html_e( 'Punkte', 'floorball-api-for-swiss-unihockey' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				foreach ( $rows as $row ) {
					$rank    = isset( $row['cells'][0]['text'][0] ) ? $row['cells'][0]['text'][0] : '';
					$player  = isset( $row['cells'][1]['text'][0] ) ? $row['cells'][1]['text'][0] : '';
					$team    = isset( $row['cells'][2]['text'][0] ) ? $row['cells'][2]['text'][0] : '';
					$goals   = isset( $row['cells'][3]['text'][0] ) ? $row['cells'][3]['text'][0] : '';
					$assists = isset( $row['cells'][4]['text'][0] ) ? $row['cells'][4]['text'][0] : '';
					$points  = isset( $row['cells'][5]['text'][0] ) ? $row['cells'][5]['text'][0] : '';
					?>
					<tr>
						<td data-label="<?php esc_attr_e( 'Rang', 'floorball-api-for-swiss-unihockey' ); ?>"><?php echo esc_html( $rank ); ?></td>
						<td data-label="<?php esc_attr_e( 'Spieler', 'floorball-api-for-swiss-unihockey' ); ?>"><?php echo esc_html( $player ); ?></td>
						<td data-label="<?php esc_attr_e( 'Team', 'floorball-api-for-swiss-unihockey' ); ?>"><?php echo esc_html( $team ); ?></td>
						<td data-label="<?php esc_attr_e( 'Tore', 'floorball-api-for-swiss-unihockey' ); ?>"><?php echo esc_html( $goals ); ?></td>
						<td data-label="<?php esc_attr_e( 'Assists', 'floorball-api-for-swiss-unihockey' ); ?>"><?php echo esc_html( $assists ); ?></td>
						<td data-label="<?php esc_attr_e( 'Punkte', 'floorball-api-for-swiss-unihockey' ); ?>"><?php echo esc_html( $points ); ?></td>
					</tr>
					<?php
				}
				?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Get game events.
	 *
	 * @param int|string $game_id Game ID.
	 * @param bool       $is_backend Optional. Whether to render for backend. Default false.
	 * @return void
	 */
	public static function render_game_events( $game_id, $is_backend = false ) {
		$client = self::get_client();
		$api_response = $client->fetch_data( 'game_events/' . $game_id );

		if ( is_wp_error( $api_response ) || ! isset( $api_response['data']['regions'][0]['rows'] ) ) {
			echo '<p>' . esc_html__( 'Daten konnten nicht geladen werden.', 'floorball-api-for-swiss-unihockey' ) . '</p>';
			return;
		}

		$rows = $api_response['data']['regions'][0]['rows'];

		?>
		<h3><?php esc_html_e( 'Match-Telegramm', 'floorball-api-for-swiss-unihockey' ); ?></h3>
		<table class="sfa-data-table">
			        <thead>
            <tr>
                <th><?php esc_html_e( 'Zeit', 'floorball-api-for-swiss-unihockey' ); ?></th>
                <th><?php esc_html_e( 'Ereignis', 'floorball-api-for-swiss-unihockey' ); ?></th>
                <th><?php esc_html_e( 'Goal', 'floorball-api-for-swiss-unihockey' ); ?></th>
                <th><?php esc_html_e( 'Team', 'floorball-api-for-swiss-unihockey' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php
            foreach ( $rows as $row ) {
                $time   = isset( $row['cells'][0]['text'][0] ) ? $row['cells'][0]['text'][0] : '';
                $event  = isset( $row['cells'][1]['text'][0] ) ? $row['cells'][1]['text'][0] : '';
                $player = isset( $row['cells'][3]['text'][0] ) ? $row['cells'][3]['text'][0] : '';
                $team   = isset( $row['cells'][2]['text'][0] ) ? $row['cells'][2]['text'][0] : '';
                // Extract goal score from event string if present (e.g., "Torschütze 1:0")
                $goal = '';
                if ( preg_match( '/(\d+:\d+)/', $event, $matches ) ) {
                    $goal = $matches[1];
                }
                if ( ! empty( $player ) ) {
                    $event .= ' - ' . $player;
                }
                ?>
                <tr>
                    <td data-label="<?php esc_attr_e( 'Zeit', 'floorball-api-for-swiss-unihockey' ); ?>"><?php echo esc_html( $time ); ?></td>
                    <td data-label="<?php esc_attr_e( 'Ereignis', 'floorball-api-for-swiss-unihockey' ); ?>"><?php echo esc_html( $event ); ?></td>
                    <td data-label="<?php esc_attr_e( 'Goal', 'floorball-api-for-swiss-unihockey' ); ?>"><?php echo esc_html( $goal ); ?></td>
                    <td data-label="<?php esc_attr_e( 'Team', 'floorball-api-for-swiss-unihockey' ); ?>"><?php echo esc_html( $team ); ?></td>
                        <?php if ( isset( $is_backend ) && $is_backend ) : // Assuming $is_backend and $game_id are available in this scope, or need to be passed. ?>
                            <td>
                                <a href="<?php echo esc_url( add_query_arg( array( 'page' => 'floorball-api-for-swiss-unihockey-matches', 'match_id' => $game_id ), admin_url( 'admin.php' ) ) ); ?>" class="button button-small">
                                    <?php esc_html_e( 'Details', 'floorball-api-for-swiss-unihockey' ); ?>
                                </a>
                            </td>
                        <?php endif; ?>
                </tr>
            <?php }
            ?>
        </tbody>
		</table>
		<?php
	}

	/**
	 * Get sessions.
	 *
	 * @return void
	 */
	public static function render_sessions() {
		$client = self::get_client();
		$api_response = $client->fetch_data( 'sessions' );
		// Implementation depends on what this returns and if it's public.
	}
}
