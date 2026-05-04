<?php

/**
 * Provide a admin area view for the plugin
 *
 * This file is used to markup the admin-facing aspects of the plugin.
 *
 * @link       plugin_name.com/team
 * @since      1.0.0
 *
 * @package    PluginName
 * @subpackage PluginName/admin/partials
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!-- This file should primarily consist of HTML with a little bit of PHP. -->
<div class="wrap sfa-admin-wrap">
	<div class="sfa-admin-header">
		<h1>⚙️ Einstellungen</h1>
		<p>Konfigurieren Sie Ihre Swiss Floorball API Verbindung</p>
	</div>

	<?php
	// Display cache cleared success message
	if ( isset( $_GET['cache_cleared'] ) && $_GET['cache_cleared'] == '1' ) {
		$deleted_count = isset( $_GET['deleted_count'] ) ? intval( $_GET['deleted_count'] ) : 0;
		echo '<div class="notice notice-success is-dismissible">';
		echo '<p><strong>✓ Cache erfolgreich geleert!</strong> ' . $deleted_count . ' zwischengespeicherte Einträge wurden gelöscht.</p>';
		echo '</div>';
	}
	?>

	<div class="sfa-form-section">
		<h2>Plugin Einstellungen</h2>
		<?php settings_errors(); ?>
		<p class="sfa-helper-text">Geben Sie hier die erforderlichen Informationen für die Verbindung zur Swiss Floorball API ein.</p>
		
		<form method="POST" action="options.php">  
			<?php 
				settings_fields( 'sfa_general_settings' );
				do_settings_sections( 'sfa_general_settings' ); 
			?>             
			<?php submit_button('Einstellungen speichern'); ?>  
		</form>
	</div>

	<div class="sfa-form-section">
		<h2>🗑️ Cache Verwaltung</h2>
		<p class="sfa-helper-text">Löschen Sie alle zwischengespeicherten API-Daten, um frische Daten vom Server zu laden.</p>
		<form method="POST" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" class="sfa-form--spaced-top">
			<input type="hidden" name="action" value="sfa_clear_cache">
			<?php wp_nonce_field( 'sfa_clear_cache_action', 'sfa_clear_cache_nonce' ); ?>
			<button type="submit" class="button button-secondary" onclick="return confirm('Möchten Sie wirklich den gesamten Cache leeren?');">
				🗑️ Cache leeren
			</button>
		</form>
	</div>

	<div class="sfa-card">
		<h3>ℹ️ Hilfe</h3>
		<p><strong>Club Number:</strong> Die eindeutige ID Ihres Clubs bei Swiss Floorball</p>
		<p><strong>Club Name:</strong> Der offizielle Name Ihres Clubs (wird automatisch geladen)</p>
		<p><strong>Aktuelle Saison:</strong> Das Jahr der aktuellen Saison (z.B. 2024)</p>
	</div>
</div>