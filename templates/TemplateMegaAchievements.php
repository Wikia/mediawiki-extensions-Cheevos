<?php
/**
 * Cheevos
 * Mega Achievements Template
 *
 * @author		Wiki Platform Team
 * @copyright	(c) 2017 Curse Inc.
 * @license		All Rights Reserved
 * @package		Cheevos
 * @link		http://www.curse.com/
 *
 **/

class TemplateMegaAchievements {
	/**
	 * Main Constructer
	 *
	 * @access	public
	 * @return	void
	 */
	public function __construct() {
		global $wgUser, $wgScriptPath;

		$this->urlPrefix = wfExpandUrl($wgScriptPath);
	}

	/**
	 * Mega Achievement List
	 *
	 * @access	public
	 * @param	array	Array of Mega Achievement Information
	 * @param	object	Progress object for loaded user if applicable.
	 * @param	array	Hidden Options
	 * @param	string	Search Term
	 * @param	string	Pagination Controls
	 * @return	string	Built HTML
	 */
	public function megaAchievementsList($achievements, $progress, $hide, $searchTerm, $pagination) {
		global $wgOut, $wgRequest, $wgUser;

		$megaAchievementsPage	= Title::newFromText('Special:MegaAchievements');
		$megaAchievementsURL	= $megaAchievementsPage->getFullURL();

		$HTML = "
		<div>{$pagination}</div>";
		if ($wgUser->isAllowed('achievement_admin')) {
			$HTML .= "
		<div class='search_bar'>
			<form method='get' action='{$megaAchievementsURL}'>
				<fieldset>
					<input type='hidden' name='section' value='list' />
					<input type='hidden' name='do' value='search' />
					<input type='text' name='list_search' value='".htmlentities($searchTerm, ENT_QUOTES)."' class='search_field' />
					<input type='submit' value='".wfMessage('list_search')."' class='button' />
					<a href='{$megaAchievementsURL}?do=resetSearch' class='button'>".wfMessage('list_reset')."</a>
				</fieldset>
			</form>
		</div>
		<div class='buttons'>
			<a href='?hide_deleted=".($hide['deleted'] ? 'false' : 'true')."' class='button'><span class='legend_deleted'></span>".wfMessage(($hide['deleted'] ? 'show' : 'hide').'_deleted_achievements')->escaped()."</a>
			".($wgUser->isAllowed('achievement_admin') ? "<a href='{$megaAchievementsURL}/admin?do=add' class='button'>".wfMessage('add_achievement')->escaped()."</a>" : null)."
		</div>
			";
		}
		$HTML .= "
		<div id='p-achievement-list'>";
		if (count($achievements)) {
			foreach ($achievements as $achievementId => $achievement) {
				if ($achievement->isDeleted() && $hide['deleted'] === true) {
					continue;
				}
				$HTML .= $this->megaAchievementBlockRow($achievement, true, ($progress !== false ? $progress->forAchievement($achievement->getId()) : []));
			}
		} else {
			$HTML .= "
			<span class='p-achievement-error large'>".wfMessage('no_mega_achievements_found')->escaped()."</span>
			<span class='p-achievement-error small'>".wfMessage('no_mega_achievements_found_help')->escaped()."</span>";
		}
		$HTML .= "
		</div>";

		$HTML .= $pagination;

		return $HTML;
	}

	/**
	 * Generates achievement block to display.
	 *
	 * @access	public
	 * @param	array	Achievement Information
	 * @param	boolean	[Optional] Show Controls
	 * @param	array	[Optional] Achievement Progress Information
	 * @return	string	Built HTML
	 */
	public function megaAchievementBlockRow($achievement, $showControls = true, $progress = []) {
		global $wgUser, $achPointAbbreviation;

		$megaAchievementsPage	= Title::newFromText('Special:MegaAchievements');
		$megaAchievementsURL	= $megaAchievementsPage->getFullURL();

		$HTML = "
			<div class='p-achievement-row".(isset($progress['date']) && $progress['date'] > 0 ? ' earned' : null).($achievement->isDeleted() ? ' deleted' : null)."' data-id='{$achievement->getId()}'>
				<div class='p-achievement-icon'>
					<img src='{$achievement->getImageUrl()}'/>
				</div>
				<div class='p-achievement-row-inner'>
					<span class='p-achievement-name'>".htmlentities($achievement->getName(), ENT_QUOTES)."</span>
					<span class='p-achievement-description'>".htmlentities($achievement->getDescription(), ENT_QUOTES)."</span>
					<div class='p-achievement-requirements'>";
		if (count($achievement->getRequires())) {
			$notAvailable = 0;
			$HTML .= "
						<div class='p-achievement-requires'>
						".wfMessage('requires')->escaped();
			foreach ($achievement->getRequires() as $requiresHash) {
				$_temp = \Cheevos\Achievement::newFromHash($requiresHash);
				if ($_temp === false) {
					$notAvailable++;
				} else {
					$HTML .= "
						<span>".$_temp->getName()."</span>";
				}
			}
			if ($notAvailable > 0) {
				$HTML .= "
						<span>".wfMessage('plus_external_achievements', $notAvailable)->escaped()."</span>";
			}
			$HTML .= "
						</div>";
		}
		$HTML .= "
					</div>";
		if ($showControls) {
			if ($wgUser->isAllowed('achievement_admin')) {
				$HTML .= "
					<div class='p-achievement-admin'>";
				if ($achievement->isDeleted()) {
					$HTML .= "
						<span class='p-achievement-restore'><a href='{$megaAchievementsURL}/restore?id={$achievement->getId()}' class='button'>".wfMessage('restore_achievement')->escaped()."</a></span>";
				} else {
					$HTML .= "
						<span class='p-achievement-delete'><a href='{$megaAchievementsURL}/delete?id={$achievement->getId()}' class='button'>".wfMessage('delete_achievement')->escaped()."</a></span>
						<span class='p-achievement-edit'><a href='{$megaAchievementsURL}/admin?id={$achievement->getId()}' class='button'>".wfMessage('edit_achievement')->escaped()."</a></span>";
				}
				$HTML .= "
					</div>";
			}
		}
		if (isset($progress['date']) && $progress['date'] > 0) {
			$HTML .= "
					<div class='p-achievement-earned'>
						".$progress['earned_date']."
					</div>";
		}
		$HTML .= "
				</div>
			</div>";

		return $HTML;
	}

	/**
	* Mega Achievement Form
	*
	* @access	public
	* @param	array	Array of achievement information.
	* @param	array	Known Hooks
	* @param	array	All Base Achievements
	* @param	array	Wiki site for this mega achievement.
	* @param	array	Key name => Error of errors
	* @return	string	Built HTML
	*/
	public function megaAchievementsForm($megaAchievement, $knownHooks, $achievements, $site, $errors) {
		global $wgScriptPath;

		$megaAchievementsPage	= Title::newFromText('Special:MegaAchievements');
		$megaAchievementsURL	= $megaAchievementsPage->getFullURL();

		$HTML = $this->megaAchievementBlockRow($megaAchievement, false, ['date' => 1]);
		if (array_key_exists('save', $errors)) {
			$HTML .= "<div class='errorbox'>{$errors['save']}</div>";
		}
		$HTML .= "
			<form id='achievement_form' method='post' action='{$megaAchievementsURL}/admin?do=save'>
				<fieldset>
					<h2>".wfMessage('general_achievement_section')->escaped()."</h2>
					".($errors['name'] ? '<span class="error">'.$errors['name'].'</span>' : '')."
					<label for='name' class='label_above'>".wfMessage('achievement_name')->escaped()."</label>
					<input id='name' name='name' type='text' maxlength='255' placeholder='".wfMessage('achievement_name_helper')->escaped()."' value='".htmlentities($megaAchievement->getName(), ENT_QUOTES)."' />";

					$HTML .= ($errors['description'] ? '<span class="error">'.$errors['description'].'</span>' : '')."
					<label for='description' class='label_above'>".wfMessage('achievement_description')->escaped()."</label>
					<input id='description' name='description' type='text' maxlength='255' placeholder='".wfMessage('achievement_description_helper')->escaped()."' value='".htmlentities($megaAchievement->getDescription(), ENT_QUOTES)."' />

					".($errors['image_url'] ? '<span class="error">'.$errors['image_url'].'</span>' : '')."
					<div id='image_upload'>
						<img id='image_loading' src='".wfExpandUrl($wgScriptPath."/extensions/Achievements/images/loading.gif")."'/>
						<p class='image_hint'>".wfMessage('image_hint')->escaped()."</p>
					</div>
					<label for='image_url' class='label_above'>".wfMessage('achievement_image_url')->escaped()."<div class='helper_mark'><span>".wfMessage('image_upload_help')."</span></div></label>
					<input id='image_url' name='image_url' type='text' value='".htmlentities($megaAchievement->getImageUrl(), ENT_QUOTES)."' />

					<h2>".wfMessage('mega_section')->escaped()."</h2>
					<div id='wiki_selection_container' data-setup='manual'>
						<input id='site_key' class='wiki_selections' name='site_key' data-select-key='megaachievementswiki' data-select-type='single' type='hidden' value='{\"single\": \"{$megaAchievement->getSiteKey()}\"}'/>
					</div>";

		$HTML .= "
					<script type='text/javascript'>
						window.megaAchievementRequires = ".json_encode($megaAchievement->getRequires()).";
					</script>
					<label class='label_above'>".wfMessage('required_achievements')->escaped()."<div class='helper_mark'><span>".wfMessage('required_achievements_help')."</span></div></label>
					<div id='achievements_container'>";
			if (count($achievements)) {
				foreach ($achievements as $unique_hash => $info) {
					$HTML .= "
						<label><input type='checkbox' name='required_achievements[]' value='{$info->getHash()}'".(in_array($info->getHash(), $megaAchievement->getRequires()) ? " checked='checked'" : null)."/>{$info->getName()}</label>
						";
				}
			}
		$HTML .= "
					</div>";

		$HTML .= "
				</fieldset>
				<fieldset class='submit'>
					<input id='id' name='id' type='hidden' value='{$megaAchievement->getId()}'/>
					<input id='wiki_submit' name='wiki_submit' type='submit' value='Save'/>
				</fieldset>
			</form>";

		return $HTML;
	}

	/**
	 * Mega Achievement Deletion Form
	 *
	 * @access	public
	 * @param	array	Achievement information.
	 * @param	string	[Optional] Error message if any.
	 * @return	string	Built HTML
	 */
	public function megaAchievementsDelete($megaAchievement, $error = false) {
		$megaAchievementsPage	= Title::newFromText('Special:MegaAchievements');
		$megaAchievementsURL	= $megaAchievementsPage->getFullURL();

		if (!empty($error)) {
			$HTML .= "<div class='errorbox'>{$error}</div>";
		}
		if ($megaAchievement->isDeleted()) {
			$HTML .= "
			<div>
				".wfMessage('restore_achievement_confirm')->escaped()."<br/>
				<a href='{$megaAchievementsURL}/restore?id={$megaAchievement->getId()}&amp;confirm=true' class='button'>".wfMessage('restore_achievement')->escaped()."</a>
			</div>
			";
		} else {
			$HTML .= "
			<div>
				".wfMessage('delete_achievement_confirm')->escaped()."<br/>
				<a href='{$megaAchievementsURL}/delete?id={$megaAchievement->getId()}&amp;confirm=true' class='button'>".wfMessage('delete_achievement')->escaped()."</a>
			</div>
			";
		}

		return $HTML;
	}

	/**
	 * Mega Achievement Deletion Form
	 *
	 * @access	public
	 * @param	object	Wiki
	 * @return	string	Built HTML
	 */
	public function updateSiteMegaForm($wiki) {
		$megaAchievementsPage	= Title::newFromText('Special:MegaAchievements');
		$megaAchievementsURL	= $megaAchievementsPage->getFullURL();

		$HTML .= "
		<div>
			".wfMessage('update_site_mega_confirm')->escaped()."<br/>
			<a href='{$megaAchievementsURL}/updateSiteMega?siteKey={$wiki->getSiteKey()}&amp;confirm=true' class='button'>".wfMessage('update_site_mega')->escaped()."</a>
		</div>
		";

		return $HTML;
	}
}
