<?php
/**
 * Next game and latest result of a club or team.
 *
 * @link       https://flaviowaser.ch
 * @since      2.0.4
 *
 * @package    SWFL
 * @subpackage SWFL_Plugin/includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the next game(s) and the latest result(s) as a small excerpt of the games table.
 *
 * Reuses the static list output of SWFL_Widgets, so the markup, escaping and styles are the
 * same as for the `limit` and `show_upcoming` options of the games shortcodes.
 *
 * @since      2.0.4
 * @package    SWFL
 * @subpackage SWFL_Plugin/includes
 * @author     Flavio Waser <kontakt@flawas.ch>
 */
class SWFL_Highlights {

	/**
	 * Largest number of games a single block shows.
	 *
	 * @since 2.0.4
	 */
	const MAX_LIMIT = 10;

	/**
	 * Clamp a requested number of games to 1..MAX_LIMIT.
	 *
	 * @since 2.0.4
	 * @param mixed $limit Requested number of games.
	 * @return int Number of games to show.
	 */
	public static function normalize_limit( $limit ) {
		$limit = absint( $limit );

		return $limit < 1 ? 1 : min( $limit, self::MAX_LIMIT );
	}

	/**
	 * Render the next upcoming game(s). A game that is played today still counts as upcoming.
	 *
	 * @since 2.0.4
	 * @param int $club_id Club id.
	 * @param int $season  Season (start year).
	 * @param int $team_id Optional. Team id; shows the games of this team instead of the club.
	 * @param int $limit   Optional. Number of games, 1 by default.
	 * @return void
	 */
	public static function render_next_game( $club_id, $season, $team_id = 0, $limit = 1 ) {
		$limit = self::normalize_limit( $limit );

		self::render(
			$club_id,
			$season,
			$team_id,
			array(
				'limit'      => $limit,
				'offset'     => 0,
				'upcoming'   => true,
				'dated_only' => true,
				'past'       => false,
				'pagination' => false,
				'title'      => _n( 'Nächstes Spiel', 'Nächste Spiele', $limit, 'swiss-floorball-api' ),
			)
		);
	}

	/**
	 * Render the most recent result(s), newest first. Games of today count as upcoming until the next day.
	 *
	 * @since 2.0.4
	 * @param int $club_id Club id.
	 * @param int $season  Season (start year).
	 * @param int $team_id Optional. Team id; shows the games of this team instead of the club.
	 * @param int $limit   Optional. Number of games, 1 by default.
	 * @return void
	 */
	public static function render_last_result( $club_id, $season, $team_id = 0, $limit = 1 ) {
		$limit = self::normalize_limit( $limit );

		self::render(
			$club_id,
			$season,
			$team_id,
			array(
				'limit'      => $limit,
				'offset'     => 0,
				'upcoming'   => false,
				'past'       => true,
				'pagination' => false,
				'title'      => _n( 'Letztes Resultat', 'Letzte Resultate', $limit, 'swiss-floorball-api' ),
			)
		);
	}

	/**
	 * Render a trimmed game list of a team, or of the club without a team.
	 *
	 * @since 2.0.4
	 * @param int   $club_id Club id.
	 * @param int   $season  Season (start year).
	 * @param int   $team_id Team id, 0 for the club.
	 * @param array $list    List options of SWFL_Table_Data::get_list_options(), plus a `title`.
	 * @return void
	 */
	private static function render( $club_id, $season, $team_id, $list ) {
		if ( $team_id > 0 ) {
			SWFL_Widgets::render_team_games( $team_id, $season, 0, false, $list );
			return;
		}

		SWFL_Widgets::render_club_games( $club_id, $season, $list );
	}
}
