(function(mw, $) {
	console.log('Cheevos Stats Code Loaded.');
	var api = new mw.Api();

	// Initialize DataTable for ALL SITE on load.
	$("#all_sites_mega_list").DataTable({
		dom: 'Blfrtip',
		buttons: [
			'csv', 'excel', 'pdf'
		]
	});

	function allWikiDisplay() {
		$("#wikiStats").hide();
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
			}
		});

	}

	// Initialize DataTables on WIKI SITE Load.
	var siteTable = $("#per_wiki_stats").DataTable({
		"columnDefs": [
			{ // last row action buttons
				"targets": -1,
				"orderable": false,
				"data": null,
				"defaultContent": "<button>Action</button>"
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
		$("#allStats").hide();
		$("#wikiStats").show();

		var data = getHashArguments();
		var wiki = data.wiki;

		// Refresh magical table with new fresh dank data :100:
		var ajaxUrl = '/api.php?format=json'
					+ '&action=cheevosstats'
					+ '&do=getWikiStatsTable'
					+ '&wiki=' + wiki;
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
			}
		});

	}


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
		} else {
			singleWikiDisplay();
		}

	}

	/**
	  *   _    _           _____ _    _ _____ _   _  _____
	  *  | |  | |   /\    / ____| |  | |_   _| \ | |/ ____|
	  *  | |__| |  /  \  | (___ | |__| | | | |  \| | |  __
	  *  |  __  | / /\ \  \___ \|  __  | | | | . ` | | |_ |
	  *  | |  | |/ ____ \ ____) | |  | |_| |_| |\  | |__| |
	  *  |_|  |_/_/    \_\_____/|_|  |_|_____|_| \_|\_____|
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
