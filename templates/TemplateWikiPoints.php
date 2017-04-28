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
	 * @param	string	[Optional] Modifier - Monthly, Daily, etecetera.
	 * @return	string	Built HTML
	 */
	public function wikiPoints($userPoints, $pagination, $modifier = null) {
		global $wgUser;

		$wikiPointsAdminPage = Title::newFromText('Special:WikiPointsAdmin');

		$HTML = implode(' | ', $this->getWikiPointsLinks());

		$HTML .= "
		<div>{$pagination}</div>
		<table class='wikitable'>
			<thead>
				<tr>
					<th>".wfMessage('rank')->escaped()."</th>
					<th>".wfMessage('wiki_user')->escaped()."</th>
					<th>".wfMessage('score')->escaped()."</th>
					".($modifier === 'monthly' ? "<th>".wfMessage('monthly')->escaped()."</th>" : '')."
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
							'href' => $wikiPointsAdminPage->getFullURL(['action' => 'lookup','userName' => $userPointsRow->userName])
						],
						wfMessage('wp_admin')
					);
				}
				$i++;
				$HTML .= "
				<tr>
					<td>{$i}</td>
					<td>{$userPointsRow->userLink}{$userPointsRow->userToolsLinks}</td>
					<td class='score'>{$userPointsRow->score}</td>
					".($modifier === 'monthly' ? "<td class='monthly'>".gmdate('F Y', strtotime($userPointsRow->yyyymm.'01'))."</td>" : '')."
				</tr>";
			}
		} else {
			$HTML .= "
				<tr>
					<td colspan='".($modifier === 'monthly' ? 4 : 3)."'>".wfMessage('no_points_results_found')->escaped()."</td>
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
	 * Wiki points sites totals.
	 *
	 * @access	public
	 * @param	array	Array of rows of top points
	 * @param	string	Pagination HTML
	 * @param	array	Wiki objects loaded from a search.
	 * @param	string	[Optional] Modifier - Monthly, Daily, etecetera.
	 * @return	string	Built HTML
	 */
	public function wikiPointsSite($userPoints, $pagination, $wikis, $modifier = null) {
		global $wgUser;

		$wikiPointsAdminPage = Title::newFromText('Special:WikiPointsAdmin');

		$HTML = implode(' | ', $this->getWikiPointsLinks());

		$HTML .= "
		<div>{$pagination}</div>
		<table class='wikitable'>
			<thead>
				<tr>
					<th>".wfMessage('rank')->escaped()."</th>
					<th>".wfMessage('wiki_user')->escaped()."</th>
					<th>".wfMessage('wiki_site')->escaped()."</th>
					<th>".wfMessage('score')->escaped()."</th>
					".($modifier === 'monthly' ? "<th>".wfMessage('monthly')->escaped()."</th>" : '')."
				</tr>
			</thead>
			<tbody>";
		if (!empty($userPoints)) {
			$i = 0;
			foreach ($userPoints as $userPointsRow) {
				$userLink = Linker::makeExternalLink(
					(isset($wikis[$userPointsRow->site_key]) ? '//'.$wikis[$userPointsRow->site_key]->getDomains()->getDomain() : '').Title::newFromText("User:".$userPointsRow->userName)->getLocalURL(),
					$userPointsRow->userName
				);
				$userContribLink = Linker::makeExternalLink(
					(isset($wikis[$userPointsRow->site_key]) ? '//'.$wikis[$userPointsRow->site_key]->getDomains()->getDomain() : '').Title::newFromText('Special:Contributions/'.$userPointsRow->userName)->getLocalURL(),
					wfMessage('wp_contribs')
				);
				$adminLink = '';
				if ($wgUser->isAllowed('wiki_points_admin')) {
					$adminLink = ' | '.Linker::makeExternalLink(
						(isset($wikis[$userPointsRow->site_key]) ? '//'.$wikis[$userPointsRow->site_key]->getDomains()->getDomain() : '').$wikiPointsAdminPage->getLocalURL(['action' => 'lookup','userName' => $userPointsRow->userName]),
						wfMessage('wp_admin')
					);
				}
				if ($userContribLink) {
					$i++;
					$HTML .= "
				<tr>
					<td>{$i}</td>
					<td>{$userLink} ({$userContribLink}{$adminLink})</td>
					<td>".(isset($wikis[$userPointsRow->site_key]) ? $wikis[$userPointsRow->site_key]->getNameForDisplay() : $userPointsRow->site_key)."</td>
					<td class='score'>{$userPointsRow->score}</td>
					".($modifier === 'monthly' ? "<td class='monthly'>".gmdate('F Y', strtotime($userPointsRow->yyyymm.'01'))."</td>" : '')."
				</tr>";
				}

			}
		} else {
			$HTML .= "
				<tr>
					<td colspan='".($modifier === 'monthly' ? 5 : 4)."'>".wfMessage('no_points_results_found')->escaped()."</td>
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
