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

if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( esc_html__( 'You do not have permission to access this page.', 'swiss-floorball-api' ) );
}
?>
<!-- This file should primarily consist of HTML with a little bit of PHP. -->
<div class="wrap sfa-admin-wrap" data-sfa-theme="<?php echo esc_attr( Swiss_Floorball_API_Display::get_theme() ); ?>">
	<div class="sfa-admin-header">
		<h1><?php Swiss_Floorball_Api_Icons::render( 'settings' ); ?> Einstellungen</h1>
		<p>Konfigurieren Sie Ihre Swiss Floorball API Verbindung</p>
	</div>

	<?php
	// Display cache cleared success message.
	// One-time notice stored by the nonce-protected cache clear handler.
	$swfl_deleted_count = get_transient( 'swfl_cache_cleared_' . get_current_user_id() );
	if ( false !== $swfl_deleted_count ) {
		delete_transient( 'swfl_cache_cleared_' . get_current_user_id() );
		echo '<div class="notice notice-success is-dismissible">';
		echo '<p><strong>' . Swiss_Floorball_Api_Icons::get( 'check' ) . ' Cache erfolgreich geleert!</strong> ' . esc_html( (int) $swfl_deleted_count ) . ' zwischengespeicherte Einträge wurden gelöscht.</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Icon markup sanitized via wp_kses() in Swiss_Floorball_Api_Icons::get().
		echo '</div>';
	}
	?>

	<div class="sfa-form-section">
		<h2>Plugin Einstellungen</h2>
		<?php settings_errors(); ?>
		<p class="sfa-helper-text">Geben Sie hier die erforderlichen Informationen für die Verbindung zur Swiss Floorball API ein.</p>
		
		<form method="POST" action="options.php" id="sfa-settings-form" class="sfa-autosave">
			<?php
				settings_fields( 'swfl_general_settings' );
				do_settings_sections( 'swfl_general_settings' );
			?>
			<output class="sfa-autosave-status" id="sfa-autosave-status" aria-live="polite"><?php esc_html_e( 'Changes are saved automatically.', 'swiss-floorball-api' ); ?></output>
			<noscript><?php submit_button( __( 'Save settings', 'swiss-floorball-api' ) ); ?></noscript>
		</form>
	</div>

	<div class="sfa-form-section">
		<h2><?php Swiss_Floorball_Api_Icons::render( 'delete' ); ?> Cache Verwaltung</h2>
		<p class="sfa-helper-text">Löschen Sie alle zwischengespeicherten API-Daten, um frische Daten vom Server zu laden.</p>
		<form method="POST" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="sfa-form--spaced-top">
			<input type="hidden" name="action" value="swfl_clear_cache">
			<?php wp_nonce_field( 'swfl_clear_cache_action', 'swfl_clear_cache_nonce' ); ?>
			<button type="submit" class="button button-secondary" onclick="return confirm('Möchten Sie wirklich den gesamten Cache leeren?');">
				<?php Swiss_Floorball_Api_Icons::render( 'delete' ); ?> Cache leeren
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
