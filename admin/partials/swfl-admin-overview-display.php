<?php
/**
 * Provide the club overview admin page (teams and games of the club)
 *
 * Moved here from the dashboard so the dashboard stays a quick entry point.
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
$swfl_season      = SWFL_Display::get_current_season();
?>
<div class="wrap sfa-admin-wrap" data-sfa-theme="<?php echo esc_attr( SWFL_Theme::get_theme() ); ?>">
	<div class="sfa-admin-header">
		<h1><?php SWFL_Icons::render( 'chart' ); ?> <?php esc_html_e( 'Club-Übersicht', 'swiss-floorball-api' ); ?></h1>
		<p><?php esc_html_e( 'Teams und Spiele des konfigurierten Clubs', 'swiss-floorball-api' ); ?></p>
	</div>

	<?php if ( $swfl_club_number && $swfl_season ) : ?>
		<div class="sfa-table-container">
			<?php SWFL_Display::render_club_teams( $swfl_club_number ); ?>
		</div>
		<div class="sfa-table-container">
			<?php SWFL_Display::render_club_games( $swfl_club_number, $swfl_season ); ?>
		</div>
	<?php else : ?>
		<div class="sfa-callout">
			<?php SWFL_Icons::render( 'warning' ); ?>
			<span><?php esc_html_e( 'Bitte konfigurieren Sie zuerst die Einstellungen (Club ID und Saison).', 'swiss-floorball-api' ); ?></span>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=swiss-floorball-api-settings' ) ); ?>" class="button button-primary"><?php esc_html_e( 'Einstellungen', 'swiss-floorball-api' ); ?></a>
		</div>
	<?php endif; ?>
</div>
