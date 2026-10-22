<?php
/**
 * Admin page: overview of all shortcodes with their attributes and examples.
 *
 * @link       https://flaviowaser.ch
 * @since      1.0.0
 *
 * @package    SWFL
 * @subpackage SWFL_Plugin/admin/partials
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( esc_html__( 'You do not have permission to access this page.', 'swiss-floorball-api' ) );
}

// Markup the descriptions may use.
$swfl_inline_html = array(
	'code'   => array(),
	'strong' => array(),
	'em'     => array(),
	'a'      => array( 'href' => true ),
);

$swfl_club_setting = __( 'The club always comes from the plugin settings.', 'swiss-floorball-api' );
$swfl_season       = __( 'Start year of the season (default: current season).', 'swiss-floorball-api' );
$swfl_limit        = __( 'Maximum number of rows (0 = all).', 'swiss-floorball-api' );
$swfl_offset       = __( 'Number of rows to skip.', 'swiss-floorball-api' );
$swfl_upcoming     = __( '<code>true</code> shows upcoming games only.', 'swiss-floorball-api' );
$swfl_pagination   = __( '<code>true</code> adds previous/next links (only with <code>limit</code>).', 'swiss-floorball-api' );
$swfl_page_size    = __( 'Games per page (default: 4).', 'swiss-floorball-api' );
$swfl_team_or_club = __( 'Team ID, to show a team instead of the club.', 'swiss-floorball-api' );
$swfl_game_limit   = __( 'Number of games (default: 1, at most 10).', 'swiss-floorball-api' );
$swfl_league       = __( 'League ID.', 'swiss-floorball-api' );
$swfl_game_class   = __( 'Game class ID.', 'swiss-floorball-api' );
$swfl_group_name   = __( 'Group name, e.g. "Gruppe 1".', 'swiss-floorball-api' );
$swfl_none         = __( 'None', 'swiss-floorball-api' );
$swfl_team_ids     = sprintf(
	/* translators: %s: link to the admin page "Club overview". */
	__( 'Team IDs are listed on the %s page.', 'swiss-floorball-api' ),
	'<a href="' . esc_url( admin_url( 'admin.php?page=swiss-floorball-api-overview' ) ) . '">' . esc_html__( 'Club overview', 'swiss-floorball-api' ) . '</a>'
);

/*
 * Each shortcode: description, attributes (name => text, an empty name for a plain line) and examples
 * (label => shortcode, an empty label for an example without one).
 */
$swfl_shortcodes = array(
	'swfl-club-teams'         => array(
		__( 'Lists all teams of the configured club.', 'swiss-floorball-api' ),
		array( '' => __( 'None (uses the plugin settings)', 'swiss-floorball-api' ) ),
		array( '' => '[swfl-club-teams]' ),
	),
	'swfl-club-games'         => array(
		__( 'Shows the games of the club week by week, with buttons to change the week.', 'swiss-floorball-api' ),
		array(
			''                => $swfl_club_setting,
			'season'          => $swfl_season,
			'limit'           => $swfl_limit,
			'offset'          => $swfl_offset,
			'show_upcoming'   => $swfl_upcoming,
			'show_pagination' => $swfl_pagination,
		),
		array( '' => '[swfl-club-games season="2025" limit="5" show_upcoming="true"]' ),
	),
	'swfl-team-games'         => array(
		__( 'Shows the games of a team page by page, starting around the next game.', 'swiss-floorball-api' ),
		array(
			'team_id'         => __( '(required) The ID of the team.', 'swiss-floorball-api' ) . ' <em>' . $swfl_team_ids . '</em>',
			'season'          => $swfl_season,
			'page_size'       => $swfl_page_size,
			'limit'           => $swfl_limit,
			'offset'          => $swfl_offset,
			'show_upcoming'   => $swfl_upcoming,
			'show_pagination' => $swfl_pagination,
		),
		array( '' => '[swfl-team-games team_id="429626" season="2025" limit="5" show_upcoming="true"]' ),
	),
	'swfl-club-team-games'    => array(
		__( 'Shows all teams of the club in a select list and the games of the selected team.', 'swiss-floorball-api' ),
		array(
			''          => $swfl_club_setting,
			'season'    => $swfl_season,
			'page_size' => $swfl_page_size,
		),
		array( '' => '[swfl-club-team-games season="2025" page_size="6"]' ),
	),
	'swfl-next-game'          => array(
		__( 'Shows the next game of the club or of a team. The widget "Next game and last result" under Appearance → Widgets shows the same.', 'swiss-floorball-api' ),
		array(
			''        => $swfl_club_setting,
			'team_id' => $swfl_team_or_club,
			'season'  => $swfl_season,
			'limit'   => $swfl_game_limit,
		),
		array( '' => '[swfl-next-game team_id="429626"]' ),
	),
	'swfl-last-result'        => array(
		__( 'Shows the last result of the club or of a team, the latest first.', 'swiss-floorball-api' ),
		array(
			''        => $swfl_club_setting,
			'team_id' => $swfl_team_or_club,
			'season'  => $swfl_season,
			'limit'   => $swfl_game_limit,
		),
		array( '' => '[swfl-last-result limit="3"]' ),
	),
	'swfl-league-games'       => array(
		__( 'Shows the games of a league and group, round by round. Playoff series are grouped.', 'swiss-floorball-api' ),
		array(
			'game_class' => __( '(required) Game class ID.', 'swiss-floorball-api' ),
			'league'     => __( '(required) League ID.', 'swiss-floorball-api' ),
			'season'     => $swfl_season,
			'group'      => $swfl_group_name,
		),
		array( '' => '[swfl-league-games game_class="21" league="3" season="2025" group="Gruppe 1"]' ),
	),
	'swfl-mobiliar-topscorer' => array(
		__( 'Shows the Mobiliar topscorers of a club as cards.', 'swiss-floorball-api' ),
		array(
			'club_id' => __( 'Club ID (default: plugin setting).', 'swiss-floorball-api' ),
			'season'  => $swfl_season,
		),
		array( '' => '[swfl-mobiliar-topscorer club_id="463845" season="2025"]' ),
	),
	'swfl-clubs'              => array(
		__( 'Lists all clubs.', 'swiss-floorball-api' ),
		array( '' => $swfl_none ),
		array( '' => '[swfl-clubs]' ),
	),
	'swfl-calendars'          => array(
		__( 'Creates a calendar link (webcal) for a team, a club or a league.', 'swiss-floorball-api' ),
		array(
			'team_id'    => __( '(required for a team calendar) The ID of the team.', 'swiss-floorball-api' ) . ' <em>' . $swfl_team_ids . '</em>',
			'club_id'    => __( 'The ID of the club.', 'swiss-floorball-api' ),
			'season'     => __( 'The season (e.g. 2025).', 'swiss-floorball-api' ),
			'league'     => __( 'The league.', 'swiss-floorball-api' ),
			'game_class' => __( 'The game class.', 'swiss-floorball-api' ),
			'group'      => __( 'The group.', 'swiss-floorball-api' ),
		),
		array(
			__( 'Team calendar:', 'swiss-floorball-api' ) => '[swfl-calendars team_id="427892"]',
			__( 'Club calendar:', 'swiss-floorball-api' ) => '[swfl-calendars club_id="637"]',
		),
	),
	'swfl-cups'               => array(
		__( 'Lists all cup competitions.', 'swiss-floorball-api' ),
		array( '' => $swfl_none ),
		array( '' => '[swfl-cups]' ),
	),
	'swfl-groups'             => array(
		__( 'Lists the groups of a season, league and game class.', 'swiss-floorball-api' ),
		array(
			'season'     => __( 'Season (default: current season from the settings).', 'swiss-floorball-api' ),
			'league'     => $swfl_league,
			'game_class' => $swfl_game_class,
		),
		array( '' => '[swfl-groups league="1" game_class="11"]' ),
	),
	'swfl-teams'              => array(
		__( 'Lists all teams.', 'swiss-floorball-api' ),
		array( '' => $swfl_none ),
		array( '' => '[swfl-teams]' ),
	),
	'swfl-rankings'           => array(
		__( 'Shows the ranking of a group.', 'swiss-floorball-api' ),
		array(
			'season'     => __( 'Season (default: current season).', 'swiss-floorball-api' ),
			'league'     => $swfl_league,
			'game_class' => $swfl_game_class,
			'group'      => $swfl_group_name,
		),
		array( '' => '[swfl-rankings season="2025" league="3" game_class="21" group="Gruppe 1"]' ),
	),
	'swfl-player'             => array(
		__( 'Shows information about a player.', 'swiss-floorball-api' ),
		array( 'player_id' => __( '(required) The ID of the player.', 'swiss-floorball-api' ) ),
		array( '' => '[swfl-player player_id="99999"]' ),
	),
	'swfl-national-players'   => array(
		__( 'Lists the national players.', 'swiss-floorball-api' ),
		array( '' => $swfl_none ),
		array( '' => '[swfl-national-players]' ),
	),
	'swfl-topscorers'         => array(
		__( 'Shows the topscorers of a league or group.', 'swiss-floorball-api' ),
		array(
			'season'          => __( 'Season (default: current season).', 'swiss-floorball-api' ),
			'league'          => $swfl_league,
			'game_class'      => $swfl_game_class,
			'group'           => __( 'Group ID.', 'swiss-floorball-api' ),
			'limit'           => $swfl_limit,
			'offset'          => $swfl_offset,
			'show_pagination' => $swfl_pagination,
		),
		array( '' => '[swfl-topscorers league="1"]' ),
	),
	'swfl-game-events'        => array(
		__( 'Shows the events (goals, penalties) of a game.', 'swiss-floorball-api' ),
		array( 'game_id' => __( '(required) The ID of the game.', 'swiss-floorball-api' ) ),
		array( '' => '[swfl-game-events game_id="1000"]' ),
	),
);

$swfl_backend_functions = array(
	'render_leagues()'            => array( __( 'Shows a list of all available leagues.', 'swiss-floorball-api' ), __( 'Admin only, there is no public shortcode.', 'swiss-floorball-api' ) ),
	'render_seasons()'            => array( __( 'Shows a list of all available seasons.', 'swiss-floorball-api' ), __( 'Admin only, there is no public shortcode.', 'swiss-floorball-api' ) ),
	'render_club_games_callout()' => array( __( 'Shows a callout box with the games of the club in the admin style.', 'swiss-floorball-api' ), __( 'Admin only, a special view for the dashboard.', 'swiss-floorball-api' ) ),
	'render_club_games_table()'   => array( __( 'Shows a table with the games of the club in the admin style.', 'swiss-floorball-api' ), __( 'Admin only, a special view for the dashboard.', 'swiss-floorball-api' ) ),
	'get_teamdetails_image()'     => array( __( 'Gets the logo or picture of a team from the API.', 'swiss-floorball-api' ), __( 'Helper, used by other functions.', 'swiss-floorball-api' ) ),
	'get_gamedetails()'           => array( __( 'Gets the details of a game.', 'swiss-floorball-api' ), __( 'Helper, used by other functions.', 'swiss-floorball-api' ) ),
);
?>

<div class="wrap sfa-admin-wrap" data-sfa-theme="<?php echo esc_attr( SWFL_Theme::get_theme() ); ?>">
	<div class="sfa-admin-header">
		<h1><?php SWFL_Icons::render( 'description' ); ?> <?php esc_html_e( 'Shortcodes overview', 'swiss-floorball-api' ); ?></h1>
		<p><?php esc_html_e( 'All available shortcodes with their attributes and examples.', 'swiss-floorball-api' ); ?></p>
	</div>

	<div class="sfa-table-container">
		<h2><?php esc_html_e( 'Available shortcodes', 'swiss-floorball-api' ); ?></h2>
		<table class="sfa-data-table">
			<thead>
				<tr>
					<th class="sfa-col-shortcode"><?php esc_html_e( 'Shortcode', 'swiss-floorball-api' ); ?></th>
					<th class="sfa-col-description"><?php esc_html_e( 'Description', 'swiss-floorball-api' ); ?></th>
					<th class="sfa-col-params"><?php esc_html_e( 'Attributes', 'swiss-floorball-api' ); ?></th>
					<th class="sfa-col-example"><?php esc_html_e( 'Example', 'swiss-floorball-api' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $swfl_shortcodes as $swfl_tag => $swfl_shortcode ) : ?>
					<tr>
						<td><code>[<?php echo esc_html( $swfl_tag ); ?>]</code></td>
						<td><?php echo esc_html( $swfl_shortcode[0] ); ?></td>
						<td>
							<?php
							$swfl_lines = array();
							foreach ( $swfl_shortcode[1] as $swfl_name => $swfl_text ) {
								$swfl_lines[] = ( '' === $swfl_name ? '' : '<strong>' . esc_html( $swfl_name ) . '</strong>: ' ) . wp_kses( $swfl_text, $swfl_inline_html );
							}
							echo implode( '<br>', $swfl_lines ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Every line is escaped above.
							?>
						</td>
						<td>
							<?php foreach ( $swfl_shortcode[2] as $swfl_label => $swfl_example ) : ?>
								<?php if ( '' !== $swfl_label ) : ?>
									<strong><?php echo esc_html( $swfl_label ); ?></strong><br>
								<?php endif; ?>
								<code><?php echo esc_html( $swfl_example ); ?></code><br>
							<?php endforeach; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>

	<div class="sfa-table-container">
		<h2><?php SWFL_Icons::render( 'settings' ); ?> <?php esc_html_e( 'Admin-only functions', 'swiss-floorball-api' ); ?></h2>
		<p><?php esc_html_e( 'The following functions are only used in the admin area and have no public shortcode.', 'swiss-floorball-api' ); ?></p>

		<table class="sfa-data-table">
			<thead>
				<tr>
					<th class="sfa-col-function"><?php esc_html_e( 'Function', 'swiss-floorball-api' ); ?></th>
					<th class="sfa-col-description-wide"><?php esc_html_e( 'Description', 'swiss-floorball-api' ); ?></th>
					<th class="sfa-col-usage"><?php esc_html_e( 'Use', 'swiss-floorball-api' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $swfl_backend_functions as $swfl_function => $swfl_texts ) : ?>
					<tr>
						<td><code><?php echo esc_html( $swfl_function ); ?></code></td>
						<td><?php echo esc_html( $swfl_texts[0] ); ?></td>
						<td><?php echo esc_html( $swfl_texts[1] ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<div class="sfa-info-box">
			<h3 class="sfa-info-box__title">ℹ️ <?php esc_html_e( 'Note', 'swiss-floorball-api' ); ?></h3>
			<p class="sfa-info-box__text">
				<?php esc_html_e( 'If you need one of these functions as a public shortcode, please open a feature request. They already exist in the plugin and could be made available as shortcodes.', 'swiss-floorball-api' ); ?>
			</p>
		</div>
	</div>
</div>
