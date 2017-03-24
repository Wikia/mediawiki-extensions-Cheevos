(function(mw, $) {
	console.log('Cheevos Stats Code Loaded.');
	var api = new mw.Api();



	// Pie chart for Custom Achievements.
	var customAchievementsPie = new Chart($("#customAchievementsPie"), {
		type: 'pie',
		data: {
			labels: ["Have Custom","Standard",],
			datasets: [{
					data: [
						getData('wikisWithCustomAchievements'),
						(getData('totalWikis') - getData('wikisWithCustomAchievements')),
					],
					backgroundColor: ["#FF6384","#36A2EB"],
					hoverBackgroundColor: ["#FF6384","#36A2EB"]
				}]
		},
		options: { responsive: true }
	});




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
			$("#wikiStats").hide();
			$("#allStats").show();





		} else {
			$("#allStats").hide();
			$("#wikiStats").show();


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
