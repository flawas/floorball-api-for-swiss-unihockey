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
		<h1><?php Swiss_Floorball_Api_Icons::render( 'trophy' ); ?> Ligen</h1>
		<p>Übersicht aller verfügbaren Ligen</p>
	</div>

	<div class="sfa-table-container">
		<?php Swiss_Floorball_API_Display::render_leagues(); ?>
	</div>
</div>