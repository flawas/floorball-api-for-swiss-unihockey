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

<!-- This file should primarily consist of HTML with a little bit of PHP. -->
<div class="wrap sfa-admin-wrap">
	<div class="sfa-admin-header">
		<h1>🏑 Swiss Unihockey Plugin</h1>
		<p>Übersicht und Verwaltung Ihrer Swiss Unihockey Daten</p>
	</div>

	<div class="sfa-cards-container">
		<div class="sfa-card">
			<h3>⚙️ Aktuelle Einstellungen</h3>
			<table class="sfa-settings-table">
				<tr>
					<td>API Key</td>
					<td><?php 
						$api_key = get_option('swissfloorball_api_key');
						if ( $api_key ) {
							$masked_key = str_repeat('*', max(0, strlen($api_key) - 3)) . substr($api_key, -3);
							echo esc_html( $masked_key );
						} else {
							echo '—';
						}
					?></td>
				</tr>
				<tr>
					<td>Club ID</td>
					<td><?php echo esc_html( get_option('swissfloorball_club_number') ) ?: '—'; ?></td>
				</tr>
				<tr>
					<td>Club Name</td>
					<td><?php echo esc_html( get_option('swissfloorball_club_name') ) ?: '—'; ?></td>
				</tr>
				<tr>
					<td>Aktuelle Saison</td>
					<td><?php echo esc_html( get_option('swissfloorball_actual_season') ) ?: '—'; ?></td>
				</tr>
			</table>
		</div>

		<div class="sfa-card">
			<h3>📊 Schnellzugriff</h3>
			<p class="sfa-nav-description">Navigieren Sie zu den verschiedenen Bereichen:</p>
			<p class="sfa-nav-item">
				<a href="<?php echo esc_url( admin_url('admin.php?page=swiss-floorball-api-settings') ); ?>" class="button">Einstellungen</a>
			</p>
			<p class="sfa-nav-item">
				<a href="<?php echo esc_url( admin_url('admin.php?page=swiss-floorball-api-league') ); ?>" class="button">Ligen</a>
			</p>
			<p class="sfa-nav-item">
				<a href="<?php echo esc_url( admin_url('admin.php?page=swiss-floorball-api-teams') ); ?>" class="button">Clubs</a>
			</p>
			<p class="sfa-nav-item">
				<a href="<?php echo esc_url( admin_url('admin.php?page=swiss-floorball-api-seasons') ); ?>" class="button">Saisons</a>
			</p>
			<p class="sfa-nav-item">
				<a href="<?php echo esc_url( admin_url('admin.php?page=swiss-floorball-api-shortcodes') ); ?>" class="button">Shortcodes</a>
			</p>
		</div>
	</div>

	<?php
	$club_number = get_option('swissfloorball_club_number');
	$season = get_option('swissfloorball_actual_season');
	
	if ($club_number && $season) {
		echo '<div class="sfa-table-container">';
		Swiss_Floorball_API_Display::render_club_teams($club_number);
		echo '</div>';
		
		echo '<div class="sfa-table-container">';
		Swiss_Floorball_API_Display::render_club_games($club_number, $season);
		echo '</div>';
	} else {
		echo '<div class="sfa-card">';
		echo '<div class="sfa-empty-state">';
		echo '<div class="sfa-empty-state-icon">⚠️</div>';
		echo '<div class="sfa-empty-state-text">Bitte konfigurieren Sie zuerst die Einstellungen (Club ID und Saison).</div>';
		echo '</div>';
		echo '</div>';
	}
	?>
</div>