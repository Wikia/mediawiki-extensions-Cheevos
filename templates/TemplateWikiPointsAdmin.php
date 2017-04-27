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
	 * Output HTML
	 *
	 * @var		string
	 */
	private $HTML;

	/**
	 * Initialize form HTML for each page.
	 *
	 * @access	public
	 * @param	array	[Optional] Form data for resubmission.
	 * @return	string	Built HTML
	 */
	private function initHtml($form = []) {
		$username = htmlspecialchars($form['username']);
		$wikiPointsAdminPage = Title::newFromText('Special:WikiPointsAdmin');
		$wikiPointsPage = Title::newFromText('Special:WikiPoints');

		if (!empty($form['error'])) {
			$this->HTML .= "<div class='errorbox'>{$form['error']}</div>";
		}
		$this->HTML .= "
		<form id='wikipoints_lookup_form' class='wikipoints' method='get' action='".$wikiPointsAdminPage->getFullURL()."'>
			<fieldset>
				<legend>".wfMessage('user_lookup')->escaped()."</legend>
				<input type='hidden' name='action' value='lookup'/>
				<input type='text' name='user_name' placeholder='".wfMessage('username')->escaped()."' value='{$username}'/>
				<input type='submit' value='".wfMessage('lookup')->escaped()."'/>
				<div class='right'><a href='".$wikiPointsPage->getFullURL()."'>".wfMessage('view_public_points_list')->escaped()."</a> | <a href='".$wikiPointsAdminPage->getFullURL()."?action=recent'>".wfMessage('all_recently_earned')->escaped()."</a></div>
			</fieldset>
		</form>";
	}

	/**
	 * Recently earned points table
	 *
	 * @access	public
	 * @param	array	array of rows of top points
	 * @return	string	Built HTML
	 */
	public function recentTable($userPoints) {
		$this->initHtml();

		$thisPage	= Title::newFromText('Special:WikiPointsAdmin');

		$this->HTML .= "
		<h2>".wfMessage('recently_earned_points')->escaped()."</h2>
		<table class='wikitable wikipoints'>
			<thead><tr><td class='emptycell' colspan='2'></td><th colspan='4'>".wfMessage('calculation_weights')->escaped()."</th><th colspan='3'>".wfMessage('calculation_inputs')->escaped()."</th></tr>
				<tr>
					<th>".wfMessage('article_method_earned')->escaped()."</th>
					<th>".wfMessage('curse_username')->escaped()."</th>
					<th title='".wfMessage('multiplier')->escaped()."'>".wfMessage('m')->escaped()."</th>
					<th title='".wfMessage('pages_edited')->escaped()."'>".wfMessage('wx')->escaped()."</th>
					<th title='".wfMessage('unique_edit')->escaped()."'>".wfMessage('wy')->escaped()."</th>
					<th title='".wfMessage('char_diff')->escaped()."'>".wfMessage('wz')->escaped()."</th>
					<th title='".wfMessage('pages_edited')->escaped()."'>".wfMessage('x')->escaped()."</th>
					<th title='".wfMessage('unique_edit')->escaped()."'>".wfMessage('y')->escaped()."</th>
					<th title='".wfMessage('char_diff')->escaped()."'>".wfMessage('z')->escaped()."</th>
					<th>".wfMessage('score')->escaped()."</th>
				</tr>
			</thead>
			<tbody>";
		foreach ($userPoints as $userPointsRow) {
			$userLink = '<a href="'.$thisPage->getFullURL().'?action=lookup&user_name='.urlencode($userPointsRow['user_name']).'">'.htmlentities($userPointsRow['user_name'], ENT_QUOTES).'</a>';

			if ($userPointsRow['reason'] == EditPoints::WIKI_EDIT_EARNED) {
				$calcData = json_decode(stripslashes($userPointsRow['calculation_info']));
				$calculationCells = "
					<td class='numeric'>".$calcData->weights->a / WikiPoints::BASE_MULTIPLIER."</td>
					<td class='numeric'>{$calcData->weights->Wx}</td>
					<td class='numeric'>{$calcData->weights->Wy}</td>
					<td class='numeric'>{$calcData->weights->Wz}</td>
					<td class='numeric'>{$calcData->inputs->x}</td>
					<td class='numeric'>{$calcData->inputs->y}</td>
					<td class='numeric'>{$calcData->inputs->z}</td>";
				$title = Title::newFromID($userPointsRow['article_id']);
				if ($title) {
					$articleOrReason = '<a href="'.$title->getInternalURL().'">'.$title->getText().'</a>';
				} else {
					$articleOrReason = '<span title="'.wfMessage('deleted_article')->escaped().'">'.wfMessage('article_id_number')->escaped().$userPointsRow['article_id'].'</span>';
				}
			} else {
				$calculationCells = "<td colspan='7' class='numeric'>".wfMessage('na')->escaped()."</td>";

				$articleOrReason = wfMessage(EditPoints::$reasonMessage[$userPointsRow['reason']])->escaped();
			}
			$this->HTML .= "
				<tr>
					<td>$articleOrReason</td>
					<td>$userLink</td>
					$calculationCells
					<td class='numeric'>{$userPointsRow['score']}</td>
				</tr>";
		}
		$this->HTML .= "
			</tbody>
		</table>";
		return $this->HTML;
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
	public function lookup($user, $points = [], $form = []) {
		global $wgRequest;

		$this->initHtml($form);
		$lookup = \CentralIdLookup::factory();
		$globalId = $lookup->centralIdFromLocalUser($user);
		if ($globalId) {
			$addSubtractButtonText = wfMessage('wikipointsaddsubtractbutton')->escaped();
			$addSubtractTooltip    = wfMessage('wikipointsaddsubtracttooltip')->escaped();
			$pointsAdjusted        = wfMessage('wikipointsaddsubtractsuccess')->escaped();

			if ($wgRequest->getVal('pointsAdjusted')) {
				$this->HTML .= "
				<div><div class='successbox'>$pointsAdjusted</div></div>";
			}
			if (array_key_exists('pointsRetotaled', $_GET)) {
				if ($wgRequest->getVal('pointsRetotaled')) {
					$this->HTML .= "
					<div class='successbox'>".wfMessage('user_points_retotaled', $wgRequest->getVal('user_name'))->escaped()."</div>";
				} else {
					$this->HTML .= "
					<div class='errorbox'>".wfMessage('user_points_retotaled_error')->escaped()."</div>";
				}
			}

			$wpaPage = Title::newFromText('Special:WikiPointsAdmin');
			$escapedUserName = htmlspecialchars($user->getName(), ENT_QUOTES);
			$this->HTML .= "
			<div id='wpa_user_controls'>
				<h2>{$escapedUserName}</h2>
				<form method='post' action='".$wpaPage->getFullURL()."'>
					<fieldset>
						<input type='hidden' name='action' value='retotal'>
						<input type='hidden' name='user_id' value='{$user->getId()}'>
						<input type='hidden' name='user_name' value='{$escapedUserName}'>
						<button id='retotalButton' type='submit'>".wfMessage('retotal_user')->escaped()."</button>
					</fieldset>
				</form>
				<form method='post' action='".$wpaPage->getFullURL()."'>
					<fieldset>
						<input type='hidden' name='action' value='adjust'>
						<input type='hidden' name='user_id' value='{$user->getId()}'>
						<input type='hidden' name='user_name' value='$escapedUserName'>
						<input type='number' name='amount' placeholder='$addSubtractTooltip' id='addSubtractField'> <input type='submit' value='$addSubtractButtonText'>
					</fieldset>
					<small>".wfMessage('note_about_wikipoints_and_negatives')->escaped()."</small>
				</form>
			</div>
			<h2>".wfMessage('points_recently_earned')->escaped()."</h2>
			<form class='wikipoints' method='post' action='".$wpaPage->getFullURL()."'>
				<fieldset class='bounding'>
					<input name='action' type='hidden' value='revoke'>
					<input name='user_name' type='hidden' value='$escapedUserName'>
					<input name='save_revocation' type='submit' value='".wfMessage('save_revocation')->escaped()."'>
					<table class='wikitable wikipoints'>
						<thead>
							<tr><td class='emptycell' colspan='2'></td><th colspan='4'>".wfMessage('calculation_weights')->escaped()."</th><th colspan='3'>".wfMessage('calculation_inputs')->escaped()."</th></tr>
							<tr>
								<th>".wfMessage('article_method_earned')->escaped()."</th>
								<th>".wfMessage('timestamp')->escaped()."</th>
								<th title='".wfMessage('multiplier')->escaped()."'>".wfMessage('m')->escaped()."</th>
								<th title='".wfMessage('pages_edited')->escaped()."'>".wfMessage('wx')->escaped()."</th>
								<th title='".wfMessage('unique_edit')->escaped()."'>".wfMessage('wy')->escaped()."</th>
								<th title='".wfMessage('char_diff')->escaped()."'>".wfMessage('wz')->escaped()."</th>
								<th title='".wfMessage('pages_edited')->escaped()."'>".wfMessage('x')->escaped()."</th>
								<th title='".wfMessage('unique_edit')->escaped()."'>".wfMessage('y')->escaped()."</th>
								<th title='".wfMessage('char_diff')->escaped()."'>".wfMessage('z')->escaped()."</th>
								<th>".wfMessage('score')->escaped()."</th>
								<th title='".wfMessage('revoke_points')->escaped()."'>".wfMessage('rev')->escaped()."</th>
							</tr>
						</thead>
						<tbody>";
			foreach ($points as $pointRow) {
				$this->HTML .= "
							<tr>";

				if ($pointRow['reason'] == EditPoints::WIKI_EDIT_EARNED) {
					$calcData = json_decode(stripslashes($pointRow['calculation_info']));
					$title = Title::newFromID($pointRow['article_id']);
					if ($title) {
						$link = '<a href="'.$title->getInternalURL().'">'.$title->getText().'</a>';
					} else {
						$link = '<span title="'.".wfMessage('deleted_article')->escaped().".'">'.wfMessage('article_id_number')->escaped().$userPointsRow['article_id'].'</span>';
					}
					$this->HTML .= "
								<td>$link</td>
								<td>{$pointRow['created']}</td>
								<td class='numeric'>".$calcData->weights->a / WikiPoints::BASE_MULTIPLIER."</td>
								<td class='numeric'>{$calcData->weights->Wx}</td>
								<td class='numeric'>{$calcData->weights->Wy}</td>
								<td class='numeric'>{$calcData->weights->Wz}</td>
								<td class='numeric'>{$calcData->inputs->x}</td>
								<td class='numeric'>{$calcData->inputs->y}</td>
								<td class='numeric'>{$calcData->inputs->z}</td>";
				} else {
					$reason = wfMessage(EditPoints::$reasonMessage[$pointRow['reason']])->escaped();
					$this->HTML .= "
								<td>$reason</td>
								<td>{$pointRow['created']}</td>
								<td colspan='7' class='numeric'>".wfMessage('na')->escaped()."</td>";
				}

				$this->HTML .= "
								<td class='numeric'>{$pointRow['score']}</td>
								<td>
									<input type='hidden' name='revoke_list[{$pointRow['wiki_points_id']}]' value='0'>
									<input type='checkbox' name='revoke_list[{$pointRow['wiki_points_id']}]' value='1' title='".wfMessage('revoke_points_for')->escaped()."' ".($pointRow['revoked'] ? 'checked' : '')."></td>
							</tr>";
			}
			$this->HTML .= "
						</tbody>
					</table>
				</fieldset>
			</form>";
		}

		return $this->HTML;
	}
}