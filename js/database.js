/*
 * Auto-refresh of the locks and processes tables on database.php.
 *
 * Loaded once per full page load. Each time such a page is shown
 * ("ppa:load", see js/app.js) the refresh starts; it stops when the page is
 * left ("ppa:unload"), so no timer keeps polling in the background.
 */
(function() {

	var timeid = null, query = null, controlLink = null, errmsg = null, loading = null;

	function refreshTable() {
		if (Database.ajax_time_refresh > 0) {
			loading.show();
			query = $.ajax({
				type: 'GET',
				dataType: 'html',
				data: {server: Database.server, database: Database.dbname, action: Database.action},
				url: 'database.php',
				cache: false,
				contentType: 'application/x-www-form-urlencoded',
				success: function(html) {
					$('#data_block').html(html);
					timeid = window.setTimeout(refreshTable, Database.ajax_time_refresh);
				},
				error: function(xhr, status) {
					if (status === 'abort') return;
					controlLink.click();
					errmsg.show();
				},
				complete: function () {
					if (loading) loading.hide();
				}
			});
		}
	}

	function stop() {
		window.clearTimeout(timeid);
		timeid = null;
		if (query) query.abort();
		query = null;
	}

	function init() {
		var link = $('#control');
		if (!link.length || link.data('ppa-init') || typeof Database === 'undefined') return;
		link.data('ppa-init', true);
		stop();

		controlLink = link;
		errmsg = $('<p class="errmsg">'+Database.errmsg+'</p>')
			.insertBefore(controlLink)
			.hide();
		loading = $('<img class="loading" alt="[loading]" src="'+ Database.load_icon +'" />')
			.insertAfter(controlLink)
			.hide();

		controlLink.on('click', function() {
			if (!timeid) {
				$(errmsg).hide();
				timeid = window.setTimeout(refreshTable, Database.ajax_time_refresh);
				controlLink.html('<img src="'+ Database.str_stop.icon +'" alt="" />&nbsp;'
					+ Database.str_stop.text + '&nbsp;&nbsp;&nbsp;'
				);
			} else {
				$(errmsg).hide();
				$(loading).hide();
				stop();
				controlLink.html('<img src="'+ Database.str_start.icon +'" alt="" />&nbsp;'
					+ Database.str_start.text
				);
			}
			return false;  /* disable event propagation */
		});

		/* preload images */
		$('#control img').hide()
			.attr('src', Database.str_start.icon)
			.attr('src', Database.str_stop.icon)
			.show();

		/* start refreshing */
		controlLink.trigger('click');
	}

	$(document).on('ppa:load', init);
	$(document).on('ppa:unload', function () {
		stop();
		controlLink = errmsg = loading = null;
	});
	$(init);
})();
