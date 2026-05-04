<?php

/**
 * Provide a admin area view for the plugin
 *
 * This file is used to markup the admin-facing aspects of the plugin.
 *
 * @link       https://flaviowaser.ch
 * @since      1.0.0
 *
 * @package    Swiss_Floorball_Api
 * @subpackage Swiss_Floorball_Api/admin/partials
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wrap sfa-admin-wrap">
    <div class="sfa-admin-header">
        <h1>📝 Shortcodes Übersicht</h1>
        <p>Hier finden Sie eine Übersicht über alle verfügbaren Shortcodes, deren Parameter und Anwendungsbeispiele.</p>
    </div>

    <div class="sfa-table-container">
        <h2>Verfügbare Shortcodes</h2>
        <table class="sfa-data-table">
            <thead>
                <tr>
                    <th class="sfa-col-shortcode">Shortcode</th>
                    <th class="sfa-col-description">Beschreibung</th>
                    <th class="sfa-col-params">Parameter</th>
                    <th class="sfa-col-example">Beispiel</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><code>[suh-club-teams]</code></td>
                    <td>Listet alle Teams des konfigurierten Vereins auf.</td>
                    <td>Keine (nutzt die Plugin-Einstellungen)</td>
                    <td><code>[suh-club-teams]</code></td>
                </tr>
                <tr>
                    <td><code>[suh-club-games]</code></td>
                    <td>Zeigt alle Spiele des konfigurierten Vereins für die aktuelle Saison.</td>
                    <td>Keine (nutzt die Plugin-Einstellungen)</td>
                    <td><code>[suh-club-games]</code></td>
                </tr>
                <tr>
                    <td><code>[suh-team-games]</code></td>
                    <td>Zeigt die Spiele eines spezifischen Teams.</td>
                    <td>
                        <strong>team_id</strong> (erforderlich): Die ID des Teams.<br>
                        <em>Zu finden auf der <a href="<?php echo esc_url( admin_url('admin.php?page=swiss-floorball-api-teams') ); ?>">Teams Übersicht</a> oder im <a href="<?php echo esc_url( admin_url('admin.php?page=swiss-floorball-api') ); ?>">Dashboard</a>.</em>
                    </td>
                    <td><code>[suh-team-games team_id="427892"]</code></td>
                </tr>
                <tr>
                    <td><code>[suh-clubs]</code></td>
                    <td>Listet alle Vereine auf.</td>
                    <td>Keine</td>
                    <td><code>[suh-clubs]</code></td>
                </tr>
                <tr>
                    <td><code>[suh-calendars]</code></td>
                    <td>Erstellt einen Kalender-Link (Webcal) für ein Team oder eine Liga.</td>
                    <td>
                        <strong>team_id</strong> (erforderlich für Team-Kalender): Die ID des Teams.<br>
                        <strong>club_id</strong>: Die ID des Vereins.<br>
                        <strong>season</strong>: Die Saison (z.B. 2023).<br>
                        <strong>league</strong>: Die Liga.<br>
                        <strong>game_class</strong>: Die Spielklasse.<br>
                        <strong>group</strong>: Die Gruppe.<br>
                        <em>Team IDs finden Sie auf der <a href="<?php echo esc_url( admin_url('admin.php?page=swiss-floorball-api-teams') ); ?>">Teams Übersicht</a>.</em>
                    </td>
                    <td>
                        <strong>Team Kalender:</strong><br>
                        <code>[suh-calendars team_id="427892"]</code><br><br>
                        
                        <strong>Vereins Kalender:</strong><br>
                        <code>[suh-calendars club_id="637"]</code>
                    </td>
                </tr>
                <tr>
                    <td><code>[suh-cups]</code></td>
                    <td>Listet alle Cup-Wettbewerbe auf.</td>
                    <td>Keine</td>
                    <td><code>[suh-cups]</code></td>
                </tr>
                <tr>
                    <td><code>[suh-groups]</code></td>
                    <td>Listet Gruppen basierend auf Saison, Liga und Spielklasse auf.</td>
                    <td>
                        <strong>season</strong>: Saison (Standard: aktuelle Saison aus Einstellungen).<br>
                        <strong>league</strong>: Liga ID.<br>
                        <strong>game_class</strong>: Spielklasse ID.
                    </td>
                    <td><code>[suh-groups league="1" game_class="11"]</code></td>
                </tr>
                <tr>
                    <td><code>[suh-teams]</code></td>
                    <td>Listet alle Teams auf.</td>
                    <td>Keine</td>
                    <td><code>[suh-teams]</code></td>
                </tr>
                <tr>
                    <td><code>[suh-rankings]</code></td>
                    <td>Zeigt die Rangliste für eine bestimmte Gruppe.</td>
                    <td>
                        <strong>season</strong>: Saison (Standard: aktuelle Saison).<br>
                        <strong>league</strong>: Liga ID.<br>
                        <strong>game_class</strong>: Spielklasse ID.<br>
                        <strong>group</strong>: Gruppen ID.
                    </td>
                    <td><code>[suh-rankings group="416947"]</code></td>
                </tr>
                <tr>
                    <td><code>[suh-player]</code></td>
                    <td>Zeigt Informationen zu einem spezifischen Spieler.</td>
                    <td>
                        <strong>player_id</strong> (erforderlich): Die ID des Spielers.
                    </td>
                    <td><code>[suh-player player_id="99999"]</code></td>
                </tr>
                <tr>
                    <td><code>[suh-national-players]</code></td>
                    <td>Listet Nationalspieler auf.</td>
                    <td>Keine</td>
                    <td><code>[suh-national-players]</code></td>
                </tr>
                <tr>
                    <td><code>[suh-topscorers]</code></td>
                    <td>Zeigt die Topscorer einer Liga/Gruppe.</td>
                    <td>
                        <strong>season</strong>: Saison (Standard: aktuelle Saison).<br>
                        <strong>league</strong>: Liga ID.<br>
                        <strong>game_class</strong>: Spielklasse ID.<br>
                        <strong>group</strong>: Gruppen ID.
                    </td>
                    <td><code>[suh-topscorers league="1"]</code></td>
                </tr>
                <tr>
                    <td><code>[suh-game-events]</code></td>
                    <td>Zeigt Ereignisse (Tore, Strafen) eines spezifischen Spiels.</td>
                    <td>
                        <strong>game_id</strong> (erforderlich): Die ID des Spiels.
                    </td>
                    <td><code>[suh-game-events game_id="1000"]</code></td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="sfa-table-container">
        <h2>⚙️ Backend-Only Funktionen</h2>
        <p>Die folgenden Funktionen sind nur im Backend verfügbar und haben keine öffentlichen Shortcodes. Sie werden hauptsächlich für administrative Zwecke verwendet.</p>
        
        <table class="sfa-data-table">
            <thead>
                <tr>
                    <th class="sfa-col-function">Funktion</th>
                    <th class="sfa-col-description-wide">Beschreibung</th>
                    <th class="sfa-col-usage">Verwendung</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><code>render_leagues()</code></td>
                    <td>Zeigt eine Liste aller verfügbaren Ligen an.</td>
                    <td>Nur Backend - Keine öffentliche Shortcode-Implementierung vorhanden.</td>
                </tr>
                <tr>
                    <td><code>render_seasons()</code></td>
                    <td>Zeigt eine Liste aller verfügbaren Saisons an.</td>
                    <td>Nur Backend - Keine öffentliche Shortcode-Implementierung vorhanden.</td>
                </tr>
                <tr>
                    <td><code>render_sessions()</code></td>
                    <td>Zeigt API-Session-Informationen an.</td>
                    <td>Nur Backend - Technische Funktion für API-Verwaltung.</td>
                </tr>
                <tr>
                    <td><code>render_club_games_callout()</code></td>
                    <td>Zeigt eine Callout-Box mit Vereinsspielen im Backend-Stil.</td>
                    <td>Nur Backend - Spezielle Darstellung für Admin-Dashboard.</td>
                </tr>
                <tr>
                    <td><code>render_club_games_table()</code></td>
                    <td>Zeigt eine Tabelle mit Vereinsspielen im Backend-Stil.</td>
                    <td>Nur Backend - Spezielle Darstellung für Admin-Dashboard.</td>
                </tr>
                <tr>
                    <td><code>get_teamdetails_image()</code></td>
                    <td>Ruft das Team-Logo/Bild von der API ab.</td>
                    <td>Hilfsfunktion - Wird intern von anderen Funktionen verwendet.</td>
                </tr>
                <tr>
                    <td><code>get_gamedetails()</code></td>
                    <td>Ruft detaillierte Informationen zu einem spezifischen Spiel ab.</td>
                    <td>Hilfsfunktion - Wird intern von anderen Funktionen verwendet.</td>
                </tr>
            </tbody>
        </table>

        <div class="sfa-info-box">
            <h3 class="sfa-info-box__title">ℹ️ Hinweis</h3>
            <p style="margin-bottom: 0;">
                Wenn Sie eine dieser Backend-Funktionen als öffentlichen Shortcode benötigen, können Sie dies als Feature-Request einreichen. 
                Die Funktionen sind bereits in der API-Client-Klasse implementiert und könnten bei Bedarf als Shortcodes verfügbar gemacht werden.
            </p>
        </div>
    </div>
</div>
