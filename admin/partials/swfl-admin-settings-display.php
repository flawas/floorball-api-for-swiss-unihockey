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
<div class="wrap sfa-admin-wrap" data-sfa-theme="<?php echo esc_attr( SWFL_Theme::get_theme() ); ?>">
	<div class="sfa-admin-header">
		<h1><?php SWFL_Icons::render( 'settings' ); ?> Einstellungen</h1>
		<p>Konfigurieren Sie Ihre Swiss Floorball API Verbindung</p>
	</div>

	<?php
	// Display cache cleared success message.
	// One-time notice stored by the nonce-protected cache clear handler.
	$swfl_deleted_count = get_transient( 'swfl_cache_cleared_' . get_current_user_id() );
	if ( false !== $swfl_deleted_count ) {
		delete_transient( 'swfl_cache_cleared_' . get_current_user_id() );
		echo '<div class="notice notice-success is-dismissible">';
		echo '<p><strong>' . wp_kses( SWFL_Icons::get( 'check' ), SWFL_Icons::allowed_html() ) . ' Cache erfolgreich geleert!</strong> ' . esc_html( (int) $swfl_deleted_count ) . ' zwischengespeicherte Einträge wurden gelöscht.</p>';
		echo '</div>';
	}
	?>

	<?php
	// Shown for 24 hours after the site-wide upstream budget ran out (see SWFL_Upstream_Budget), unless the limit was changed since.
	$swfl_budget_notice = get_transient( SWFL_Upstream_Budget::NOTICE_TRANSIENT );
	if ( is_array( $swfl_budget_notice ) && isset( $swfl_budget_notice['time'], $swfl_budget_notice['limit'] ) && SWFL_Upstream_Budget::get_limit() === (int) $swfl_budget_notice['limit'] ) {
		echo '<div class="notice notice-warning"><p>' . esc_html(
			sprintf(
				/* translators: 1: how long ago, e.g. "5 minutes", 2: requests per minute. */
				__( 'The site-wide limit of %2$d upstream requests per minute was reached %1$s ago. Visitors received cached data or a "temporarily unavailable" answer. If this was normal traffic, raise "Upstream requests per minute" below.', 'swiss-floorball-api' ),
				human_time_diff( (int) $swfl_budget_notice['time'] ),
				(int) $swfl_budget_notice['limit']
			)
		) . '</p></div>';
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

	<div class="sfa-form-section" id="sfa-rate-limited">
		<h2><?php esc_html_e( 'Blocked visitors', 'swiss-floorball-api' ); ?></h2>
		<p class="sfa-helper-text"><?php esc_html_e( 'Visitors the REST rate limit blocked during the last hour (IPv4 addresses, IPv6 /64 prefixes). A block lasts until the end of the minute. If many visitors share one address (a reverse proxy, CDN or company network), enter the proxy under "Trusted proxies" or raise "REST requests per minute and visitor". Entries are removed an hour after their last block.', 'swiss-floorball-api' ); ?></p>
		<?php
		$swfl_blocked = SWFL_Rate_Limit_Log::get_entries();
		$swfl_now     = time();
		if ( empty( $swfl_blocked ) ) {
			echo '<p>' . esc_html__( 'No visitor was blocked during the last hour.', 'swiss-floorball-api' ) . '</p>';
		} else {
			?>
			<table class="sfa-data-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Address', 'swiss-floorball-api' ); ?></th>
						<th><?php esc_html_e( 'Status', 'swiss-floorball-api' ); ?></th>
						<th><?php esc_html_e( 'Blocked minutes', 'swiss-floorball-api' ); ?></th>
						<th><?php esc_html_e( 'First block', 'swiss-floorball-api' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $swfl_blocked as $swfl_bucket => $swfl_entry ) : ?>
						<?php $swfl_address = SWFL_Rate_Limit_Log::format_bucket( $swfl_bucket ); ?>
						<tr>
							<td><code><?php echo esc_html( '' !== $swfl_address ? $swfl_address : __( 'unknown address', 'swiss-floorball-api' ) ); ?></code></td>
							<td>
								<?php
								if ( $swfl_entry['until'] > $swfl_now ) {
									/* translators: %s: time left, e.g. "42 seconds". */
									echo '<strong>' . esc_html( sprintf( __( 'Blocked, %s left', 'swiss-floorball-api' ), human_time_diff( $swfl_now, $swfl_entry['until'] ) ) ) . '</strong>';
								} else {
									/* translators: %s: how long ago, e.g. "12 minutes". */
									echo esc_html( sprintf( __( 'Released %s ago', 'swiss-floorball-api' ), human_time_diff( $swfl_entry['until'], $swfl_now ) ) );
								}
								?>
							</td>
							<td><?php echo esc_html( (string) $swfl_entry['minutes'] ); ?></td>
							<td>
								<?php
								/* translators: %s: how long ago, e.g. "40 minutes". */
								echo esc_html( sprintf( __( '%s ago', 'swiss-floorball-api' ), human_time_diff( $swfl_entry['first'], $swfl_now ) ) );
								?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php
		}
		?>
	</div>

	<div class="sfa-form-section">
		<h2><?php SWFL_Icons::render( 'delete' ); ?> Cache Verwaltung</h2>
		<p class="sfa-helper-text">Löschen Sie alle zwischengespeicherten API-Daten, um frische Daten vom Server zu laden.</p>
		<form method="POST" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="sfa-form--spaced-top">
			<input type="hidden" name="action" value="swfl_clear_cache">
			<?php wp_nonce_field( 'swfl_clear_cache_action', 'swfl_clear_cache_nonce' ); ?>
			<button type="submit" class="button button-secondary" onclick="return confirm('Möchten Sie wirklich den gesamten Cache leeren?');">
				<?php SWFL_Icons::render( 'delete' ); ?> Cache leeren
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
