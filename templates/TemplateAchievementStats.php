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

	public function achievementsStats($achievements, $categories, $wikis) {
		global $wgOut, $wgRequest, $wgUser;

		$achievementsPage	= Title::newFromText('Special:ManageAchievements');
		$achievementsURL	= $achievementsPage->getFullURL();

		$HTML = "";

		$customAchievements = [];

		foreach($achievements as $a) {
			if ($a->getParent_Id() !== 0) {
				$customAchievements[$a->getSite_Key()][] = $a;
			}
		}

		$data = [
			'wikisWithCustomAchievements' => count($customAchievements),
			'totalWikis' => count($wikis)
		];

		// Some sort of crazy way of passing data into the DOM that might be dumb.
		$HTML .= "<div id=\"dataHolder\" style=\"display: none;\" ";
			foreach($data as $key => $value) {
				$HTML .= " data-${key}=\"{$value}\"";
			}
		$HTML .= "></div>";

		$wikiSelectOptions = ['<option value="all">All Wikis</option>'];
		foreach ($wikis as $wiki) {
			$wikiSelectOptions[] = "<option value=\"". $wiki->getSiteKey() ."\">". $wiki->getName() ."</option>";
		}

		$HTML .= "<div class=\"navbar\">
					Showing Stats for
					<select id=\"wikiSelector\">".implode("",$wikiSelectOptions)."</select>
					<button>Export as CSV</button>
				</div>";


		// VIEW FOR ALL WIKIS
		$HTML .= "
				<div id=\"allStats\" style=\"display: none;\" class=\"col-group\">
					<div class=\"col-12\">
						<div class=\"achievement-box\">
							<table>
								<tr>
									<th>Total number of Achievements</th>
									<td>".count($achievements)."</td>
								</tr>
								<tr>
									<th>Average Achievements per Wiki:</th>
									<td>???</td>
								</tr>
								<tr>
									<th>Total Achievements Earned:</th>
									<td>???</td>
								</tr>
								<tr>
									<th>Total Mega Achievements Earned:</th>
									<td>???</td>
								</tr>
								<tr>
									<th>Number of Achievement-Engaged Users:</th>
									<td>???</td>
								</tr>
							</table>
						</div>
					</div>
					<div class=\"col-8\">
						<div class=\"achievement-box\">

							<div>
							<img src=\"http://www.gravatar.com/avatar/c16612aced057a7d3fa0cc0a02afd916?d=mm&s=96\">
							Top Achiever Gamepedia-wide: Cchunn
							</div>
						</div>
						<div class=\"achievement-box\">
							<div>
							<img src=\"http://www.gravatar.com/avatar/c16612aced057a7d3fa0cc0a02afd916?d=mm&s=96\">
							Top Non-Curse Achiever: Cchunn
							</div>

						</div>
					</div>
					<div class=\"col-4\">
						<div class=\"achievement-box\">
							<canvas id=\"customAchievementsPie\" width=\"180\" height=\"180\"></canvas>
						</div>
					</div>
					<div class=\"col-12\">
						<div class=\"achievement-box\">

							Mega Achievements Earned List.

						</div>
					</div>
				</div>";


		// VIEW FOR INDIVIDUAL WIKIS
		$HTML .= "<div id=\"wikiStats\" style=\"display: none;\">THIS BE THE STATS FOR A WIKI</div>";


		//$HTML .= "<pre>".print_r($achievements,1)."</pre><pre>".print_r($categories,1)."</pre>";
		return $HTML;
	}



}
