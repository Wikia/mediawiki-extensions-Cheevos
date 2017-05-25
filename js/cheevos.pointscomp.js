var setupPointsCompDatepickers = function() {
	$("input#start_time_datepicker, input#end_time_datepicker").datepicker(
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
setupPointsCompDatepickers();

var epochDate = function(dateField) {
	var time = $(dateField).datepicker("getDate");
	var offset = time.getTimezoneOffset() * 60;
	var epoch = time.getTime() / 1000 - offset;

	return epoch;
}

$('input#start_time, input#end_time').change(function() {
	var currentVal = $(this).val();
	if (currentVal > 0) {
		var existEpochDate = new Date(currentVal * 1000);
		var picker = '#'+$(this).attr('id')+'_datepicker';
		$(picker).datepicker("setDate", existEpochDate);
	}
}).change();