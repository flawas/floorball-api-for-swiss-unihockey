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
		<h1>🏆 Ligen</h1>
		<p>Übersicht aller verfügbaren Ligen</p>
	</div>

	<div class="sfa-table-container">
		<?php Swiss_Floorball_API_Display::render_leagues(); ?>
	</div>
</div>