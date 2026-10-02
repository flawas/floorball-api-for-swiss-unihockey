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

if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( esc_html__( 'You do not have permission to access this page.', 'swiss-floorball-api' ) );
}
?>

<!-- This file should primarily consist of HTML with a little bit of PHP. -->
<div class="wrap sfa-admin-wrap">
	<div class="sfa-admin-header">
		<h1><?php Swiss_Floorball_Api_Icons::render( 'hockey' ); ?> Swiss Floorball Plugin</h1>
		<p>Übersicht und Verwaltung Ihrer Swiss Floorball Daten</p>
	</div>

	<div class="sfa-cards-container">
		<div class="sfa-card">
			<h3><?php Swiss_Floorball_Api_Icons::render( 'settings' ); ?> Aktuelle Einstellungen</h3>
			<table class="sfa-settings-table">
				<tr>
					<td>API Key</td>
					<td>
					<?php
						$swfl_api_key = get_option( 'swissfloorball_api_key' );
					if ( $swfl_api_key ) {
						$swfl_masked_key = str_repeat( '*', max( 0, strlen( $swfl_api_key ) - 3 ) ) . substr( $swfl_api_key, -3 );
						echo esc_html( $swfl_masked_key );
					} else {
						echo '—';
					}
					?>
					</td>
				</tr>
				<tr>
					<td>Club ID</td>
					<td><?php echo esc_html( get_option( 'swissfloorball_club_number' ) ) ?: '—'; ?></td>
				</tr>
				<tr>
					<td>Club Name</td>
					<td><?php echo esc_html( get_option( 'swissfloorball_club_name' ) ) ?: '—'; ?></td>
				</tr>
				<tr>
					<td>Aktuelle Saison</td>
					<td><?php echo esc_html( Swiss_Floorball_API_Display::get_current_season() ); ?></td>
				</tr>
			</table>
		</div>

		<div class="sfa-card">
			<h3><?php Swiss_Floorball_Api_Icons::render( 'chart' ); ?> Schnellzugriff</h3>
			<p class="sfa-nav-description">Navigieren Sie zu den verschiedenen Bereichen:</p>
			<p class="sfa-nav-item">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=floorball-api-for-swiss-unihockey-settings' ) ); ?>" class="button">Einstellungen</a>
			</p>
			<p class="sfa-nav-item">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=floorball-api-for-swiss-unihockey-league' ) ); ?>" class="button">Ligen</a>
			</p>
			<p class="sfa-nav-item">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=floorball-api-for-swiss-unihockey-teams' ) ); ?>" class="button">Clubs</a>
			</p>
			<p class="sfa-nav-item">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=floorball-api-for-swiss-unihockey-seasons' ) ); ?>" class="button">Saisons</a>
			</p>
			<p class="sfa-nav-item">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=floorball-api-for-swiss-unihockey-shortcodes' ) ); ?>" class="button">Shortcodes</a>
			</p>
		</div>
	</div>

	<?php
	$swfl_club_number = get_option( 'swissfloorball_club_number' );
	$swfl_season      = Swiss_Floorball_API_Display::get_current_season();

	if ( $swfl_club_number && $swfl_season ) {
		echo '<div class="sfa-table-container">';
		Swiss_Floorball_API_Display::render_club_teams( $swfl_club_number );
		echo '</div>';

		echo '<div class="sfa-table-container">';
		Swiss_Floorball_API_Display::render_club_games( $swfl_club_number, $swfl_season );
		echo '</div>';
	} else {
		echo '<div class="sfa-card">';
		echo '<div class="sfa-empty-state">';
		echo '<div class="sfa-empty-state-icon">' . Swiss_Floorball_Api_Icons::get( 'warning' ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Sanitized via wp_kses() in Swiss_Floorball_Api_Icons::get().
		echo '<div class="sfa-empty-state-text">Bitte konfigurieren Sie zuerst die Einstellungen (Club ID und Saison).</div>';
		echo '</div>';
		echo '</div>';
	}
	?>
</div>