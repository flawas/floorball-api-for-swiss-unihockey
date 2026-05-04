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
		<h1>👥 Clubs</h1>
		<p>Übersicht aller verfügbaren Clubs</p>
	</div>

	<div class="sfa-search-container">
		<input type="text" id="sfa-team-search" class="sfa-search-input" placeholder="🔍 Club suchen (Name oder ID)..." aria-label="<?php esc_attr_e( 'Club suchen', 'floorball-api-for-swiss-unihockey' ); ?>">
	</div>

	<div class="sfa-table-container">
		<?php Swiss_Floorball_API_Display::render_clubs();?>
	</div>
</div>