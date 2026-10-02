<?php
/**
 * HTML output for games, rankings, players and topscorers.
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
 * Renders game details, rankings, player data and topscorers from the Swiss Unihockey API.
 *
 * Split out of Swiss_Floorball_API_Display, which keeps the club, league and lookup lists.
 *
 * @since      2.0.1
 * @package    Swiss_Floorball_Api
 * @subpackage Swiss_Floorball_Api/includes
 * @author     Flavio Waser <kontakt@flawas.ch>
 */
class Swiss_Floorball_API_Display_Stats {

	/**
	 * Get team details image.
	 *
	 * @param int|string $team_id Team ID.
	 * @return string
	 */
	public static function get_teamdetails_image( $team_id ) {
		$client       = Swiss_Floorball_API_Display::get_client();
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
		$client       = Swiss_Floorball_API_Display::get_client();
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
		$client       = Swiss_Floorball_API_Display::get_client();
		$api_response = $client->fetch_data( 'games/' . $game_id );

		if ( is_wp_error( $api_response ) ) {
			Swiss_Floorball_API_Display::render_fetch_error( $api_response );
		} elseif ( isset( $api_response['data'] ) ) {
			self::render_details_card( $api_response );
		} else {
			echo '<div class="sfa-empty-state"><p class="sfa-empty-state-text">' . esc_html__( 'Keine Details verfügbar.', 'swiss-floorball-api' ) . '</p></div>';
		}
	}

	/**
	 * Render the card of a game: title, subtitle and one table per region.
	 *
	 * @since 2.0.1
	 * @param array $api_response Decoded game response with a `data` key.
	 * @return void
	 */
	private static function render_details_card( $api_response ) {
		$data = $api_response['data'];
		echo '<div class="sfa-card">';

		// Title/Header.
		$title = isset( $data['title'] ) ? $data['title'] : 'Match Details';
		echo '<h3>' . esc_html( $title ) . '</h3>';
		if ( ! empty( $data['subtitle'] ) && is_string( $data['subtitle'] ) ) {
			echo '<p class="sfa-widget__subtitle">' . esc_html( $data['subtitle'] ) . '</p>';
		}

		if ( isset( $data['regions'][0]['rows'] ) ) {
			$headers = isset( $data['headers'] ) ? $data['headers'] : null;
			foreach ( $data['regions'] as $region ) {
				self::render_details_region( $region, $headers );
			}
		} else {
			echo '<pre>' . esc_html( print_r( $api_response, true ) ) . '</pre>'; // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r -- Fallback dump of an unknown response shape.
		}

		echo '</div>';
	}

	/**
	 * Render one region of a game as a titled table.
	 *
	 * @since 2.0.1
	 * @param array      $region  Region with optional `title` and `rows`.
	 * @param array|null $headers Table headers of the response.
	 * @return void
	 */
	private static function render_details_region( $region, $headers ) {
		if ( ! empty( $region['title'] ) ) {
			echo '<h4 class="sfa-region-title">' . esc_html( $region['title'] ) . '</h4>';
		}
		if ( ! isset( $region['rows'] ) ) {
			return;
		}

		echo '<div class="sfa-table-container sfa-table-container-flat">';
		echo '<div class="sfa-table-wrap"><table class="sfa-data-table"><caption class="sfa-visually-hidden">' . esc_html__( 'Spieldetails', 'swiss-floorball-api' ) . '</caption>';

		if ( ! empty( $headers ) ) {
			echo '<thead><tr>';
			foreach ( $headers as $header ) {
				$header_text = is_string( $header ) ? $header : '';
				if ( isset( $header['text'] ) ) {
					$header_text = $header['text'];
				}
				echo '<th scope="col">' . esc_html( $header_text ) . '</th>';
			}
			echo '</tr></thead>';
		}

		foreach ( $region['rows'] as $row ) {
			echo '<tr>';
			if ( isset( $row['cells'] ) ) {
				foreach ( $row['cells'] as $cell ) {
					self::render_details_cell( $cell );
				}
			}
			echo '</tr>';
		}
		echo '</table></div>';
		echo '</div>';
	}

	/**
	 * Render one table cell with an optional image and its text lines.
	 *
	 * @since 2.0.1
	 * @param array $cell Cell with optional `image` and `text`.
	 * @return void
	 */
	private static function render_details_cell( $cell ) {
		echo '<td>';
		if ( isset( $cell['image'] ) ) {
			$img_url = isset( $cell['image']['url'] ) ? $cell['image']['url'] : '';
			$img_alt = isset( $cell['image']['alt'] ) ? $cell['image']['alt'] : '';
			if ( ! empty( $img_url ) ) {
				echo '<img src="' . esc_url( $img_url ) . '" alt="' . esc_attr( $img_alt ) . '" class="sfa-cell-image">';
			}
		}
		if ( isset( $cell['text'] ) ) {
			foreach ( $cell['text'] as $text ) {
				echo esc_html( $text ) . '<br>';
			}
		}
		echo '</td>';
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
		$client       = Swiss_Floorball_API_Display::get_client();
		$api_response = $client->fetch_data(
			'rankings',
			array(
				'season'     => $season,
				'league'     => $league,
				'game_class' => $game_class,
				'group'      => $group,
			)
		);

		if ( is_wp_error( $api_response ) || ! isset( $api_response['data']['regions'][0]['rows'] ) ) {
			Swiss_Floorball_API_Display::render_fetch_error( $api_response );
			return;
		}

		$rows  = $api_response['data']['regions'][0]['rows'];
		$title = isset( $api_response['data']['title'] ) ? $api_response['data']['title'] : 'Rangliste';

		?>
		<h3 class="sfa-section-title"><?php Swiss_Floorball_Api_Icons::render( 'chart' ); ?> <?php echo esc_html( $title ); ?></h3>
		<div class="sfa-table-wrap"><table class="sfa-data-table">
			<caption class="sfa-visually-hidden"><?php esc_html_e( 'Rangliste', 'swiss-floorball-api' ); ?></caption>
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Rang', 'swiss-floorball-api' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Team', 'swiss-floorball-api' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Spiele', 'swiss-floorball-api' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Tordifferenz', 'swiss-floorball-api' ); ?></th>
					<th scope="col" class="sfa-align-right"><?php esc_html_e( 'Punkte', 'swiss-floorball-api' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				foreach ( $rows as $row ) {
					$rank      = isset( $row['cells'][0]['text'][0] ) ? $row['cells'][0]['text'][0] : '';
					$team      = isset( $row['cells'][2]['text'][0] ) ? $row['cells'][2]['text'][0] : '';
					$games     = isset( $row['cells'][3]['text'][0] ) ? $row['cells'][3]['text'][0] : '';
					$goal_diff = isset( $row['cells'][10]['text'][0] ) ? $row['cells'][10]['text'][0] : '';
					$points    = isset( $row['cells'][12]['text'][0] ) ? $row['cells'][12]['text'][0] : '';
					?>
					<tr>
						<td data-label="<?php esc_attr_e( 'Rang', 'swiss-floorball-api' ); ?>"><?php echo esc_html( $rank ); ?></td>
						<td data-label="<?php esc_attr_e( 'Team', 'swiss-floorball-api' ); ?>"><?php echo esc_html( $team ); ?></td>
						<td data-label="<?php esc_attr_e( 'Spiele', 'swiss-floorball-api' ); ?>"><?php echo esc_html( $games ); ?></td>
						<td data-label="<?php esc_attr_e( 'Tordifferenz', 'swiss-floorball-api' ); ?>"><?php echo esc_html( $goal_diff ); ?></td>
						<td class="sfa-align-right" data-label="<?php esc_attr_e( 'Punkte', 'swiss-floorball-api' ); ?>"><?php echo esc_html( $points ); ?></td>
					</tr>
					<?php
				}
				?>
			</tbody>
		</table></div>
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
		self::render_team_ranking( $season, $league, $game_class, $group );
	}

	/**
	 * Get player details.
	 *
	 * @since 1.0.0
	 * @param int|string $player_id Player ID.
	 * @return void
	 */
	public static function render_player( $player_id ) {
		$client       = Swiss_Floorball_API_Display::get_client();
		$api_response = $client->fetch_data( 'players/' . $player_id );

		if ( is_wp_error( $api_response ) ) {
			Swiss_Floorball_API_Display::render_fetch_error( $api_response );
			return;
		}

		$data = isset( $api_response['data'] ) ? $api_response['data'] : $api_response;

		// The response shape was not verified live, so render by structure and never dump raw data.
		if ( is_array( $data ) && isset( $data['regions'] ) && is_array( $data['regions'] ) ) {
			$rendered = false;
			foreach ( $data['regions'] as $region ) {
				if ( empty( $region['rows'] ) || ! is_array( $region['rows'] ) ) {
					continue;
				}
				if ( ! $rendered ) {
					echo '<div class="sfa-table-wrap"><table class="sfa-data-table"><caption class="sfa-visually-hidden">' . esc_html__( 'Spielerprofil', 'swiss-floorball-api' ) . '</caption><tbody>';
					$rendered = true;
				}
				foreach ( $region['rows'] as $row ) {
					if ( empty( $row['cells'] ) || ! is_array( $row['cells'] ) ) {
						continue;
					}
					echo '<tr>';
					foreach ( $row['cells'] as $cell ) {
						$text = '';
						if ( isset( $cell['text'] ) && is_array( $cell['text'] ) ) {
							$text = implode( ' ', array_filter( $cell['text'], 'is_scalar' ) );
						}
						echo '<td>' . esc_html( $text ) . '</td>';
					}
					echo '</tr>';
				}
			}
			if ( $rendered ) {
				echo '</tbody></table></div>';
				return;
			}
		} elseif ( is_array( $data ) ) {
			$lines = array();
			foreach ( $data as $key => $value ) {
				if ( ! is_scalar( $value ) || '' === (string) $value ) {
					continue;
				}
				$lines[ $key ] = $value;
			}
			if ( ! empty( $lines ) ) {
				echo '<div class="sfa-table-wrap"><table class="sfa-data-table"><caption class="sfa-visually-hidden">' . esc_html__( 'Spielerprofil', 'swiss-floorball-api' ) . '</caption><tbody>';
				foreach ( $lines as $key => $value ) {
					echo '<tr><th scope="row">' . esc_html( str_replace( '_', ' ', (string) $key ) ) . '</th><td>' . esc_html( (string) $value ) . '</td></tr>';
				}
				echo '</tbody></table></div>';
				return;
			}
		}

		echo '<p>' . esc_html__( 'Keine Spielerdaten verfügbar.', 'swiss-floorball-api' ) . '</p>';
	}

	/**
	 * Get national players.
	 *
	 * @return void
	 */
	public static function render_national_players() {
		$client       = Swiss_Floorball_API_Display::get_client();
		$api_response = $client->fetch_data( 'national_players' );

		if ( is_wp_error( $api_response ) || ! isset( $api_response['data']['regions'][0]['rows'] ) ) {
			Swiss_Floorball_API_Display::render_fetch_error( $api_response );
			return;
		}

		$rows  = $api_response['data']['regions'][0]['rows'];
		$title = isset( $api_response['data']['title'] ) ? $api_response['data']['title'] : 'Nationalspieler';

		?>
		<h2 class="sfa-section-title"><?php Swiss_Floorball_Api_Icons::render( 'person' ); ?> <?php echo esc_html( $title ); ?></h2>
		<div class="sfa-table-wrap"><table class="sfa-data-table">
			<caption class="sfa-visually-hidden"><?php esc_html_e( 'Nationalspieler', 'swiss-floorball-api' ); ?></caption>
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Nr', 'swiss-floorball-api' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Position', 'swiss-floorball-api' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Name', 'swiss-floorball-api' ); ?></th>
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
						<td data-label="<?php esc_attr_e( 'Nr', 'swiss-floorball-api' ); ?>"><?php echo esc_html( $nr ); ?></td>
						<td data-label="<?php esc_attr_e( 'Position', 'swiss-floorball-api' ); ?>"><?php echo esc_html( $pos ); ?></td>
						<td data-label="<?php esc_attr_e( 'Name', 'swiss-floorball-api' ); ?>"><?php echo esc_html( $name ); ?></td>
					</tr>
					<?php
				}
				?>
			</tbody>
		</table></div>
		<?php
	}

	/**
	 * Get topscorers.
	 *
	 * @param int|string $season Season ID.
	 * @param int|string $league League ID.
	 * @param int|string $game_class Game Class ID.
	 * @return void
	 */
	public static function render_topscorers( $season, $league, $game_class ) {
		$client       = Swiss_Floorball_API_Display::get_client();
		$api_response = $client->fetch_data(
			'topscorers/su',
			array(
				'season'     => $season,
				'league'     => $league,
				'game_class' => $game_class,
			)
		);

		if ( is_wp_error( $api_response ) || ! isset( $api_response['data']['regions'][0]['rows'] ) ) {
			Swiss_Floorball_API_Display::render_fetch_error( $api_response );
			return;
		}

		$rows  = $api_response['data']['regions'][0]['rows'];
		$title = isset( $api_response['data']['title'] ) ? $api_response['data']['title'] : 'Topscorer';

		?>
		<h3 class="sfa-section-title"><?php Swiss_Floorball_Api_Icons::render( 'trophy' ); ?> <?php echo esc_html( $title ); ?></h3>
		<div class="sfa-table-wrap"><table class="sfa-data-table">
			<caption class="sfa-visually-hidden"><?php esc_html_e( 'Topscorer', 'swiss-floorball-api' ); ?></caption>
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Rang', 'swiss-floorball-api' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Spieler', 'swiss-floorball-api' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Team', 'swiss-floorball-api' ); ?></th>
					<th scope="col" class="sfa-align-right"><?php esc_html_e( 'Tore', 'swiss-floorball-api' ); ?></th>
					<th scope="col" class="sfa-align-right"><?php esc_html_e( 'Assists', 'swiss-floorball-api' ); ?></th>
					<th scope="col" class="sfa-align-right"><?php esc_html_e( 'Punkte', 'swiss-floorball-api' ); ?></th>
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
						<td data-label="<?php esc_attr_e( 'Rang', 'swiss-floorball-api' ); ?>"><?php echo esc_html( $rank ); ?></td>
						<td data-label="<?php esc_attr_e( 'Spieler', 'swiss-floorball-api' ); ?>"><?php echo esc_html( $player ); ?></td>
						<td data-label="<?php esc_attr_e( 'Team', 'swiss-floorball-api' ); ?>"><?php echo esc_html( $team ); ?></td>
						<td class="sfa-align-right" data-label="<?php esc_attr_e( 'Tore', 'swiss-floorball-api' ); ?>"><?php echo esc_html( $goals ); ?></td>
						<td class="sfa-align-right" data-label="<?php esc_attr_e( 'Assists', 'swiss-floorball-api' ); ?>"><?php echo esc_html( $assists ); ?></td>
						<td class="sfa-align-right" data-label="<?php esc_attr_e( 'Punkte', 'swiss-floorball-api' ); ?>"><?php echo esc_html( $points ); ?></td>
					</tr>
					<?php
				}
				?>
			</tbody>
		</table></div>
		<?php
	}

	/**
	 * Get game events.
	 *
	 * @param int|string $game_id Game ID.
	 * @return void
	 */
	public static function render_game_events( $game_id ) {
		$client       = Swiss_Floorball_API_Display::get_client();
		$api_response = $client->fetch_data( 'game_events/' . $game_id );

		if ( is_wp_error( $api_response ) || ! isset( $api_response['data']['regions'][0]['rows'] ) ) {
			Swiss_Floorball_API_Display::render_fetch_error( $api_response );
			return;
		}

		$rows = $api_response['data']['regions'][0]['rows'];

		?>
		<h3><?php esc_html_e( 'Match-Telegramm', 'swiss-floorball-api' ); ?></h3>
		<div class="sfa-table-wrap"><table class="sfa-data-table">
			<caption class="sfa-visually-hidden"><?php esc_html_e( 'Spielereignisse', 'swiss-floorball-api' ); ?></caption>
					<thead>
			<tr>
				<th scope="col"><?php esc_html_e( 'Zeit', 'swiss-floorball-api' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Ereignis', 'swiss-floorball-api' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Goal', 'swiss-floorball-api' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Team', 'swiss-floorball-api' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php
			foreach ( $rows as $row ) {
				$time   = isset( $row['cells'][0]['text'][0] ) ? $row['cells'][0]['text'][0] : '';
				$event  = isset( $row['cells'][1]['text'][0] ) ? $row['cells'][1]['text'][0] : '';
				$player = isset( $row['cells'][3]['text'][0] ) ? $row['cells'][3]['text'][0] : '';
				$team   = isset( $row['cells'][2]['text'][0] ) ? $row['cells'][2]['text'][0] : '';
				// Extract goal score from event string if present (e.g., "Torschütze 1:0").
				$goal = '';
				if ( preg_match( '/(\d+:\d+)/', $event, $matches ) ) {
					$goal = $matches[1];
				}
				if ( ! empty( $player ) ) {
					$event .= ' - ' . $player;
				}
				?>
				<tr>
					<td data-label="<?php esc_attr_e( 'Zeit', 'swiss-floorball-api' ); ?>"><?php echo esc_html( $time ); ?></td>
					<td data-label="<?php esc_attr_e( 'Ereignis', 'swiss-floorball-api' ); ?>"><?php echo esc_html( $event ); ?></td>
					<td data-label="<?php esc_attr_e( 'Goal', 'swiss-floorball-api' ); ?>"><?php echo esc_html( $goal ); ?></td>
					<td data-label="<?php esc_attr_e( 'Team', 'swiss-floorball-api' ); ?>"><?php echo esc_html( $team ); ?></td>
				</tr>
				<?php
			}
			?>
		</tbody>
		</table></div>
		<?php
	}
}
