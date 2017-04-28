<?php
/**
 * Curse Inc.
 * Wiki Points
 * A contributor scoring system
 *
 * @author		Noah Manneschmidt
 * @copyright	(c) 2014 Curse Inc.
 * @license		All Rights Reserved
 * @package		Wiki Points
 * @link		http://www.curse.com/
 *
**/

use \Cheevos\Points;

class TemplateWikiPoints {
	/**
	 * Output HTML
	 *
	 * @var		string
	 */
	private $HMTL;

	/**
	 * Points table
	 *
	 * @access	public
	 * @param	array	Array of rows of top points
	 * @param	string	Pagination HTML
	 * @param	array	[Optional] Load wiki information for sites mode.
	 * @param	boolean	[Optional] Including all wikis or not.
	 * @param	boolean	[Optional] Showing monthly totals.
	 * @return	string	Built HTML
	 */
	public function wikiPoints($userPoints, $pagination, $wikis = [], $isSitesMode = false, $isMonthly = false) {
		global $wgUser;

		$wikiPointsAdminPage = Title::newFromText('Special:WikiPointsAdmin');

		$HTML = implode(' | ', $this->getWikiPointsLinks());

		$HTML .= "
		<div>{$pagination}</div>
		<table class='wikitable'>
			<thead>
				<tr>
					<th>".wfMessage('rank')->escaped()."</th>
					<th>".wfMessage('wiki_user')->escaped()."</th>".
					($isSitesMode ? "<th>".wfMessage('wiki_site')->escaped()."</th>" : "\n")
					."<th>".wfMessage('score')->escaped()."</th>
					".($isMonthly ? "<th>".wfMessage('monthly')->escaped()."</th>" : '')."
				</tr>
			</thead>
			<tbody>";
		if (!empty($userPoints)) {
			$i = 0;
			foreach ($userPoints as $userPointsRow) {
				$adminLink = '';
				if ($wgUser->isAllowed('wiki_points_admin')) {
					$adminLink = ' | '.Html::element(
						'a',
						[
							'href' => $wikiPointsAdminPage->getFullURL(['action' => 'lookup', 'userName' => $userPointsRow->userName])
						],
						wfMessage('wp_admin')
					);
				}
				$i++;
				$HTML .= "
				<tr>
					<td>{$i}</td>
					<td>{$userPointsRow->userLink}{$userPointsRow->userToolsLinks}</td>".
					($isSitesMode ? "<td>".(isset($wikis[$userPointsRow->siteKey]) ? $wikis[$userPointsRow->siteKey]->getNameForDisplay() : $userPointsRow->siteKey)."</td>" : "\n")
					."<td class='score'>{$userPointsRow->score}</td>"
					.($isMonthly ? "<td class='monthly'>".gmdate('F Y', strtotime($userPointsRow->yyyymm.'01'))."</td>" : '')."
				</tr>";
			}
		} else {
			$HTML .= "
				<tr>
					<td colspan='".(3 + $isSitesMode + $isMonthly)."'>".wfMessage('no_points_results_found')->escaped()."</td>
				</tr>
			";
		}
		$HTML .= "
			</tbody>
		</table>
		<div>{$pagination}</div>";

		return $HTML;
	}

	/**
	 * Get links for various wiki points special pages.
	 *
	 * @access	private
	 * @return	array	Anchor links.
	 */
	private function getWikiPointsLinks() {
		$links = [
			Linker::linkKnown(SpecialPage::getTitleFor('WikiPoints'), wfMessage('top_wiki_editors')->escaped()),
			Linker::linkKnown(SpecialPage::getTitleFor('WikiPoints/monthly'), wfMessage('top_wiki_editors_monthly')->escaped()),
			Linker::linkKnown(SpecialPage::getTitleFor('WikiPoints/sites'), wfMessage('top_wiki_editors_sites')->escaped()),
			Linker::linkKnown(SpecialPage::getTitleFor('WikiPoints/sites/monthly'), wfMessage('top_wiki_editors_sites_monthly')->escaped())
		];

		return $links;
	}
}
