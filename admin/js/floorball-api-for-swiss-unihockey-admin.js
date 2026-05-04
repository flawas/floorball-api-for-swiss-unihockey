(function ($) {
	'use strict';

	/**
	 * All of the code for your admin-facing JavaScript source
	 * should reside in this file.
	 *
	 * Note: It has been assumed you will write jQuery code here, so the
	 * $ function reference has been prepared for usage within the scope
	 * of this function.
	 *
	 * This enables you to define handlers, for when the DOM is ready:
	 *
	 * $(function() {
	 *
	 * });
	 *
	 * When the window is loaded:
	 *
	 * $( window ).load(function() {
	 *
	 * });
	 *
	 * ...and/or other possibilities.
	 *
	 * Ideally, it is not considered best practise to attach more than a
	 * single DOM-ready or window-load handler for a particular page.
	 * Although scripts in the WordPress core, Plugins and Themes may be
	 * practising this, we should strive to set a better example in our own work.
	 */

	$(document).ready(function () {
		// Team search functionality
		const searchInput = $('#sfa-team-search');
		const dataTable = $('.sfa-data-table');

		if (searchInput.length && dataTable.length) {
			searchInput.on('keyup', function () {
				const searchTerm = $(this).val().toLowerCase();

				dataTable.find('tbody tr').each(function () {
					const $row = $(this);
					const teamName = $row.find('td:first').text().toLowerCase();
					const teamId = $row.find('td:last').text().toLowerCase();

					if (teamName.includes(searchTerm) || teamId.includes(searchTerm)) {
						$row.show();
					} else {
						$row.hide();
					}
				});
			});
		}
	});

})(jQuery);
