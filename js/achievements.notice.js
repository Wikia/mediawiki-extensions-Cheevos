(function(mw, $) {
	var position = 0;
	var noticesOuterHeight = $("#p-achievement-notices").outerHeight();
	var hashes = [];
	var displayed = [];
	$('#p-achievement-notices .p-achievement-notice').each(function() {
		hashes.push($(this).data('hash'));
		window.hasAchievementNotices = true;
		var noticeLeft = $(this).css('left');
		position--;
		$(this).delay(100 + (position * 250)).animate({
			left: "0px"
		}, 600).delay(5000 + (position * 250)).animate({
			left: noticeLeft,
			top: noticesOuterHeight
		}, 600);
	});
	$('#p-achievement-notices .p-achievement-notice').promise().done(function() {
		$(this).hide();
		displayed.push($(this).data('hash'));
		if (displayed.length == hashes.length || $("#p-achievement-notices").height() < 10) {
			// hide the main container so it doesn't click block
			$("#p-achievement-notices").hide();
		}
		if (window.hasAchievementNotices == true && !window.achievementsAcknowledged) {
			var api = new mw.Api();

			api.post(
				{
					action: 'achievements',
					do: 'acknowledgeAwards',
					format: 'json',
					formatversion: 2,
					hashes: JSON.stringify(hashes)
				}
			).done(
				function(result) {
					if (result.success == true) {
						window.achievementsAcknowledged = true;
					}
				}
			);
		}
	});
}(mediaWiki, jQuery));