<?php
/**
 * Provide a admin area view for the plugin
 *
 * This file is used to markup the admin-facing aspects of the plugin.
 *
 * @link       https://flaviowaser.ch
 * @since      1.0.1
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

// Get the configured club ID.
$swfl_club_id        = get_option( 'swfl_club_number' );
$swfl_current_season = SWFL_Display::get_current_season();

// Instantiate the API client.
require_once plugin_dir_path( dirname( __DIR__ ) ) . 'includes/class-swfl-client.php';
require_once plugin_dir_path( dirname( __DIR__ ) ) . 'includes/class-swfl-display.php';
$swfl_client = new SWFL_Client();

// Check if we are viewing a specific match
// Read-only admin navigation parameter, no state change.
$swfl_match_id = isset( $_GET['match_id'] ) ? absint( $_GET['match_id'] ) : null; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

?>

<div class="wrap sfa-admin-wrap" data-sfa-theme="<?php echo esc_attr( SWFL_Theme::get_theme() ); ?>">
	<div class="sfa-admin-header">
		<h1><?php SWFL_Icons::render( 'hockey' ); ?> <?php echo esc_html( get_admin_page_title() ); ?></h1>
		<p><?php esc_html_e( 'Recent games and their details', 'swiss-floorball-api' ); ?></p>
	</div>

	<?php if ( empty( $swfl_club_id ) ) : ?>
		<div class="notice notice-error">
			<p><?php esc_html_e( 'Please set a club number in the settings first.', 'swiss-floorball-api' ); ?></p>
		</div>
	<?php elseif ( $swfl_match_id ) : ?>
		<?php
		// --- Single Match View ---

		// Back button.
		$swfl_back_url = remove_query_arg( 'match_id' );
		echo '<p><a href="' . esc_url( $swfl_back_url ) . '" class="button button-primary">' . wp_kses( SWFL_Icons::get( 'back' ), SWFL_Icons::allowed_html() ) . ' ' . esc_html__( 'Back to the overview', 'swiss-floorball-api' ) . '</a></p>';

		// Render Match Details.
		SWFL_Display_Stats::render_game_details_table( $swfl_match_id );

		// Show Game Events (Match Telegramm).
		echo '<div class="sfa-card sfa-card--spaced-top">';
		SWFL_Display_Stats::render_game_events( $swfl_match_id );
		echo '</div>';
		?>

	<?php else : ?>
		<?php
		// --- List View ---

		// Render Club Games List.
		SWFL_Display::render_club_games( $swfl_club_id, $swfl_current_season );
		?>

		<hr class="sfa-divider">
		
		<div class="sfa-admin-header">
			<h1><?php SWFL_Icons::render( 'hockey' ); ?> <?php esc_html_e( 'Games per team', 'swiss-floorball-api' ); ?></h1>
			<p><?php esc_html_e( 'Recent games and their details', 'swiss-floorball-api' ); ?></p>
		</div>
		
		<?php
		// Fetch teams for the club.
		$swfl_teams_response = $swfl_client->fetch_data( 'clubs/' . $swfl_club_id . '/statistics' );

		if ( is_wp_error( $swfl_teams_response ) || ! isset( $swfl_teams_response['data']['regions'][0]['rows'] ) ) {
			echo '<div class="notice notice-warning"><p>' . esc_html__( 'The teams could not be loaded.', 'swiss-floorball-api' ) . '</p></div>';
		} else {
			$swfl_teams = $swfl_teams_response['data']['regions'][0]['rows'];

			foreach ( $swfl_teams as $swfl_team ) {
				$swfl_team_id   = $swfl_team['team_id'];
				$swfl_team_name = isset( $swfl_team['cells'][0]['text'][0] ) ? $swfl_team['cells'][0]['text'][0] : sprintf(
					/* translators: %d: team ID. */
					__( 'Team %d', 'swiss-floorball-api' ),
					$swfl_team_id
				);

				echo '<div class="sfa-card sfa-card--spaced-bottom">';
				echo '<h3>' . esc_html( $swfl_team_name ) . '</h3>';
				echo '<div class="sfa-table-container sfa-table-container-flat">';

				// Use the display class to render games for this team.
				SWFL_Display::render_team_games( $swfl_team_id, $swfl_current_season );

				echo '</div>';
				echo '</div>';
			}
		}
		?>
	<?php endif; ?>
</div>
