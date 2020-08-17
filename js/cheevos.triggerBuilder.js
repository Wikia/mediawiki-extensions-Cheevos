(function(mw, $) {
	/**
	 * @class mw.TriggerBuilder
	 *
	 * @constructor
	 * @param	element	DOM element to anchor to.
	 * @throws	Error	Thrown when the element is not found.
	 */
	function TriggerBuilder(container) {
		if ($(container).length < 1) {
			throw new Error('Invalid DOM element passed to TriggerBuilder.');
		}

		this.container = container;

		this.setup();

		return this;
	}

	/* Private members */

	//var a, b, c, etcetera...
	var
	container = null;

	/* Public members */
	TriggerBuilder.prototype = {
		triggers: {},
		buttons: {},
		editor: null,
		currentTrigger: null,
		triggerList: null,

		setup: function() {
			var self = this;

			this.buildEditor();

			$(this.container).append(this.addTrigger());

			this.triggers = JSON.parse($("input[name='triggers']", self.container).val());
			this.updateTriggerList();
			if (this.triggers instanceof Array && this.triggers.length === 0) {
				this.triggers = {};
			}
			this.triggerList = $('<ul>').addClass('trigger_list');
			$(this.container).append(this.triggerList);
			this.updateTriggerList();
		},

		showEditor: function(position) {
			$("body").append($(this.editor));
			$(this.editor).css('top', position.top + 'px');
			$(this.editor).css('left', position.left + 'px');
			$(this.editor).fadeIn();
			$(".hook", this.editor).focus();
		},

		hideEditor: function() {
			$(this.editor).fadeOut();
			$(this.editor).detach();
		},

		buildEditor: function() {
			var self = this;
			var hookCategories = JSON.parse($('#hooks').val());

			var select = $("<select>").addClass('hook').attr('name', 'hook');
			$(select).append($('<option>').val('0').html(mw.message('choose_existing_hook').escaped()));

			$.each(hookCategories, function(category, hooks) {
				var optgroup = $("<optgroup>").attr('label', category);
				$.each(hooks, function(index, hook) {
					$(optgroup).append($("<option>").val(hook).html(hook));
				});
				$(select).append(optgroup);
			});

			var editor = $("<div class='trigger_edit popupWrapper'><div class='popupInner'><div class='trigger_buttons'></div></div></div>");
			$(".trigger_buttons", editor).before(select);
			$(".trigger_buttons", editor).append(this.addCondition()).append(this.saveTrigger()).append(this.cancelTrigger());

			this.editor = editor;
		},

		addTrigger: function() {
			var self = this;

			var addTrigger = $("<input>").addClass('add_trigger').attr('type', 'button').val(mw.message('add_trigger').escaped());
			$(addTrigger).on("click", function(event) {
				var position = $(this).offset();

				self.currentTrigger = null;

				$(".hook", self.editor).val('');
				$(".hook_select option[value='0']", self.editor).prop('selected', true);
				$(".hook_select", self.editor).change();

				$('.condition', self.editor).remove();

				self.showEditor(position);
			});

			return addTrigger;
		},

		saveTrigger: function() {
			var self = this;

			var saveTrigger = $("<input>").addClass('save_trigger').attr('type', 'button').val(mw.message('save_trigger').escaped());
			$(saveTrigger).on('click', function() {
				var dataTrigger = $("select[name='hook'] option:selected").val();

				if (dataTrigger != self.currentTrigger) {
					delete self.triggers[self.currentTrigger];
				}

				var conditions = {};
				conditions.conditions = {};
				$('.condition', self.editor).each(function() {
					var argumentNumber = $('.argumentNumber', this).val();
					var comparison = $('.comparison option:selected', this).val();
					var checkValue = $('.checkValue', this).val();

					conditions.conditions[argumentNumber] = [comparison, checkValue];
				});

				self.triggers[dataTrigger] = conditions;

				self.updateTriggerList();
				self.hideEditor();
			});

			return saveTrigger;
		},

		deleteTrigger: function() {
			var self = this;

			var deleteTrigger = $('<img>').attr('src', mw.config.get('wgExtensionAssetsPath')+'/Achievements/images/delete.png').attr('title', mw.message('delete_trigger').escaped()).addClass('delete_trigger');

			$(deleteTrigger).on("click", function(event) {
				var triggerLi = $(this).parent();
				delete self.triggers[triggerLi.attr('data-trigger')];
				self.updateTriggerList();
				$(triggerLi).remove();
			});

			return deleteTrigger;
		},

		cancelTrigger: function() {
			var self = this;

			var cancelTrigger = $("<input>").addClass('cancel_trigger').attr('type', 'button').val(mw.message('cancel_trigger').escaped());
			$(cancelTrigger).on('click', function() {
				self.hideEditor();
			});

			return cancelTrigger;
		},

		addCondition: function() {
			var self = this;

			var addCondition = $("<input>").addClass('add_condition').attr('type', 'button').val(mw.message('add_condition').escaped());
			$(addCondition).on('click', function() {
				$('.trigger_buttons', self.editor).before(self.conditionBlock());
			});

			return addCondition;
		},

		deleteCondition: function() {
			var deleteCondition = $('<img>').attr('src', mw.config.get('wgExtensionAssetsPath')+'/Achievements/images/delete.png').attr('title', mw.message('delete_condition').escaped()).addClass('delete_condition');
			$(deleteCondition).on("click", function(event) {
				var conditionHolder = $(this).parent();
				$(conditionHolder).remove();
			});

			return deleteCondition;
		},

		triggerListItem: function (trigger, conditions) {
			var self = this;

			var listItem = $('<li>').attr('data-trigger', trigger).attr('data-conditions', JSON.stringify(conditions)).html($('<span>').html(trigger)).append(this.deleteTrigger());
			$('span', listItem).on("click", function(event) {
				var triggerLi = $(this).parent();
				var position = $(triggerLi).offset();
				var dataTrigger = $(triggerLi).attr('data-trigger');

				self.currentTrigger = dataTrigger;

				if ($(".hook option[value='"+dataTrigger+"']", self.editor).length) {
					$(".hook option[value='"+dataTrigger+"']", self.editor).prop('selected', true);
				}

				$('.condition', self.editor).remove();

				$.each(self.triggers[dataTrigger].conditions, function(index, values) {
					$('.trigger_buttons', self.editor).before(self.conditionBlock(index, values[0], values[1]));
				});

				self.showEditor(position);
			});

			return listItem;
		},

		conditionBlock: function (argumentNumber, comparison, checkValue) {
			var validComparisons = ['==', '!=', '&gt;=', '&lt;=', '&gt;', '&lt;'];
			var argumentNumberInput = $('<input>').addClass('argumentNumber').attr('type', 'text').val(argumentNumber);
			var comparisonInput = $('<select>').addClass('comparison');
			var checkValueInput = $('<input>').addClass('checkValue').attr('type', 'text').val(checkValue);
			validComparisons.forEach(function(value) {
				comparisonInput.append($('<option>').val(value).append(value));
			});
			$("option[value='"+comparison+"']", comparisonInput).prop('selected', true);
			return $('<div>').addClass('condition').append('Arg. #').append(argumentNumberInput).append(' x ').append(comparisonInput).append(checkValueInput).append(this.deleteCondition());
		},

		updateTriggerList: function() {
			var self = this;

			$("input[name='triggers']", this.container).val(JSON.stringify(this.triggers));

			$(this.triggerList).empty();
			if (this.triggers !== null) {
				$.each(this.triggers, function(trigger, conditions) {
					$(self.triggerList).append(self.triggerListItem(trigger, conditions));
				});
			}
		}
	};

	//Expose
	mw.TriggerBuilder = TriggerBuilder;

	if ($('#trigger_builder').length) {
		if ($('#trigger_builder').attr('data-setup') != 'manual') {
			new mw.TriggerBuilder('#trigger_builder');
		}
	}
}(mediaWiki, jQuery));
