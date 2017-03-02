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

class TemplateManageAchievements {
	/**
	 * Achievement List
	 *
	 * @access	public
	 * @param	array	Array of Achievement Object
	 * @param	array	Array of Category Information
	 * @param	object	Progress object for loaded user if applicable.
	 * @param	array	Hidden Options
	 * @param	string	Search Term
	 * @return	string	Built HTML
	 */
	public function achievementsList($achievements, $categories, $progress, $hide, $searchTerm) {
		global $wgOut, $wgRequest, $wgUser;

		$achievementsPage	= Title::newFromText('Special:ManageAchievements');
		$achievementsURL	= $achievementsPage->getFullURL();

		$HTML = '';

		if ($wgUser->isAllowed('achievement_admin')) {
			$HTML .= "
		<div class='search_bar'>
			<form method='get' action='{$achievementsURL}'>
				<fieldset>
					<input type='hidden' name='section' value='list' />
					<input type='hidden' name='do' value='search' />
					<input type='text' name='list_search' value='".htmlentities($searchTerm, ENT_QUOTES)."' class='search_field' />
					<input type='submit' value='".wfMessage('list_search')."' class='button' />
					<a href='{$achievementsURL}?do=resetSearch' class='button'>".wfMessage('list_reset')."</a>
				</fieldset>
			</form>
		</div>
		<div class='buttons'>
			<a href='?hide_deleted=".($hide['deleted'] ? 'false' : 'true')."' class='button'><span class='legend_deleted'></span>".wfMessage(($hide['deleted'] ? 'show' : 'hide').'_deleted_achievements')."</a>
			".($wgUser->isAllowed('achievement_admin') ? "<a href='{$achievementsURL}/add' class='button'>".wfMessage('add_achievement')."</a>" : null)."
		</div>
			";
		}
		$HTML .= "
		<div id='p-achievement-list'>";
		if (count($achievements)) {
			$HTML .= "
			<ul id='achievement_categories'>";
			$firstCategory = true;
			foreach ($categories as $categoryId => $category) {
				$categoryHTML[$categoryId] = '';
				foreach ($achievements as $achievementId => $achievement) {

					/*if (($achievement->isDeleted() == 1 && $hide['deleted'] === true) || $achievement->getCategoryId() != $categoryId) {
						continue;
					}*/
					$categoryHTML[$categoryId] .= $this->achievementBlockRow($achievement, true);
				}
				if (!empty($categoryHTML[$categoryId])) {
					$HTML .= "<li class='achievement_category_select".($firstCategory ? ' begin' : '')."' data-slug='{$category->getSlug()}'>{$category->getTitle()}</li>";
					$firstCategory = false;
				}
			}
			$HTML .= "
			</ul>";
			foreach ($categories as $categoryId => $category) {
				if ($categoryHTML[$categoryId]) {
					$HTML .= "
			<div class='achievement_category' data-slug='{$category->getSlug()}'>
				{$categoryHTML[$categoryId]}
			</div>";
				}
			}
		} else {
			$HTML .= "
			<span class='p-achievement-error large'>".wfMessage('no_achievements_found')->escaped()."</span>
			<span class='p-achievement-error small'>".wfMessage('no_achievements_found_help')->escaped()."</span>";
		}
		$HTML .= "
		</div>";

		return $HTML;
	}

	/**
	 * Generates achievement block to display.
	 *
	 * @access	public
	 * @param	array	Achievement Information
	 * @return	string	Built HTML
	 */
	public function achievementBlockPopUp($achievement) {
		global $achPointAbbreviation, $wgSitename, $dsSiteKey;

		$achievementsPage		= Title::newFromText('Special:'.($achievement->isMega() ? 'Mega' : '').'Achievements');

		$HTML = "
			<div class='p-achievement-row p-achievement-notice p-achievement-remote' data-hash='{$dsSiteKey}-{$achievement->getHash()}'>
				<div class='p-achievement-source'>".($achievement->global ? wfMessage('mega_achievement_earned')->escaped() : $wgSitename)."</div>
				<div class='p-achievement-icon'>
					<img src='{$achievement->getImageUrl()}'/>
				</div>
				<div class='p-achievement-row-inner'>
					<span class='p-achievement-name'>".htmlentities($achievement->getName(), ENT_QUOTES)."</span>
					<span class='p-achievement-description'>".htmlentities($achievement->getDescription(), ENT_QUOTES)."</span>
				</div>
				<span class='p-achievement-points'>".$achievement->getPoints()."{$achPointAbbreviation}</span>
				<a href='{$achievementsPage->getFullURL()}'><span></span></a>
			</div>";

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
	public function achievementBlockRow($achievement, $showControls = true, $progress = []) {
		global $wgUser, $achPointAbbreviation;

		$achievementsPage	= Title::newFromText('Special:Achievements');
		$achievementsURL	= $achievementsPage->getFullURL();

		$HTML = "
			<div class='p-achievement-row".(isset($progress['date']) && $progress['date'] > 0 ? ' earned' : null).($achievement->isDeleted() ? ' deleted' : null).($achievement->isSecret() ? ' secret' : null)."' data-id='{$achievement->getId()}'>
				<div class='p-achievement-icon'>
					<img src='{$achievement->getImageUrl()}'/>
				</div>
				<div class='p-achievement-row-inner'>
					<span class='p-achievement-name'>".htmlentities($achievement->getName(), ENT_QUOTES)."</span>
					<span class='p-achievement-description'>".htmlentities($achievement->getDescription(), ENT_QUOTES)."</span>
					<div class='p-achievement-requirements'>";
		/*if (count($achievement->getRequiredBy())) {
			$HTML .= "
						<div class='p-achievement-required_by'>
						".wfMessage('required_by')->escaped();
			foreach ($achievement->getRequiredBy() as $requiredByAId) {
				$_temp = \Achievements\Achievement::newFromId($requiredByAId);
				$HTML .= "
							<span>".($_temp !== false ? $_temp->getName() : "FATAL ERROR LOADING REQUIRED BY ACHIEVEMENT - PLEASE FIX THIS")."</span>";
			}
			$HTML .= "
						</div>";
		}
		if (count($achievement->getRequires())) {
			$HTML .= "
						<div class='p-achievement-requires'>
						".wfMessage('requires')->escaped();
			foreach ($achievement->getRequires() as $requiresAId) {
				$_temp = \Achievements\Achievement::newFromId($requiresAId);
				$HTML .= "
							<span>".($_temp !== false ? $_temp->getName() : "FATAL ERROR LOADING REQUIRED ACHIEVEMENTS - PLEASE FIX THIS")."</span>";
			}
			$HTML .= "
						</div>";
		}*/
		$HTML .= "
					</div>";
		if ($showControls) {
			if ($wgUser->isAllowed('achievement_admin')) {
				$HTML .= "
					<div class='p-achievement-admin'>";
				if ($achievement->isDeleted()) {
					$HTML .= "
						<span class='p-achievement-restore'><a href='{$achievementsURL}/restore?aid={$achievement->getId()}' class='button'>".wfMessage('restore_achievement')->escaped()."</a></span>";
				} else {
					$HTML .= "
						<span class='p-achievement-delete'><a href='{$achievementsURL}/delete?aid={$achievement->getId()}' class='button'>".wfMessage('delete_achievement')->escaped()."</a></span>";
				}
				$HTML .= "
						<span class='p-achievement-edit'><a href='{$achievementsURL}/admin?aid={$achievement->getId()}' class='button'>".wfMessage('edit_achievement')->escaped()."</a></span>
					</div>";
			}
		}
		/*if ($achievement->getIncrement() > 0 && isset($progress['date']) && $progress['date'] <= 0) {
			$width = (intval($progress['increment']) / $achievement->getIncrement()) * 100;
			if ($width > 100) {
				$width = 100;
			}
			$HTML .= "
					<div class='p-achievement-progress'>
						<div class='progress-background'><div class='progress-bar' style='width: {$width}%;'></div></div><span>".intval($progress['increment'])."/{$achievement->getIncrement()}</span>
					</div>";
		}
		if (isset($progress['date']) && $progress['date'] > 0) {
			$HTML .= "
					<div class='p-achievement-earned'>
						".(isset($progress['earned_date']) ? $progress['earned_date'] : '')."
					</div>";
		}*/
		$HTML .= "
				</div>
				<span class='p-achievement-points'>".intval($achievement->getPoints())."{$achPointAbbreviation}</span>
			</div>";

		return $HTML;
	}

	/**
	* Achievements Form
	*
	* @access	public
	* @param	array	Array of achievement information.
	* @param	array	Achievement Categories
	* @param	array	Known Hooks
	* @param	array	All Achievements
	* @param	array	Key name => Error of errors
	* @return	string	Built HTML
	*/
	public function achievementsForm($achievement, $categories, $knownHooks, $allAchievements, $errors) {
		global $wgUser, $wgScriptPath;

		$achievementsPage	= Title::newFromText('Special:Achievements');
		$achievementsURL	= $achievementsPage->getFullURL();

		$HTML = $this->achievementBlockRow($achievement, false);
		$HTML .= "
			<form id='achievement_form' method='post' action='{$achievementsURL}/admin?do=save'>
				<fieldset>
					<h2>".wfMessage('general_achievement_section')->escaped()."</h2>
					".($errors['name'] ? '<span class="error">'.$errors['name'].'</span>' : '')."
					<label for='name' class='label_above'>".wfMessage('achievement_name')->escaped()."</label>
					<input id='name' name='name' type='text' maxlength='50' placeholder='".wfMessage('achievement_name_helper')->escaped()."' value='".htmlentities($achievement->getName(), ENT_QUOTES)."' />

					".($errors['description'] ? '<span class="error">'.$errors['description'].'</span>' : '')."
					<label for='description' class='label_above'>".wfMessage('achievement_description')->escaped()."</label>
					<input id='description' name='description' type='text' maxlength='150' placeholder='".wfMessage('achievement_description_helper')->escaped()."' value='".htmlentities($achievement->getDescription(), ENT_QUOTES)."' />

					".($errors['category'] ? '<span class="error">'.$errors['category'].'</span>' : '')."
					<label for='category' class='label_above'>".wfMessage('achievement_category')->escaped()."</label>
					<input id='category_id' name='category_id' type='hidden' value='".$achievement->getCategoryId()."'/>
					<input id='category' name='category' type='text' maxlength='30' placeholder='".wfMessage('achievement_category_helper')->escaped()."' value='".($achievement->getCategoryId() > 0 ? htmlentities($categories[$achievement->getCategoryId()]->getTitle(), ENT_QUOTES) : null)."'/>";
		if (is_array($categories) && count($categories)) {
			$HTML .= "
					<select id='achievement_category_select'>
						<option value='0'>&nbsp;</option>\n";
			foreach ($categories as $acid => $category) {
				$HTML .= "
						<option value='{$acid}'".($achievement->getCategoryId() == $acid ? " selected='selected'" : null).">".htmlentities($category->getTitle(), ENT_QUOTES)."</option>\n";
			}
			$HTML .= "
					</select>";
		}

		$HTML .= 	($errors['image_url'] ? '<span class="error">'.$errors['image_url'].'</span>' : '')."
					<div id='image_upload'>
						<img id='image_loading' src='".wfExpandUrl($wgScriptPath."/extensions/Achievements/images/loading.gif")."'/>
						<p class='image_hint'>".wfMessage('image_hint')->escaped()."</p>
					</div>
					<label for='image_url' class='label_above'>".wfMessage('achievement_image_url')->escaped()."<div class='helper_mark'><span>".wfMessage('image_upload_help')."</span></div></label>
					<input id='image_url' name='image_url' type='text' value='".htmlentities($achievement->getImageUrl(), ENT_QUOTES)."' />

					".($errors['points'] ? '<span class="error">'.$errors['points'].'</span>' : '')."
					<label for='points' class='label_above'>".wfMessage('achievement_points')->escaped()."<div class='helper_mark'><span>".wfMessage('points_help')."</span></div></label>
					<input id='points' name='points' type='text' value='".htmlentities($achievement->getPoints(), ENT_QUOTES)."' /><br/>

					<input id='secret' name='secret' type='checkbox' value='1'".($achievement->isSecret() ? " checked='checked'" : null)."/><label for='secret'>".wfMessage('secret_achievement')->escaped()."<div class='helper_mark'><span>".wfMessage('secret_help')->escaped()."</span></div></label><br/>
					<input id='manual_award' name='manual_award' type='checkbox' value='1'".($achievement->isManuallyAwarded() ? " checked='checked'" : null)."/><label for='manual_award'>".wfMessage('manual_award_achievement')->escaped()."<div class='helper_mark'><span>".wfMessage('manual_award_help')->parse()."</span></div></label><br/>
					<input id='part_of_default_mega' name='part_of_default_mega' type='checkbox' value='1'".($achievement->isPartOfDefaultMega() ? " checked='checked'" : null)."/><label for='part_of_default_mega'>".wfMessage('part_of_default_mega_achievement')->escaped()."<div class='helper_mark'><span>".wfMessage('part_of_default_mega_help')->escaped()."</span></div></label>";

		if ($wgUser->isAllowed('edit_meta_achievements')) {
			$HTML .= "
					<h2>".wfMessage('meta_section')->escaped()."</h2>
					<label class='label_above'>".wfMessage('required_achievements')->escaped()."<div class='helper_mark'><span>".wfMessage('required_achievements_help')."</span></div></label>
					<div id='achievements_container'>";
			if (count($allAchievements)) {
				foreach ($allAchievements as $aid => $info) {
					if ($aid == $achievement->getId()) {
						continue;
					}
					$HTML .= "
						<label><input type='checkbox' name='required_achievements[]' value='{$aid}'".(in_array($aid, $achievement->getRequires()) ? " checked='checked'" : null)."/>{$info->getName()}</label>
						";
				}
			}
			$HTML .= "
					</div>";
			}

		if ($wgUser->isAllowed('edit_achievement_triggers')) {
			$HTML .= "
					<h2>".wfMessage('trigger_section')->escaped()."</h2>
					".($errors['increment'] ? '<span class="error">'.$errors['increment'].'</span>' : '')."
					<label for='increment' class='label_above'>".wfMessage('achievement_increment')->escaped()."<div class='helper_mark'><span>".wfMessage('increment_help')->escaped()."</span></div></label>
					<input id='increment' name='increment' type='text' value='".$achievement->getIncrement()."' />

					<label for='trigger_builder' class='label_above'>".wfMessage('trigger_builder')->escaped()."</label>
					<div id='trigger_builder'>
						<input name='triggers' type='hidden' value='".(count($achievement->getTriggers()) ? json_encode($achievement->getTriggers(), JSON_UNESCAPED_SLASHES) : '{}')."'/>
						<input id='hooks' type='hidden' value='".(is_array($knownHooks) && count($knownHooks) ? json_encode($knownHooks, JSON_UNESCAPED_SLASHES) : '{}')."'/>
					</div>";
		}

		$HTML .= "
				</fieldset>
				<fieldset class='submit'>
					<input id='aid' name='aid' type='hidden' value='{$achievement->getId()}'/>
					<input id='wiki_submit' name='wiki_submit' type='submit' value='Save'/>
				</fieldset>
			</form>";

	return $HTML;
	}

	/**
	 * Achievements Deletion Form
	 *
	 * @access	public
	 * @param	array	Achievement information.
	 * @return	string	Built HTML
	 */
	public function achievementsDelete($achievement) {
		$achievementsPage	= Title::newFromText('Special:Achievements');
		$achievementsURL	= $achievementsPage->getFullURL();

		if ($achievement->isDeleted()) {
			$HTML = "
			<div>
				".wfMessage('restore_achievement_confirm')."<br/>
				<a href='{$achievementsURL}/restore?aid={$achievement->getId()}&amp;confirm=true' class='button'>".wfMessage('restore_achievement')."</a>
			</div>
			";
		} else {
			$HTML = "
			<div>
				".wfMessage('delete_achievement_confirm')."<br/>
				<a href='{$achievementsURL}/delete?aid={$achievement->getId()}&amp;confirm=true' class='button'>".wfMessage('delete_achievement')."</a>
			</div>
			";
		}

		return $HTML;
	}
}
