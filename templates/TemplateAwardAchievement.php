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

class TemplateAwardAchievement {
	/**
	 * Cheevos URL
	 *
	 * @var		string
	 */
	private $awardURL;

	/**
	 * URL Prefix
	 *
	 * @var		string
	 */
	private $urlPrefix;

	/**
	 * Main Constructer
	 *
	 * @access	public
	 * @return	void
	 */
	public function __construct() {
		global $wgScriptPath, $wgUser;

		$awardPage		= Title::newFromText('Special:AwardAchievement');
		$this->awardURL	= $awardPage->getFullURL();

		$this->urlPrefix = wfExpandUrl($wgScriptPath);
		$this->wgUser = $wgUser;
	}

	/**
	 * Award Achievement Form
	 *
	 * @access	public
	 * @param	array	Array of saved form information.
	 * @param	array	Array of achievement information.
	 * @return	string	Built HTML
	 */
	public function awardForm($form, $achievements, $megaAchievements) {
		global $wgRequest;

		$HTML = '';

		$wasAwarded = $wgRequest->getVal('do') == wfMessage('award')->escaped();
		if ($form['success'] === true) {
			$HTML = "<div class='successbox'>".wfMessage('achievement_awarded', ($wgRequest->getVal('do') == wfMessage('award')->escaped() ? wfMessage('awarded') : wfMessage('unawarded')))->escaped()."</div>";
		} elseif ($form['success'] === false) {
			$HTML = "<div class='errorbox'>".wfMessage('achievement_award_failed', mb_strtolower(($wasAwarded ? wfMessage('award') : wfMessage('unaward')), 'UTF-8'), mb_strtolower(($wasAwarded ? wfMessage('awarded') : wfMessage('unawarded')), 'UTF-8'))->escaped()."</div>";
		}
		$HTML .= "
		<form action='{$this->awardURL}' id='mw-awardachievement-form' method='post' name='mw-awardachievement-form'>
			<fieldset>
				<legend>".wfMessage('award_hint')->escaped()."</legend>
				".(isset($form['errors']['username']) ? '<span class="error">'.$form['errors']['username'].'</span><br/>' : '')."
				<label for='offset'>".wfMessage('local_username')->escaped()."</label> <input autofocus='' id='offset' name='username' size='20' value='".$form['save']['username']."'><br/>";
		if (is_array($achievements) && count($achievements)) {
			$HTML .= "
				".(isset($form['errors']['achievement_id']) ? '<span class="error">'.$form['errors']['achievement_id'].'</span><br/>' : '')."
				<select id='achievement_id' name='achievement_id'>\n";
			foreach ($achievements as $achievementId => $achievement) {
				$HTML .= "
					<option value='{$achievementId}'".(isset($form['save']['achievement_id']) && $form['save']['achievement_id'] == $achievementId ? " selected='selected'" : null).">".htmlentities($achievement->getName(), ENT_QUOTES)."</option>\n";
			}
			$HTML .= "
				</select><br/>";
		}
		$HTML .= "
				<input name='type' type='hidden' value='local'/>
				<input name='do' type='submit' value='".wfMessage('award')->escaped()."'><input name='do' type='submit' value='".wfMessage('unaward')->escaped()."'>
			</fieldset>
		</form>";

		if (defined('ACHIEVEMENTS_MASTER') && ACHIEVEMENTS_MASTER === true) {
			if (!empty($form['errors']['mega_service_error'])) {
				$HTML .= "<div class='errorbox'>".$form['errors']['mega_service_error']."</div>";
			}
			$HTML .= "
		<form action='{$this->awardURL}' id='mw-awardachievement-form' method='post' name='mw-awardachievement-form'>
			<fieldset>
				<legend>".wfMessage('mega_award_hint')->parse()."</legend>
				".($form['errors']['username'] ? '<span class="error">'.$form['errors']['username'].'</span><br/>' : '')."
				<label for='offset'>".wfMessage('local_username')->escaped()."</label> <input autofocus='' id='offset' name='username' size='20' value='".$form['save']['username']."'><br/>";
		if (is_array($achievements) && count($achievements)) {
			$HTML .= "
				".(isset($form['errors']['achievement_id']) ? '<span class="error">'.$form['errors']['achievement_id'].'</span><br/>' : '')."
				<select id='achievement_id' name='achievement_id'>\n";
			foreach ($megaAchievements as $achievementId => $achievement) {
				$HTML .= "
					<option value='{$achievementId}'".(isset($form['save']['achievement_id']) == $achievementId ? " selected='selected'" : null).">".htmlentities($achievement->getName(), ENT_QUOTES)."</option>\n";
			}
			$HTML .= "
				</select><br/>";
		}
		$HTML .= "
				<input name='type' type='hidden' value='mega'/>
				<input name='do' type='submit' value='".wfMessage('award')->escaped()."'><input name='do' type='submit' value='".wfMessage('unaward')->escaped()."'>
			</fieldset>
		</form>";
		}
		return $HTML;
	}
}
