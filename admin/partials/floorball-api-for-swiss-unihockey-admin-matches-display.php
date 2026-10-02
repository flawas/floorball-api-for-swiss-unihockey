<?php
/**
 * Provide a admin area view for the plugin
 *
 * This file is used to markup the admin-facing aspects of the plugin.
 *
 * @link       https://flaviowaser.ch
 * @since      1.0.1
 *
 * @package    Swiss_Floorball_Api
 * @subpackage Swiss_Floorball_Api/admin/partials
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( esc_html__( 'You do not have permission to access this page.', 'swiss-floorball-api' ) );
}

// Get the configured club ID.
$swfl_club_id        = get_option( 'swissfloorball_club_number' );
$swfl_current_season = Swiss_Floorball_API_Display::get_current_season();

// Instantiate the API client.
require_once plugin_dir_path( dirname( __DIR__ ) ) . 'includes/class-floorball-api-for-swiss-unihockey-client.php';
require_once plugin_dir_path( dirname( __DIR__ ) ) . 'includes/class-floorball-api-for-swiss-unihockey-display.php';
$swfl_client = new Swiss_Floorball_API_Client();

// Check if we are viewing a specific match
// Read-only admin navigation parameter, no state change.
$swfl_match_id = isset( $_GET['match_id'] ) ? absint( $_GET['match_id'] ) : null; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

?>

<div class="wrap sfa-admin-wrap" data-sfa-theme="<?php echo esc_attr( Swiss_Floorball_API_Display::get_theme() ); ?>">
	<div class="sfa-admin-header">
		<h1><?php Swiss_Floorball_Api_Icons::render( 'hockey' ); ?> <?php echo esc_html( get_admin_page_title() ); ?></h1>
		<p><?php esc_html_e( 'Übersicht der letzten Spiele und Details', 'swiss-floorball-api' ); ?></p>
	</div>

	<?php if ( empty( $swfl_club_id ) ) : ?>
		<div class="notice notice-error">
			<p><?php esc_html_e( 'Bitte konfigurieren Sie zuerst eine Club-Nummer in den Einstellungen.', 'swiss-floorball-api' ); ?></p>
		</div>
	<?php elseif ( $swfl_match_id ) : ?>
		<?php
		// --- Single Match View ---

		// Back button.
		$swfl_back_url = remove_query_arg( 'match_id' );
		echo '<p><a href="' . esc_url( $swfl_back_url ) . '" class="button button-primary">' . Swiss_Floorball_Api_Icons::get( 'back' ) . ' ' . esc_html__( 'Zurück zur Übersicht', 'swiss-floorball-api' ) . '</a></p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Icon markup sanitized via wp_kses() in Swiss_Floorball_Api_Icons::get().

		// Render Match Details.
		Swiss_Floorball_API_Display::render_game_details_table( $swfl_match_id );

		// Show Game Events (Match Telegramm).
		echo '<div class="sfa-card sfa-card--spaced-top">';
		Swiss_Floorball_API_Display::render_game_events( $swfl_match_id, true );
		echo '</div>';
		?>

	<?php else : ?>
		<?php
		// --- List View ---

		// Render Club Games List.
		Swiss_Floorball_API_Display::render_club_games( $swfl_club_id, $swfl_current_season, true );
		?>

		<hr class="sfa-divider">
		
		<div class="sfa-admin-header">
			<h1><?php Swiss_Floorball_Api_Icons::render( 'hockey' ); ?> <?php esc_html_e( 'Spiele pro Team', 'swiss-floorball-api' ); ?></h1>
			<p><?php esc_html_e( 'Übersicht der letzten Spiele und Details', 'swiss-floorball-api' ); ?></p>
		</div>
		
		<?php
		// Fetch teams for the club.
		$swfl_teams_response = $swfl_client->fetch_data( 'clubs/' . $swfl_club_id . '/statistics' );

		if ( is_wp_error( $swfl_teams_response ) || ! isset( $swfl_teams_response['data']['regions'][0]['rows'] ) ) {
			echo '<div class="notice notice-warning"><p>' . esc_html__( 'Konnte Teams nicht laden.', 'swiss-floorball-api' ) . '</p></div>';
		} else {
			$swfl_teams = $swfl_teams_response['data']['regions'][0]['rows'];

			foreach ( $swfl_teams as $swfl_team ) {
				$swfl_team_id   = $swfl_team['team_id'];
				$swfl_team_name = isset( $swfl_team['cells'][0]['text'][0] ) ? $swfl_team['cells'][0]['text'][0] : 'Team ' . $swfl_team_id;

				echo '<div class="sfa-card sfa-card--spaced-bottom">';
				echo '<h3>' . esc_html( $swfl_team_name ) . '</h3>';
				echo '<div class="sfa-table-container sfa-table-container-flat">';

				// Use the display class to render games for this team.
				Swiss_Floorball_API_Display::render_team_games( $swfl_team_id, $swfl_current_season, true );

				echo '</div>';
				echo '</div>';
			}
		}
		?>
	<?php endif; ?>
</div>
