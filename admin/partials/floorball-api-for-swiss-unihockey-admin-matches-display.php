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

// Get the configured club ID
$club_id = get_option( 'swissfloorball_club_number' );
$current_season = get_option( 'swissfloorball_actual_season', date('Y') );

// Instantiate the API client
require_once plugin_dir_path( dirname( dirname( __FILE__ ) ) ) . 'includes/class-swiss-floorball-api-client.php';
require_once plugin_dir_path( dirname( dirname( __FILE__ ) ) ) . 'includes/class-swiss-floorball-api-display.php';
$client = new Swiss_Floorball_API_Client();

// Check if we are viewing a specific match
$match_id = isset( $_GET['match_id'] ) ? sanitize_text_field( $_GET['match_id'] ) : null;

?>

<div class="wrap sfa-admin-wrap">
    <div class="sfa-admin-header">
        <h1>🏑 <?php echo esc_html( get_admin_page_title() ); ?></h1>
        <p><?php esc_html_e( 'Übersicht der letzten Spiele und Details', 'swiss-floorball-api' ); ?></p>
    </div>

    <?php if ( empty( $club_id ) ) : ?>
        <div class="notice notice-error">
            <p><?php esc_html_e( 'Bitte konfigurieren Sie zuerst eine Club-Nummer in den Einstellungen.', 'swiss-floorball-api' ); ?></p>
        </div>
    <?php elseif ( $match_id ) : ?>
        <?php
        // --- Single Match View ---
        
        // Back button
        $back_url = remove_query_arg( 'match_id' );
        echo '<p><a href="' . esc_url( $back_url ) . '" class="button button-primary">' . esc_html__( '← Zurück zur Übersicht', 'swiss-floorball-api' ) . '</a></p>';

        // Render Match Details
        Swiss_Floorball_API_Display::render_game_details_table( $match_id );
        
        // Show Game Events (Match Telegramm)
        echo '<div class="sfa-card sfa-card--spaced-top">';
        Swiss_Floorball_API_Display::render_game_events( $match_id, true );
        echo '</div>';
        ?>

    <?php else : ?>
        <?php
        // --- List View ---
        
        // Render Club Games List
        Swiss_Floorball_API_Display::render_club_games( $club_id, $current_season, true );
        ?>

        <hr class="sfa-divider">
        
        <div class="sfa-admin-header">
            <h1>🏑 <?php esc_html_e( 'Spiele pro Team', 'swiss-floorball-api' ); ?></h1>
            <p><?php esc_html_e( 'Übersicht der letzten Spiele und Details', 'swiss-floorball-api' ); ?></p>
        </div>
        
        <?php
        // Fetch teams for the club
        $teams_response = $client->fetch_data( 'clubs/' . $club_id . '/statistics' );
        
        if ( is_wp_error( $teams_response ) || ! isset( $teams_response['data']['regions'][0]['rows'] ) ) {
            echo '<div class="notice notice-warning"><p>' . esc_html__( 'Konnte Teams nicht laden.', 'swiss-floorball-api' ) . '</p></div>';
        } else {
            $teams = $teams_response['data']['regions'][0]['rows'];
            
            foreach ( $teams as $team ) {
                $team_id = $team['team_id'];
                $team_name = isset($team['cells'][0]['text'][0]) ? $team['cells'][0]['text'][0] : 'Team ' . $team_id;
                
                echo '<div class="sfa-card sfa-card--spaced-bottom">';
                echo '<h3>' . esc_html( $team_name ) . '</h3>';
                echo '<div class="sfa-table-container sfa-table-container-flat">';
                
                // Use the display class to render games for this team
                Swiss_Floorball_API_Display::render_team_games( $team_id, $current_season, true );
                
                echo '</div>';
                echo '</div>';
            }
        }
        ?>
    <?php endif; ?>
</div>
