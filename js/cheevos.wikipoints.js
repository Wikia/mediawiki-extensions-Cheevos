$(document).ready(function() {
	$('.add_level').click(function() {
		var fieldset = $(this).parent().parent();
		var delImg = $('<img>').attr('src', mw.config.get('wgScriptPath')+'/extensions/Cheevos/images/delete.png').addClass('delete_level');
		var levelLids = $('<input>').attr('name', 'lid[]').attr('type', 'hidden');
		var levelPoints = $('<input>').attr('name', 'points[]').attr('type', 'text');
		var levelTexts = $('<input>').attr('name', 'text[]').attr('type', 'text');
		var imageIcons = $('<input>').attr('name', 'image_icon[]').attr('type', 'text');
		var imageLarges = $('<input>').attr('name', 'image_large[]').attr('type', 'text');
		var div = $('<div>').addClass('level').append(levelLids).append(levelPoints).append(levelTexts).append(imageIcons).append(imageLarges).append(delImg);
		fieldset.append(div);

		setupDeleteValue();
	});

	function setupDeleteValue() {
		$('.delete_level').click(function() {
			$(this).parent().remove();
		});
	}
	setupDeleteValue();

	var setupMultiplierDatepickers = function() {
		$("input#begins_datepicker, input#expires_datepicker").datepicker(
			{
				dateFormat: "yy-mm-dd",
				constrainInput: true,
				onSelect: function(dateText) {
					var epochInput = '#'+$(this).attr('data-input');
					$(epochInput).val(epochDate(this));
				}
			}
		);
	}
	setupMultiplierDatepickers();

	var epochDate = function(dateField) {
		var time = $(dateField).datepicker("getDate");
		var offset = time.getTimezoneOffset() * 60;
		var epoch = time.getTime() / 1000 - offset;

		return epoch;
	}

	$('input#begins, input#expires').change(function() {
		var currentVal = $(this).val();
		if (currentVal > 0) {
			var existEpochDate = new Date(currentVal * 1000);
			var picker = '#'+$(this).attr('id')+'_datepicker';
			$(picker).datepicker("setDate", existEpochDate);
		}
	}).change();

	if ($('input#begins').val()) {
		$('input#begins_display').val(new Date($('input#begins').val() * 1000).toString());
	}
	if ($('input#expires').val()) {
		$('input#expires_display').val(new Date($('input#expires').val() * 1000).toString());
	}

	$('#multiplier_form #checkAll').click(function() {
		$('#multiplier_form input:checkbox').attr('checked', 'checked');
	});

	$('#multiplier_form #uncheckAll').click(function() {
		$('#multiplier_form input:checkbox').removeAttr('checked');
	});
});