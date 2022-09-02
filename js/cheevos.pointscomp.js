var setupPointsCompDatepickers = function() {
	var startDate = new mw.widgets.DateInputWidget(
		{
			inputFormat: 'YYYY-MM-DD',
			displayFormat: 'YYYY-MM-DD',
			value: $('#start_time_datepicker').val(),
			dataInput: $('#start_time_datepicker').attr('data-input')
		}
	);
	$("#start_time_datepicker").replaceWith(startDate.$element);
	startDate.on('change', function() {
		$('#'+startDate.dataInput).val(new Date(startDate.getValue()).getTime() / 1000);
	});
}
setupPointsCompDatepickers();
