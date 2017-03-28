<?php
/**
 * Cheevos
 * Cheevos Template
 *
 * @author		Wiki Platform Team
 * @copyright	(c) 2017 Curse Inc.
 * @license		All Rights Reserved
 * @package		Cheevos
 * @link		http://www.curse.com/
 *
 **/

class TemplateAchievementStats {

	public function achievementsStats($wikis) {
		global $wgOut, $wgRequest, $wgUser;

		$achievementsPage	= Title::newFromText('Special:ManageAchievements');
		$achievementsURL	= $achievementsPage->getFullURL();

		$HTML = "";

		$wikiSelectOptions = ['<option value="all">All Wikis</option>'];
		foreach ($wikis as $wiki) {
			$wikiSelectOptions[] = "<option value=\"". $wiki->getSiteKey() ."\">". $wiki->getName() ."</option>";
		}

		$HTML .= "<div class=\"navbar\">
					Showing Stats for
					<select id=\"wikiSelector\">".implode("",$wikiSelectOptions)."</select>
					<button>Export as CSV</button>
				</div>";

		$HTML .= "<div id=\"loadingStats\">Loading Stats...</div>";
		$HTML .= "<div id=\"loadingError\" style=\"display: none;\">Error Loading Stats</div>";

		// VIEW FOR ALL WIKIS
		$HTML .= "
				<div id=\"allStats\" style=\"display: none;\" class=\"col-group\">
					<div class=\"col-12\">
						<div class=\"achievement-box\">
							<table>
								<tr>
									<th>Total number of Achievements</th>
									<td class=\"dataPoint\" data-name=\"totalAchievements\">???</td>
								</tr>
								<tr>
									<th>Average Achievements per Wiki:</th>
									<td class=\"dataPoint\" data-name=\"averageAchievementsPerWiki\">???</td>
								</tr>
								<tr>
									<th>Total Achievements Earned:</th>
									<td class=\"dataPoint\" data-name=\"totalEarnedAchievements\">???</td>
								</tr>
								<tr>
									<th>Total Mega Achievements Earned:</th>
									<td class=\"dataPoint\" data-name=\"totalEarnedMegaAchievements\">???</td>
								</tr>
								<tr>
									<th>Number of Achievement-Engaged Users:</th>
									<td class=\"dataPoint\" data-name=\"engagedUsers\">???</td>
								</tr>
							</table>
						</div>
					</div>
					<div class=\"col-8\">
						<div class=\"achievement-box\">
							<div id=\"topAchieverGlobal\">
							<img class=\"achieverImage\" src=\"https://placehold.it/96x96\">
							Top Achiever Gamepedia-wide: <span class=\"achieverName\">???</span>
							</div>
						</div>
						<div class=\"achievement-box\">
							<div id=\"topNonCurseAchieverGlobal\">
							<img class=\"achieverImage\" src=\"https://placehold.it/96x96\">
							Top Non-Curse Achiever: <span class=\"achieverName\">???</span>
							</div>
						</div>
					</div>
					<div class=\"col-4\">
						<div class=\"achievement-box\">
							<canvas id=\"customAchievementsPie\" width=\"180\" height=\"180\"></canvas>
						</div>
					</div>
					<div class=\"col-12\">
						<div class=\"achievement-box table-box\">
							<table id=\"all_sites_mega_list\" class=\"compact hover order-column stripe row-border\">
								<thead>
									<tr>
										<th>User</th>
										<th>Mega</th>
										<th>Award Date</th>
									</tr>
								</thead>
								<tbody>
									<tr>
										<td>N/A</td>
										<td>N/A</td>
										<td>N/A</td>
									</tr>
								</tbody>
							</table>
						</div>
					</div>
				</div>";


		// VIEW FOR INDIVIDUAL WIKIS
		$HTML .= "<div id=\"wikiStats\" style=\"display: none;\" class=\"col-group\">
					<div class=\"col-8\">
						<div class=\"achievement-box\">
							<table>
								<tr>
									<th>Total number of Achievements</th>
									<td class=\"dataPointWiki\" data-name=\"totalAchievements\">???</td>

									<th>Total Mega Achievements Earned:</th>
									<td class=\"dataPointWiki\" data-name=\"totalEarnedMegaAchievements\">???</td>
								</tr>
								<tr>
									<th>Total Achievements Earned:</th>
									<td class=\"dataPointWiki\" data-name=\"totalEarnedAchievements\">???</td>

									<th></th>
									<td></td>
								</tr>
							</table>
						</div>
					</div>
					<div class=\"col-4\">
						<div class=\"achievement-box\">
							<div id=\"topAchieverThisWiki\">
								<img class=\"achieverImage\" src=\"https://placehold.it/96x96\">
								Top Achiever for this wiki: <span class=\"achieverName\">???</span>
							</div>
						</div>
					</div>
					<div class=\"col-12\">
						<div class=\"achievement-box table-box\">
							<table id=\"per_wiki_stats\" class=\"compact hover order-column stripe row-border\">
								<thead>
									<tr>
										<th>Achievement</th>
										<th>Description</th>
										<th>Category</th>
										<th>Earned</th>
										<th>User %</th>
										<th></th>
									</tr>
								</thead>
								<tbody>
								</tbody>
							</table>
						</div>
					</div>
				</div>";


		//$HTML .= "<pre>".print_r($achievements,1)."</pre><pre>".print_r($categories,1)."</pre>";
		return $HTML;
	}



}
