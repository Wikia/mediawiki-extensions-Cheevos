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

class TemplateWikiPointsAdmin {
	/**
	 * Initialize form HTML for each page.
	 *
	 * @access	public
	 * @param	array	[Optional] Form data for resubmission.
	 * @return	string	Built HTML
	 */
	static private function initHtml($form = []) {
		$username = htmlspecialchars($form['username']);
		$wikiPointsAdminPage = Title::newFromText('Special:WikiPointsAdmin');
		$wikiPointsPage = Title::newFromText('Special:WikiPoints');

		$html = '';
		if (!empty($form['error'])) {
			$html .= "<div class='errorbox'>{$form['error']}</div>";
		}
		return $html .= "
		<form id='wikipoints_lookup_form' class='mw-ui-vform' method='get' action='".$wikiPointsAdminPage->getFullURL()."'>
			<legend>".wfMessage('user_lookup')->escaped()."</legend>
			<input type='hidden' name='action' value='lookup'/>
			<div class='mw-ui-vform-field'>
				<input type='text' name='user_name' placeholder='".wfMessage('wpa_user')->escaped()."' value='{$username}' class='oo-ui-inputWidget-input'/>
			</div>
			<div class='mw-ui-vform-field'>
				<input class='submit mw-ui-button mw-ui-constructive' type='submit' value='".wfMessage('lookup')->escaped()."'/>
			</div>
			<div class='right'><a href='".$wikiPointsPage->getFullURL()."'>".wfMessage('view_public_points_list')->escaped()."</a></div>
		</form>";
	}

	/**
	 * User lookup display
	 *
	 * @access	public
	 * @param	array	Raw table row of looked up user if found.
	 * @param	array	[Optional] Earned point entries for the found user.
	 * @param	array	[Optional] Form data for resubmission.
	 * @return	string	Built HTML
	 */
	static public function lookup($user, $points = [], $form = []) {
		global $wgRequest;

		$html = self::initHtml($form);

		$lookup = \CentralIdLookup::factory();
		$globalId = $lookup->centralIdFromLocalUser($user);
		if ($globalId) {
			$addSubtractButtonText = wfMessage('wikipointsaddsubtractbutton')->escaped();
			$addSubtractTooltip    = wfMessage('wikipointsaddsubtracttooltip')->escaped();
			$pointsAdjusted        = wfMessage('wikipointsaddsubtractsuccess')->escaped();

			if ($wgRequest->getVal('pointsAdjusted')) {
				$html .= "
				<div><div class='successbox'>{$pointsAdjusted}</div></div>";
			}

			$wpaPage = Title::newFromText('Special:WikiPointsAdmin');
			$escapedUserName = htmlspecialchars($user->getName(), ENT_QUOTES);
			$html .= "
			<div id='wpa_user_controls'>
				<h2>{$escapedUserName}</h2>
				<form method='post' action='".$wpaPage->getFullURL()."'>
					<fieldset>
						<input type='hidden' name='action' value='adjust'>
						<input type='hidden' name='user_id' value='{$user->getId()}'>
						<input type='hidden' name='user_name' value='{$escapedUserName}'>
						<input type='number' name='amount' placeholder='{$addSubtractTooltip}' id='addSubtractField'> <input type='submit' value='{$addSubtractButtonText}'>
					</fieldset>
					<small>".wfMessage('note_about_wikipoints_and_negatives')->escaped()."</small>
				</form>
			</div>
			<h2>".wfMessage('points_recently_earned')->escaped()."</h2>
			<form class='wikipoints' method='post' action='".$wpaPage->getFullURL()."'>
				<fieldset class='bounding'>
					<input name='action' type='hidden' value='revoke'>
					<input name='user_name' type='hidden' value='{$escapedUserName}'>
					<table class='wikitable wikipoints'>
						<thead>
							<tr>
								<th>".wfMessage('wpa_reason')->escaped()."</th>
								<th>".wfMessage('wpa_date')->escaped()."</th>
								<th title='".wfMessage('char_size')->escaped()."'>".wfMessage('char_size')->escaped()."</th>
								<th title='".wfMessage('char_diff')->escaped()."'>".wfMessage('char_diff')->escaped()."</th>
								<th>".wfMessage('score')->escaped()."</th>
							</tr>
						</thead>
						<tbody>";
			foreach ($points as $pointRow) {
				$html .= "
							<tr>";

				if ($pointRow->getPage_Id()) {
					$title = Title::newFromID($pointRow->getPage_Id());
					//@TODO: On Monday - Add oldid to URL link.
					if ($title) {
						$arguments = [];
						if ($pointRow->getRevision_Id()) {
							$revision = Revision::newFromId($pointRow->getRevision_Id());
							if ($revision !== null) {
								$arguments['oldid'] = $revision->getTextId();
							}
						}
						$link = '<a href="'.$title->getInternalURL($arguments).'">'.$title->getText().'</a>';
					} else {
						$link = '<span title="'.wfMessage('deleted_article')->escaped().'">'.wfMessage('article_id_number')->escaped().$pointRow->getPage_Id().'</span>';
					}
				}
				if ($pointRow->getAchievement_Id()) {
					$title = SpecialPage::getTitleFor('Achievements');
					try {
						$achievement = \Cheevos\Cheevos::getAchievement($pointRow->getAchievement_Id());
						$link = '<a href="'.$title->getInternalURL().'#category='.$achievement->getCategory()->getSlug().'&achievement='.$pointRow->getAchievement_Id().'">'.htmlentities($achievement->getName()).'</a>';
					} catch (\Cheevos\CheevosException $e) {
						$link = '<a href="'.$title->getInternalURL().'#achievement='.$pointRow->getAchievement_Id().'">'.wfMessage('achievement_id', $pointRow->getAchievement_Id())->escaped().'</a>';
					}
				}
				$html .= "
								<td>{$link}</td>
								<td>".date('Y-m-d H:i:s', $pointRow->getTimestamp())."</td>
								<td class='numeric'>".($pointRow->getPage_Id() ? $pointRow->getSize() : wfMessage("n-a")->escaped())."</td>
								<td class='numeric'>".($pointRow->getPage_Id() ? $pointRow->getSize_Diff() : wfMessage("n-a")->escaped())."</td>
								<td class='numeric'>{$pointRow->getPoints()}</td>
							</tr>";
			}
			$html .= "
						</tbody>
					</table>
				</fieldset>
			</form>";
		}

		return $html;
	}
}
