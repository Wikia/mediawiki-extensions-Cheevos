(function(mw, $) {

	/***
	 *      ______   ________  ________  __    __  _______
	 *     /      \ /        |/        |/  |  /  |/       \
	 *    /$$$$$$  |$$$$$$$$/ $$$$$$$$/ $$ |  $$ |$$$$$$$  |
	 *    $$ \__$$/ $$ |__       $$ |   $$ |  $$ |$$ |__$$ |
	 *    $$      \ $$    |      $$ |   $$ |  $$ |$$    $$/
	 *     $$$$$$  |$$$$$/       $$ |   $$ |  $$ |$$$$$$$/
	 *    /  \__$$ |$$ |_____    $$ |   $$ \__$$ |$$ |
	 *    $$    $$/ $$       |   $$ |   $$    $$/ $$ |
	 *     $$$$$$/  $$$$$$$$/    $$/     $$$$$$/  $$/
	 *
	 *
	 *
	 */

	console.log('Cheevos Stats Code Loaded.');
	var api = new mw.Api();

	/***
	 *     __    __  ________  __        _______   ________  _______    ______
	 *    /  |  /  |/        |/  |      /       \ /        |/       \  /      \
	 *    $$ |  $$ |$$$$$$$$/ $$ |      $$$$$$$  |$$$$$$$$/ $$$$$$$  |/$$$$$$  |
	 *    $$ |__$$ |$$ |__    $$ |      $$ |__$$ |$$ |__    $$ |__$$ |$$ \__$$/
	 *    $$    $$ |$$    |   $$ |      $$    $$/ $$    |   $$    $$< $$      \
	 *    $$$$$$$$ |$$$$$/    $$ |      $$$$$$$/  $$$$$/    $$$$$$$  | $$$$$$  |
	 *    $$ |  $$ |$$ |_____ $$ |_____ $$ |      $$ |_____ $$ |  $$ |/  \__$$ |
	 *    $$ |  $$ |$$       |$$       |$$ |      $$       |$$ |  $$ |$$    $$/
	 *    $$/   $$/ $$$$$$$$/ $$$$$$$$/ $$/       $$$$$$$$/ $$/   $$/  $$$$$$/
	 *
	 *
	 *
	 */

	function showLoading() {
		$("#loadingError").slideUp();
		$("#loadingStats").show();
	}

	function hideLoading() {
		setTimeout(function(){
			$("#loadingStats").hide();
		},500);
	}

	function showError(err) {
		$("#loadingStats").hide();
		$("#loadingError").html('<strong>Error Loading Stats:</strong> '+err).slideDown();
	}

	/***
	 *      ______   __        __              __       __  ______  __    __  ______   ______
	 *     /      \ /  |      /  |            /  |  _  /  |/      |/  |  /  |/      | /      \
	 *    /$$$$$$  |$$ |      $$ |            $$ | / \ $$ |$$$$$$/ $$ | /$$/ $$$$$$/ /$$$$$$  |
	 *    $$ |__$$ |$$ |      $$ |            $$ |/$  \$$ |  $$ |  $$ |/$$/    $$ |  $$ \__$$/
	 *    $$    $$ |$$ |      $$ |            $$ /$$$  $$ |  $$ |  $$  $$<     $$ |  $$      \
	 *    $$$$$$$$ |$$ |      $$ |            $$ $$/$$ $$ |  $$ |  $$$$$  \    $$ |   $$$$$$  |
	 *    $$ |  $$ |$$ |_____ $$ |_____       $$$$/  $$$$ | _$$ |_ $$ |$$  \  _$$ |_ /  \__$$ |
	 *    $$ |  $$ |$$       |$$       |      $$$/    $$$ |/ $$   |$$ | $$  |/ $$   |$$    $$/
	 *    $$/   $$/ $$$$$$$$/ $$$$$$$$/       $$/      $$/ $$$$$$/ $$/   $$/ $$$$$$/  $$$$$$/
	 *
	 *
	 *
	 */

	function allWikiDisplay() {
		showLoading();
		$("#wikiStats").hide();
		$("#megas").hide();
		$("#allStats").show();


		api.get({
			action: 'cheevosstats',
			do: 'getGlobalStats',
			format: 'json',
			formatversion: 2
		}).done(function (result) {
			if (result.success) {
				var data = result.data;

				$(".dataPoint").each(function(){
					var name = $(this).attr('data-name');
					if (data[name] !== null) {
						$(this).html(data[name]);
					}
				});

				$("#topAchieverGlobal .achieverImage").attr('src',data.topAchiever.img);
				$("#topAchieverGlobal .achieverName").html(data.topAchiever.name);

				$("#topNonCurseAchieverGlobal .achieverImage").attr('src', data.topAchieverNonCurse.img);
				$("#topNonCurseAchieverGlobal .achieverName").html(data.topAchieverNonCurse.name);

				new Chart($("#customAchievementsPie"), {
					type: 'pie',
					data: {
						labels: ["Have Custom", "Standard"],
						datasets: [{
							data: [
								data.wikisWithCustomAchievements,
								(data.totalWikis - data.wikisWithCustomAchievements),
							],
							backgroundColor: ["#FF6384", "#36A2EB"],
							hoverBackgroundColor: ["#FF6384", "#36A2EB"]
						}]
					},
					options: { responsive: true }
				});

				hideLoading();
			} else {
				showError('There was an error when pulling stats');
			}
		}).fail(function (xhr, status) {
			showError(status.exception.message);
		});

	}

	/***
	 *     __       __  ________   ______    ______    ______
	 *    /  \     /  |/        | /      \  /      \  /      \
	 *    $$  \   /$$ |$$$$$$$$/ /$$$$$$  |/$$$$$$  |/$$$$$$  |
	 *    $$$  \ /$$$ |$$ |__    $$ | _$$/ $$ |__$$ |$$ \__$$/
	 *    $$$$  /$$$$ |$$    |   $$ |/    |$$    $$ |$$      \
	 *    $$ $$ $$/$$ |$$$$$/    $$ |$$$$ |$$$$$$$$ | $$$$$$  |
	 *    $$ |$$$/ $$ |$$ |_____ $$ \__$$ |$$ |  $$ |/  \__$$ |
	 *    $$ | $/  $$ |$$       |$$    $$/ $$ |  $$ |$$    $$/
	 *    $$/      $$/ $$$$$$$$/  $$$$$$/  $$/   $$/  $$$$$$/
	 *
	 *
	 *
	 */

	// Initialize DataTable for ALL SITE on load.
	var megaTable = $("#all_sites_mega_list").DataTable({
		dom: 'Blfrtip',
		"language": {
			"emptyTable": "Loading data for table..."
		},
		"columns": [
			{ "data": "user" },
			{ "data": "mega" },
			{ "data": "awarded" }
		],
		buttons: [
			'csv', 'excel', 'pdf'
		]
	});

	function megasDisplay() {
		showLoading();
		$("#allStats").hide();
		$("#wikiStats").hide();
		$("#megas").show();

		// Refresh magical table with new fresh dank data :100:
		var ajaxUrl = '/api.php?format=json'
			+ '&action=cheevosstats'
			+ '&do=getMegasTable';

		megaTable.clear().draw();
		megaTable.ajax.url(ajaxUrl).load();

		hideLoading();
	}

	/***
	 *      ______   ______  __    __   ______   __        ________        __       __  ______  __    __  ______
	 *     /      \ /      |/  \  /  | /      \ /  |      /        |      /  |  _  /  |/      |/  |  /  |/      |
	 *    /$$$$$$  |$$$$$$/ $$  \ $$ |/$$$$$$  |$$ |      $$$$$$$$/       $$ | / \ $$ |$$$$$$/ $$ | /$$/ $$$$$$/
	 *    $$ \__$$/   $$ |  $$$  \$$ |$$ | _$$/ $$ |      $$ |__          $$ |/$  \$$ |  $$ |  $$ |/$$/    $$ |
	 *    $$      \   $$ |  $$$$  $$ |$$ |/    |$$ |      $$    |         $$ /$$$  $$ |  $$ |  $$  $$<     $$ |
	 *     $$$$$$  |  $$ |  $$ $$ $$ |$$ |$$$$ |$$ |      $$$$$/          $$ $$/$$ $$ |  $$ |  $$$$$  \    $$ |
	 *    /  \__$$ | _$$ |_ $$ |$$$$ |$$ \__$$ |$$ |_____ $$ |_____       $$$$/  $$$$ | _$$ |_ $$ |$$  \  _$$ |_
	 *    $$    $$/ / $$   |$$ | $$$ |$$    $$/ $$       |$$       |      $$$/    $$$ |/ $$   |$$ | $$  |/ $$   |
	 *     $$$$$$/  $$$$$$/ $$/   $$/  $$$$$$/  $$$$$$$$/ $$$$$$$$/       $$/      $$/ $$$$$$/ $$/   $$/ $$$$$$/
	 *
	 *
	 *
	 */

	// Initialize DataTables on WIKI SITE Load.
	var siteTable = $("#per_wiki_stats").DataTable({
		"language": {
			"emptyTable": "Loading data for table..."
		},
		"columnDefs": [
			{ // last row action buttons
				"targets": -1,
				"orderable": false,
				"data": function(row, type, set, meta) {
					if (row.earned > 0) {
						return "<button>View Users</button>";
					} else {
						return "";
					}
				},
			},{ // yon localization number for Earned
				"targets": 3,
				"render": function (data, type, row) {
					return parseInt(data).toLocaleString();
				}
			},{ //
				"targets": 4,
				"render": function (data, type, row) {
					return data.toString() + "%";
				}
			}
		],
		"columns": [
			{ "data": "name" },
			{ "data": "description" },
			{ "data": "category" },
			{ "data": "earned" },
			{ "data": "userpercent" },
			{}
		],
		buttons: [
			'csv', 'excel', 'pdf'
		],
		dom: 'Blfrtip'
	});

	function singleWikiDisplay() {
		showLoading();
		$("#allStats").hide();
		$("#megas").hide();
		$("#wikiStats").show();

		var data = getHashArguments();
		var wiki = data.wiki;


		// Refresh magical table with new fresh dank data :100:
		var ajaxUrl = '/api.php?format=json'
					+ '&action=cheevosstats'
					+ '&do=getWikiStatsTable'
					+ '&wiki=' + wiki;
		siteTable.clear().draw();
		siteTable.ajax.url(ajaxUrl).load();

		api.get({
			action: 'cheevosstats',
			do: 'getWikiStats',
			format: 'json',
			wiki: wiki,
			formatversion: 2
		}).done(function (result) {
			if (result.success) {
				var data = result.data;
				console.log(data);

				$(".dataPointWiki").each(function () {
					var name = $(this).attr('data-name');
					if (data[name] !== null) {
						$(this).html(data[name]);
					}
				});

				$("#topAchieverThisWiki .achieverImage").attr('src', data.topAchiever.img);
				$("#topAchieverThisWiki .achieverName").html(data.topAchiever.name);
				hideLoading();
			} else {
				showError('There was an error when pulling stats');
			}
		}).fail(function (xhr, status) {
			showError(status.exception.message);
		});

	}



	/***
	 *     __         ______    ______   ______   ______
	 *    /  |       /      \  /      \ /      | /      \
	 *    $$ |      /$$$$$$  |/$$$$$$  |$$$$$$/ /$$$$$$  |
	 *    $$ |      $$ |  $$ |$$ | _$$/   $$ |  $$ |  $$/
	 *    $$ |      $$ |  $$ |$$ |/    |  $$ |  $$ |
	 *    $$ |      $$ |  $$ |$$ |$$$$ |  $$ |  $$ |   __
	 *    $$ |_____ $$ \__$$ |$$ \__$$ | _$$ |_ $$ \__/  |
	 *    $$       |$$    $$/ $$    $$/ / $$   |$$    $$/
	 *    $$$$$$$$/  $$$$$$/   $$$$$$/  $$$$$$/  $$$$$$/
	 *
	 *
	 *
	 */


	$("#wikiSelector").change(function(){
		var val = $(this).val();
		changeHash({'wiki': val});
	});

	function getData(name) {
		return $("#dataHolder").attr('data-'+name);
	}

	function handleChange() {
		var args = getHashArguments();
		console.log("New Hash Set: ", args);

		if (!args.wiki) {
			//always force wiki=all if no wiki hash set.
			return changeHash({ 'wiki': 'all' });
		}

		// Make selector match.
		$("#wikiSelector").val(args.wiki);

		// All Wikis Stat
		if (args.wiki == "all") {
			allWikiDisplay();
		} else if(args.wiki == "megas") {
			megasDisplay();
		} else {
			singleWikiDisplay();
		}

	}

	/***
	 *     __    __   ______    ______   __    __  ______  __    __   ______
	 *    /  |  /  | /      \  /      \ /  |  /  |/      |/  \  /  | /      \
	 *    $$ |  $$ |/$$$$$$  |/$$$$$$  |$$ |  $$ |$$$$$$/ $$  \ $$ |/$$$$$$  |
	 *    $$ |__$$ |$$ |__$$ |$$ \__$$/ $$ |__$$ |  $$ |  $$$  \$$ |$$ | _$$/
	 *    $$    $$ |$$    $$ |$$      \ $$    $$ |  $$ |  $$$$  $$ |$$ |/    |
	 *    $$$$$$$$ |$$$$$$$$ | $$$$$$  |$$$$$$$$ |  $$ |  $$ $$ $$ |$$ |$$$$ |
	 *    $$ |  $$ |$$ |  $$ |/  \__$$ |$$ |  $$ | _$$ |_ $$ |$$$$ |$$ \__$$ |
	 *    $$ |  $$ |$$ |  $$ |$$    $$/ $$ |  $$ |/ $$   |$$ | $$$ |$$    $$/
	 *    $$/   $$/ $$/   $$/  $$$$$$/  $$/   $$/ $$$$$$/ $$/   $$/  $$$$$$/
	 *
	 *
	 *
	 */

	if ("onhashchange" in window) {
		$(window).on('hashchange', function (e) {
			handleChange();
		}).trigger('hashchange');
	} else {
		var hash = window.location.hash;
		window.setInterval(function () {
			if (window.location.hash != hash) {
				handleChange();
			}
		}, 100);
	}

	function changeHash(newargs) {
		var args = getHashArguments();
		for (key in newargs) {
			args[key] = newargs[key];
		}
		var out = new Array();
		for (key in args) {
			if (key.length) {
				out.push(key + '=' + encodeURIComponent(args[key]));
			}
		}
		window.location.hash = '#' + out.join('&');
	}

	function getHashArguments() {
		var hash = window.location.hash;
		var argumentsString = hash.substring(1);
		var argumentPairs = argumentsString.split('&');
		var arguments = [];
		for (var i = argumentPairs.length - 1; i >= 0; i--) {
			var pair = argumentPairs[i].split('=');
			arguments[pair[0]] = pair[1];
		}
		return arguments;
	}

}(mediaWiki, jQuery));
