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
		// Save the settings form automatically, so no save button is needed.
		const settingsForm = $('#sfa-settings-form');
		const statusEl = $('#sfa-autosave-status');
		let saveTimer = null;
		let saving = false;
		let pending = false;

		const setStatus = function (text) {
			statusEl.text(text);
		};

		const saveSettings = function () {
			if (saving) {
				pending = true;
				return;
			}
			saving = true;
			setStatus(swflAutosave.saving);
			$.post(settingsForm.attr('action'), settingsForm.serialize())
				.done(function () {
					setStatus(swflAutosave.saved);
				})
				.fail(function () {
					setStatus(swflAutosave.error);
				})
				.always(function () {
					saving = false;
					if (pending) {
						pending = false;
						saveSettings();
					}
				});
		};

		const queueSave = function () {
			clearTimeout(saveTimer);
			saveTimer = setTimeout(saveSettings, 600);
		};

		// Colour pickers for the seed and table colour settings.
		if ($.fn.wpColorPicker) {
			$('#swfl_seed_color, input[id^="swfl_table_"][id$="_color"]').wpColorPicker({
				change: queueSave,
				clear: function () {
					setTimeout(queueSave, 0);
				}
			});
		}

		if (settingsForm.length && 'undefined' !== typeof swflAutosave) {
			settingsForm.on('change input', 'input, select, textarea', queueSave);
			settingsForm.on('submit', function (event) {
				event.preventDefault();
				saveSettings();
			});
		}

		// Show the Partner API credentials only while the Partner API is selected.
		const apiSource = $('#swfl_api_source');
		if (apiSource.length) {
			const credentialRows = $('#swfl_api_key, #swfl_api_secret').closest('tr');
			const toggleCredentials = function () {
				credentialRows.toggle('partner' === apiSource.val());
			};
			apiSource.on('change', toggleCredentials);
			toggleCredentials();
		}

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
