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
		<h1><?php SWFL_Icons::render( 'group' ); ?> <?php esc_html_e( 'Clubs', 'swiss-floorball-api' ); ?></h1>
		<p><?php esc_html_e( 'All available clubs', 'swiss-floorball-api' ); ?></p>
	</div>

	<div class="sfa-search-container">
		<?php SWFL_Icons::render( 'search' ); ?>
		<input type="text" id="sfa-team-search" class="sfa-search-input" placeholder="<?php esc_attr_e( 'Search clubs (name or ID)…', 'swiss-floorball-api' ); ?>" aria-label="<?php esc_attr_e( 'Search clubs', 'swiss-floorball-api' ); ?>">
	</div>

	<div class="sfa-table-container">
		<?php SWFL_Display::render_clubs(); ?>
	</div>
</div>
