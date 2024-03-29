$(function() {
	if ($("li.achievement_category_select").length) {
		updateTabs();
	}
	handleHashChange();

	$("#achievement_form").submit(function(e){
		var cat = $("#category").val();
		var catid = $("#category_id").val();

		if (!cat.length || !catid) {
			e.preventDefault();
			alert("A category is required to save.\n\nIf this error continues, please refresh the page and try again.");
		}
	});


	$(".edit-on-hover").hover(function(){
		$(this).find('.image-edit-box').first().show();
	},function(){
		$(this).find('.image-edit-box').first().hide();
	}).click(function(){
		document.location.href = "/Special:Upload?wpDestFile=" + $(this).find('img').first().attr('data-img') + "&wpForReUpload=1";
	});

	function updateTabs() {
		var lastElement = false;
		var isEnd = false;
		var selector = "ul#achievement_categories > li.achievement_category_select";
		$(selector).each(function() {
			if (isEnd !== true && lastElement !== false && lastElement.offset().top != $(this).offset().top) {
				$(lastElement).addClass("end");
				$(this).removeClass("end");
				isEnd = true;
			} else {
				$(this).removeClass("end");
			}
			lastElement = $(this);
		});
		if (!$(selector).hasClass("end")) {
			$(selector).last().addClass("end");
		}
	}
	if ($("li.achievement_category_select").length) {
		$(window).resize(function() {
			updateTabs();
		});
	}

	$('#achievement_category_select').change(function() {
		var newVal = $('#achievement_category_select option:selected').val();
		var newText = $('#achievement_category_select option:selected').text();
		if (newVal !== '' && newVal !== '-1') {
			$('#category_id').val(newVal);
			$('#category').val(newText);
			$('#achievement_category_select').val('-1');
		}
	}).change();

	/*****************************/
	/* Preview Updating          */
	/*****************************/
	$("input[name='name']").keyup(function() {
		$('.p-achievement-name').html($(this).val());
	}).keyup();

	$("input[name='description']").keyup(function() {
		$('.p-achievement-description').html($(this).val());
	}).keyup();

	$("input[name='image_url']").keyup(function() {
		$('.p-achievement-icon img').attr('src', $(this).val());
	}).keyup();

	$("input[name='points']").keyup(function() {
		var points = $(this).val();
		if (points > 0) {
			$('.p-achievement-points').html($(this).val());
		} else {
			$('.p-achievement-points').html('');
		}
	}).keyup();

	$("input[name='increment']").keyup(function() {
		var increment = $(this).val();
		if (increment > 0) {
			var progressDiv = $('<div>').addClass('p-achievement-progress');
			var spanNumbers = $('<span>').append('0/' + increment);
			var bar = $('<div>').addClass('progress-background').append($('<div>').addClass('progress-bar').attr('style', 'width: 0%;'));
			if ($('.p-achievement-progress').length > 0) {
				$('.p-achievement-progress').replaceWith(progressDiv.append(spanNumbers).append(bar));
			} else {
				$('.p-achievement-row-inner').append(progressDiv.append(spanNumbers).append(bar));
			}
		} else {
			$('.p-achievement-progress').remove();
		}
	}).keyup();

	$('#achievements_container').on('change', "input[name='required_achievements[]']", function(event) {
		var requiresDiv = $('<div>').addClass('p-achievement-requires');
		$("input[name='required_achievements[]']:checked").each(function() {
			var labelText = $(this).parent().text();
			requiresDiv.append($('<span>').append(labelText));
		});

		if ($('.p-achievement-requires').length > 0) {
			$('.p-achievement-requires').replaceWith(requiresDiv);
		} else {
			if ($('.p-achievement-required_by').length > 0) {
				requiresDiv.insertAfter('.p-achievement-required_by');
			} else {
				requiresDiv.insertAfter('.p-achievement-description');
			}
		}
	});
	$("input[name='required_achievements[]']").change();

	if ($('#image_upload').length > 0) {
		$('#image_upload #image_loading').hide();
		var apiURL = mw.config.get('wgScriptPath') + '/api.php';
		var editToken = null;
		var fileKey = null;
		$.get(apiURL + '?action=tokens&type=edit&format=json', function(result) {
			if (result.tokens.edittoken.length > 0) {


				editToken = result.tokens.edittoken;
				if (editToken !== null) {

					var r = new Resumable({
						target: apiURL,
						query: { action: 'upload', token: editToken, format: 'json', stash: 1, ignorewarnings: 1 },
						simultaneousUploads: 1,
						maxFiles: 1,
						testChunks: false,
						fileParameterName: 'chunk'
					});
					r.assignBrowse($('#image_upload'));
					r.assignDrop($('#image_upload'));

					r.on('fileAdded', function(file) {
						r.opts.query.filename = file.fileName;
						fileKey = null;
						delete r.opts.query.filekey;
						r.upload();
						$('#image_upload .MediaTransformError').remove();
						$('#image_upload p').hide();
						$('#image_upload #image_loading').fadeIn();
					});

					r.on('fileProgress', function(file, message) {
						try {
							message = JSON.parse(message);
							if (message !== undefined && message !== null) {
								if ("upload" in message && "filekey" in message.upload && message.upload.filekey.length > 0) {
									fileKey = message.upload.filekey;
									r.opts.query.filekey = fileKey;
								}
								if ("error" in message) {
									alert(message.error.code + "\n\n" + message.error.info);
									$('#image_upload #image_loading').hide();
									$('#image_upload p').show();
								}
							}
						} catch (e) {
							return;
						}
					});

					r.on('fileSuccess', function(file, message) {
						$.post(apiURL, { action: 'upload', token: editToken, format: 'json', ignorewarnings: 1, filename: r.opts.query.filename, filekey: r.opts.query.filekey }, function(result) {
							if ("upload" in result && "result" in result.upload && result.upload.result == 'Success') {
								$.get(apiURL, { action: 'query', titles: 'Image:' + result.upload.filename, prop: 'imageinfo', iiprop: 'url', format: 'json' }, function(result) {
									$.each(result.query.pages, function(pageID, pageInfo) {
										$("input[name='image']").val(pageInfo.title);
										$("input[name='image']").keyup();

										return;
									});
								});
								var wikiText = '[[File:' + result.upload.filename + '|100px|link=]]';
								$.get(apiURL, { action: 'parse', text: wikiText, format: 'json' }, function(result) {
									$('#image_upload #image_loading').fadeOut();

									$('#image_upload').append(result.parse.text['*']);
								});
							}
						});
					});

				}
			}
		});
	}

	if ("onhashchange" in window) {
		$(window).on('hashchange', function(e) {
			handleHashChange();
		}).trigger('hashchange');
	} else {
		var hash = window.location.hash;
		window.setInterval(function() {
			if (window.location.hash != hash) {
				handleHashChange();
			}
		}, 100);
	}

	function handleHashChange() {
		var args = getHashArguments();
		if (typeof args.filterby !== "undefined") {
			$("#search_field").val(decodeURIComponent(args.filterby));
			if(!args.category || args.category !== "all") {
				window.location.hash = '#category=all&filterby=' + args.filterby;
			}
			filterbyResults(args.filterby);
		} else {
			clearFilter();
		}
		if (args.category) {
			switchCategoryTab(args.category);
		}
		if (args.achievement) {
			highlightAchievement(args.achievement, args.category);
		}
	}

	function getHashArguments() {
		var hash = window.location.hash;
		var argsString = hash.substring(1);
		var argumentPairs = argsString.split('&');
		var args = [];
		for (var i = argumentPairs.length - 1; i >= 0; i--) {
			var pair = argumentPairs[i].split('=');
			args[pair[0]] = pair[1];
		}
		return args;
	}

	function switchCategoryTab(slug) {
		if ($(".achievement_category[data-slug='" + slug + "']").length > 0) {
			$('.achievement_category').hide();
			$('.achievement_category').attr('data-selected', 'false');
			$('.achievement_category_select').attr('data-selected', 'false');
			$(".achievement_category[data-slug='" + slug + "']").show();
			$(".achievement_category[data-slug='" + slug + "']").attr('data-selected', 'true');
			$(".achievement_category_select[data-slug='" + slug + "']").attr('data-selected', 'true');
		}
		if (slug == "all") {
			$('.achievement_category').attr('data-selected', 'false');
			$('.achievement_category_select').attr('data-selected', 'false');
			$('.achievement_category').show();
		}
	}

	function highlightAchievement(achievementId, categorySlug) {
		if ($(".achievement_category[data-slug='" + categorySlug + "']").length > 0 && $(".p-achievement-row[data-id='" + achievementId + "']").length > 0) {
			$(".p-achievement-row").removeClass('selected');
			$(".p-achievement-row[data-id='" + achievementId + "']").addClass('selected');

			var achievementTop = $(".p-achievement-row[data-id='" + achievementId + "']").position().top;
			var categoryTop = $(".achievement_category[data-slug='" + categorySlug + "']").offset().top;
			$(".achievement_category[data-slug='" + categorySlug + "']").animate({
				scrollTop: achievementTop
			}, 1000);
		}
	}

	$("#search_button").click(function(){
		var filterBy = $("#search_field").val();
		window.location.hash = '#category=all&filterby=' + encodeURIComponent(filterBy);
	});

	$("#search_reset_button").click(function () {
		clearFilter();
		window.location.hash = '#category=all';
	});

	function filterbyResults(filterby) {
		if (typeof filterby === "string") {
			search = decodeURIComponent(filterby).toLowerCase();
			$('.achievement_category').each(function () {
				$(this).show();
			});
			for (var x in window.achievementsList) {
				var name = window.achievementsList[x].toLowerCase();
				var achievementRow = $(".p-achievement-row[data-id='" + x + "']");

				if (name.indexOf(search) > -1) {
					achievementRow.show();
				} else {
					achievementRow.hide();
				}
			}
		}
	}

	function clearFilter() {
		$("#search_field").val('');
		$(".p-achievement-row").each(function(){
			$(this).show();
		});
	}

	if ($(".achievement_category_select[data-selected='true']").length < 1) {
		var slug = $(".achievement_category_select:first-child").attr('data-slug');
		switchCategoryTab(slug);
	}

	$('.achievement_category_select').click(function() {
		var slug = $(this).attr('data-slug');
		window.location.hash = '#category=' + slug;
	});

	$('.p-achievement-row').click(function() {
		var parent = $(this).parent();
		var categorySlug = $(parent).attr('data-slug');
		var achievementId = $(this).attr('data-id');
		window.location.hash = '#category=' + categorySlug + '&achievement=' + achievementId;
	});

	if ($('#wiki_selection_container').length) {
		var megaWikiSelector = new mw.WikiSelect('#wiki_selection_container');

		megaWikiSelector.registerOnChange(function(wikiSelector) {
			var siteKey = wikiSelector.getSingleWikiKey();
			var siteAchievementsURL = mw.config.get('wgScriptPath') + '/Special:MegaAchievements/siteAchievements?siteKey=' + siteKey;
			if (typeof siteKey !== 'string' || siteKey.length !== 32) {
				return;
			}
			$('#achievements_container').html('');
			wikiSelector.showProgressIndicator($('#achievements_container'));
			$.get(siteAchievementsURL, function(result) {
				if ("success" in result && result.success === true) {
					$('#achievements_container').css('background-image', '');
					for (var achievementId in result.achievements) {
						if (result.achievements.hasOwnProperty(achievementId)) {
							var input = $('<input>').attr('type', 'checkbox').attr('name', 'required_achievements[]').val(result.achievements[achievementId].unique_hash);
							if ($.inArray(result.achievements[achievementId].unique_hash, window.megaAchievementRequires) !== -1) {
								$(input).attr('checked', true);
							}
							var achievementRow = $('<label>').append(input).append(result.achievements[achievementId].name);
							$('#achievements_container').append(achievementRow);
						}
					}
				} else if ("error" in result) {
					alert(mw.message(result.error));
				}
				wikiSelector.hideProgressIndicator($('#achievements_container'));
			});
		});
	}

	mw.loader.using("mediawiki.widgets.DateInputWidget").then(function() { 
		var sdate = new mw.widgets.DateInputWidget(
			{
				inputFormat: 'YYYY-MM-DD',
				displayFormat: 'YYYY-MM-DD',
				value: $("#date_range_start_datepicker").val(),
				data: $("#date_range_start_datepicker").attr('data-input')
			}
		);
		$("#date_range_start_datepicker").replaceWith(sdate.$element);
		sdate.on('change',function() {
			$("#"+sdate.data).val(new Date(sdate.getValue()).getTime()/1000);
		});
		var edate = new mw.widgets.DateInputWidget(
			{
				inputFormat: 'YYYY-MM-DD',
				displayFormat: 'YYYY-MM-DD',
				value: $("#date_range_end_datepicker").val(),
				data: $("#date_range_end_datepicker").attr('data-input')
			}
		);
		$("#date_range_end_datepicker").replaceWith(edate.$element);
		edate.on('change',function() {
			$("#"+edate.data).val(new Date(edate.getValue()).getTime()/1000);
		});
	});
});
