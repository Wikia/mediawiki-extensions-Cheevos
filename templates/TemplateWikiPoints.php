<?php
/**
 * Curse Inc.
 * Cheevos
 * A contributor scoring system
 *
 * @author		Noah Manneschmidt
 * @copyright	(c) 2014 Curse Inc.
 * @license		All Rights Reserved
 * @package		Cheevos
 * @link		http://www.curse.com/
 *
**/

class TemplateWikiPoints {
	/**
	 * Points table
	 *
	 * @access	public
	 * @param	array	Array of rows of top points
	 * @param	string	Pagination HTML
	 * @param	integer	Current starting position.
	 * @param	array	[Optional] Load wiki information for sites mode.
	 * @param	boolean	[Optional] Including all wikis or not.
	 * @param	boolean	[Optional] Showing monthly totals.
	 * @return	string	Built HTML
	 */
	static public function pointsBlockHtml($userPoints, $pagination, $start, $wikis = [], $isSitesMode = false, $isMonthly = false) {
		$html .= "
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
			$i = $start;
			foreach ($userPoints as $userPointsRow) {
				$wikiName = $userPointsRow->siteKey;
				if ($isSitesMode && isset($wikis[$userPointsRow->siteKey])) {
					if ($wikis[$userPointsRow->siteKey] instanceof \DynamicSettings\Wiki) {
						$wikiName = $wikis[$userPointsRow->siteKey]->getNameForDisplay();
					} elseif (isset($wikis[$userPointsRow->siteKey]['wiki_name_display'])) {
						$wikiName = $wikis[$userPointsRow->siteKey]['wiki_name_display'];
					}
				}
				$i++;
				$html .= "
				<tr>
					<td>{$i}</td>
					<td>{$userPointsRow->userLink}{$userPointsRow->userToolsLinks}</td>".
					($isSitesMode ? "<td>{$wikiName}</td>" : "\n")
					."<td class='score'>{$userPointsRow->score}</td>"
					.($isMonthly ? "<td class='monthly'>".$userPointsRow->yyyymm."</td>" : '')."
				</tr>";
			}
		} else {
			$html .= "
				<tr>
					<td colspan='".(3 + $isSitesMode + $isMonthly)."'>".wfMessage('no_points_results_found')->escaped()."</td>
				</tr>
			";
		}
		$html .= "
			</tbody>
		</table>
		<div>{$pagination}</div>";

		return $html;
	}

	/**
	 * Get links for various wiki points special pages.
	 *
	 * @access	private
	 * @return	array	Anchor links.
	 */
	static public function getWikiPointsLinks() {
		$links = [
			Linker::linkKnown(SpecialPage::getTitleFor('WikiPoints'), wfMessage('top_wiki_editors')->escaped()),
			Linker::linkKnown(SpecialPage::getTitleFor('WikiPoints', 'monthly'), wfMessage('top_wiki_editors_monthly')->escaped()),
			Linker::linkKnown(SpecialPage::getTitleFor('WikiPoints', 'global'), wfMessage('top_wiki_editors_global')->escaped())
		];
		if (defined('MASTER_WIKI')) {
			$links[] = Linker::linkKnown(SpecialPage::getTitleFor('WikiPoints', 'sites'), wfMessage('top_wiki_editors_sites')->escaped());
			$links[] = Linker::linkKnown(SpecialPage::getTitleFor('WikiPoints', 'sites/monthly'), wfMessage('top_wiki_editors_sites_monthly')->escaped());
		}

		return implode(' | ', $links)."<hr>";
	}

	/**
	 * Get simple dumb pagination.
	 *
	 * @access	public
	 * @param	string	URL Destination
	 * @param	integer	Number of items per page.
	 * @param	integer	Current starting position.
	 * @return	string	HTML
	 */
	static public function getSimplePagination(Title $title, $itemsPerPage, $start) {
		$previous = max(0, $start - $itemsPerPage);
		$next = $start + $itemsPerPage;
		$previous = "<a href='{$title->getFullUrl(['st' => $previous])}' class='mw-ui-button'>&lt;</a>";
		$next = "<a href='{$title->getFullUrl(['st' => $next])}' class='mw-ui-button'>&gt;</a>";
		return $previous.' '.$next;
	}
}
