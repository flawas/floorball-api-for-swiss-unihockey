<?php
/**
 * Provide a admin area view for the plugin
 *
 * This file is used to markup the admin-facing aspects of the plugin.
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
?>

<?php
$swfl_club_number = get_option( 'swfl_club_number' );
$swfl_club_name   = get_option( 'swfl_club_name' );
$swfl_season      = SWFL_Display::get_current_season();
$swfl_api_source  = SWFL_Client::get_source();
$swfl_configured  = $swfl_club_number && $swfl_season;

$swfl_stats = array(
	array( __( 'Club', 'swiss-floorball-api' ), $swfl_club_name ? $swfl_club_name : '—' ),
	array( __( 'Club ID', 'swiss-floorball-api' ), $swfl_club_number ? $swfl_club_number : '—' ),
	array( __( 'Saison', 'swiss-floorball-api' ), $swfl_season ? $swfl_season : '—' ),
	array( __( 'API-Quelle', 'swiss-floorball-api' ), $swfl_api_source ),
);

$swfl_tiles = array(
	array( 'chart', __( 'Club-Übersicht', 'swiss-floorball-api' ), __( 'Teams und alle Spiele des Clubs', 'swiss-floorball-api' ), 'overview' ),
	array( 'hockey', __( 'Spiele', 'swiss-floorball-api' ), __( 'Spiele pro Team mit Details und Telegramm', 'swiss-floorball-api' ), 'matches' ),
	array( 'trophy', __( 'Ligen', 'swiss-floorball-api' ), __( 'Liga-, Game-Class- und Gruppen-IDs nachschlagen', 'swiss-floorball-api' ), 'league' ),
	array( 'group', __( 'Clubs', 'swiss-floorball-api' ), __( 'Club-IDs aller Vereine', 'swiss-floorball-api' ), 'teams' ),
	array( 'calendar', __( 'Saisons', 'swiss-floorball-api' ), __( 'Verfügbare Saisons', 'swiss-floorball-api' ), 'seasons' ),
	array( 'description', __( 'Shortcodes', 'swiss-floorball-api' ), __( 'Alle Shortcodes mit Attributen', 'swiss-floorball-api' ), 'shortcodes' ),
	array( 'settings', __( 'Einstellungen', 'swiss-floorball-api' ), __( 'API, Club, Design und Cache', 'swiss-floorball-api' ), 'settings' ),
);
?>
<div class="wrap sfa-admin-wrap" data-sfa-theme="<?php echo esc_attr( SWFL_Theme::get_theme() ); ?>">
	<div class="sfa-admin-header">
		<h1><?php SWFL_Icons::render( 'hockey' ); ?> Swiss Floorball</h1>
		<p><?php esc_html_e( 'Übersicht und Verwaltung Ihrer Swiss Floorball Daten', 'swiss-floorball-api' ); ?></p>
	</div>

	<?php if ( ! $swfl_configured ) : ?>
		<div class="sfa-callout">
			<?php SWFL_Icons::render( 'warning' ); ?>
			<span><?php esc_html_e( 'Bitte konfigurieren Sie zuerst die Einstellungen (Club ID und Saison).', 'swiss-floorball-api' ); ?></span>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=swiss-floorball-api-settings' ) ); ?>" class="button button-primary"><?php esc_html_e( 'Einstellungen', 'swiss-floorball-api' ); ?></a>
		</div>
	<?php endif; ?>

	<div class="sfa-stat-grid">
		<?php foreach ( $swfl_stats as $swfl_stat ) : ?>
			<div class="sfa-stat-tile">
				<span class="sfa-stat-tile__label"><?php echo esc_html( $swfl_stat[0] ); ?></span>
				<span class="sfa-stat-tile__value"><?php echo esc_html( $swfl_stat[1] ); ?></span>
			</div>
		<?php endforeach; ?>
	</div>

	<div class="sfa-tile-grid">
		<?php foreach ( $swfl_tiles as $swfl_tile ) : ?>
			<a class="sfa-tile" href="<?php echo esc_url( admin_url( 'admin.php?page=swiss-floorball-api-' . $swfl_tile[3] ) ); ?>">
				<span class="sfa-tile__icon"><?php SWFL_Icons::render( $swfl_tile[0] ); ?></span>
				<span class="sfa-tile__title"><?php echo esc_html( $swfl_tile[1] ); ?></span>
				<span class="sfa-tile__text"><?php echo esc_html( $swfl_tile[2] ); ?></span>
			</a>
		<?php endforeach; ?>
	</div>
</div>
