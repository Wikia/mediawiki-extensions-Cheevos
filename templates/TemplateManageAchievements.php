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
	public function achievementsList($achievements, $categories) {
		global $wgOut, $wgRequest, $wgUser;

		$achievementsPage	= Title::newFromText('Special:ManageAchievements');
		$achievementsURL	= $achievementsPage->getFullURL();

		$HTML = '';

		if ($wgUser->isAllowed('achievement_admin')) {
			$HTML .= "
		<!--<div class='search_bar'> 
			<form method='get' action='{$achievementsURL}'>
				<fieldset>
					<input type='text' name='filter' id='search_field' value='' class='search_field' />
					<input type='submit' value='".wfMessage('list_search')."' class='button' />
					<a href='{$achievementsURL}' class='button'>".wfMessage('list_reset')."</a>
				</fieldset>
			</form>
		</div>-->
		<div class='buttons'>
			".($wgUser->isAllowed('achievement_admin') ? "<a href='{$achievementsURL}/add' class='button'>".wfMessage('add_achievement')."</a>" : null)."
			".($wgUser->isAllowed('achievement_admin') ? "<a href='{$achievementsURL}/award' class='button'>".wfMessage('award_achievement')."</a>" : null)."
		
		</div>
			";
		}
		$HTML .= "
		<div id='p-achievement-list'>";
		if (count($achievements)) {
			$HTML .= "
			<ul id='achievement_categories'>";
			$firstCategory = true;
			foreach ($categories as $categoryIndex => $category) {
				$categoryId = $category->getId();
				$categoryHTML[$categoryId] = '';
				foreach ($achievements as $achievementId => $achievement) {
					if ($achievement->getCategoryId() != $categoryId) {
						continue;
					}
					$categoryHTML[$categoryId] .= $this->achievementBlockRow($achievement, true);
				}
				if (!empty($categoryHTML[$categoryId])) {
					$HTML .= "<li class='achievement_category_select".($firstCategory ? ' begin' : '')."' data-slug='{$category->getSlug()}'>{$category->getTitle()}</li>";
					$firstCategory = false;
				}
			}
			$HTML .= "
			</ul>";
			foreach ($categories as $categoryIndex => $category) {
				$categoryId = $category->getId();
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
					<img src=\"{$achievement->getImageUrl()}\"/>
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

		$achievementsPage	= Title::newFromText('Special:ManageAchievements');
		$achievementsURL	= $achievementsPage->getFullURL();

		$HTML = "
			<div class='p-achievement-row".(isset($progress['date']) && $progress['date'] > 0 ? ' earned' : null).($achievement->isDeleted() ? ' deleted' : null).($achievement->isSecret() ? ' secret' : null)."' data-id='{$achievement->getId()}'>
				<div class='p-achievement-icon'>
					".(!empty($imageUrl) ? "<img src='{$imageUrl}'/>" : "")."
				</div>
				<div class='p-achievement-row-inner'>
					<span class='p-achievement-name'>".htmlentities($achievement->getName(), ENT_QUOTES)."</span>
					<span class='p-achievement-description'>".htmlentities($achievement->getDescription(), ENT_QUOTES)."</span>
					<div class='p-achievement-requirements'>";
		$HTML .= "
					</div>";
		if ($showControls) {
			if ($wgUser->isAllowed('achievement_admin')) {
				$HTML .= "
					<div class='p-achievement-admin'>";
				
					$HTML .= "
						<span class='p-achievement-delete'><a href='{$achievementsURL}/delete?aid={$achievement->getId()}' class='button'>".wfMessage('delete_achievement')->escaped()."</a></span>";
				
				$HTML .= "
						<span class='p-achievement-edit'><a href='{$achievementsURL}/edit?aid={$achievement->getId()}' class='button'>".wfMessage('edit_achievement')->escaped()."</a></span>
					</div>";
			}
		}
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

		$achievementsPage	= Title::newFromText('Special:ManageAchievements');
		$achievementsURL	= $achievementsPage->getFullURL();
		$category = $achievement->getCategory();

		$HTML = $this->achievementBlockRow($achievement, false);

		$HTML .= "<h2>".wfMessage('general_achievement_section')->escaped()."</h2>";

		$HTML .= "
			<form id='achievement_form' class=\"pure-form pure-form-stacked\" method='post' action='{$achievementsURL}/admin?do=save'>
				<fieldset>

					".($errors['name'] ? '<span class="error">'.$errors['name'].'</span>' : '')."
					<label for='name' class='label_above'>".wfMessage('achievement_name')->escaped()."</label>
					<input id='name' name='name' type='text' maxlength='50' placeholder='".wfMessage('achievement_name_helper')->escaped()."' value='".htmlentities($achievement->getName(), ENT_QUOTES)."' />

					".($errors['description'] ? '<span class="error">'.$errors['description'].'</span>' : '')."
					<label for='description' class='label_above'>".wfMessage('achievement_description')->escaped()."</label>
					<input id='description' name='description' type='text' maxlength='150' placeholder='".wfMessage('achievement_description_helper')->escaped()."' value='".htmlentities($achievement->getDescription(), ENT_QUOTES)."' />

					".($errors['category'] ? '<span class="error">'.$errors['category'].'</span>' : '')."
					<label for='category' class='label_above'>".wfMessage('achievement_category')->escaped()."</label>
					<input id='category_id' name='category_id' type='hidden' value='".$category->getId()."'/>
					<input id='category' name='category' type='text' maxlength='30' placeholder='".wfMessage('achievement_category_helper')->escaped()."' value='".$category->getName()."'/>";
		
					if (is_array($categories) && count($categories)) {
						$HTML .= "<select id='achievement_category_select'>
									<option value='0'>&nbsp;</option>\n";
						foreach ($categories as $gid => $category) {
							$acid = $category->getId();
							$HTML .= "<option value='{$acid}'".($achievement->getCategoryId() == $acid ? " selected='selected'" : null).">".htmlentities($category->getTitle(), ENT_QUOTES)."</option>\n";
						}
						$HTML .= "</select>";
								
					}

		$HTML .= 	($errors['image_url'] ? '<span class="error">'.$errors['image_url'].'</span>' : '')."
					<div id='image_upload'>
						<img id='image_loading' src='".wfExpandUrl($wgScriptPath."/extensions/Achievements/images/loading.gif")."'/>
						<p class='image_hint'>".wfMessage('image_hint')->escaped()."</p>
					</div>
					<label for='image' class='label_above'>".wfMessage('achievement_image_url')->escaped()."<div class='helper_mark'><span>".wfMessage('image_upload_help')."</span></div></label>
					<input id='image' name='image' type='text' value='".htmlentities($achievement->getImage(), ENT_QUOTES)."' />

					".($errors['points'] ? '<span class="error">'.$errors['points'].'</span>' : '')."
					<label for='points' class='label_above'>".wfMessage('achievement_points')->escaped()."<div class='helper_mark'><span>".wfMessage('points_help')."</span></div></label>
					<input id='points' name='points' type='text' value='".htmlentities($achievement->getPoints(), ENT_QUOTES)."' /><br/>

					<input id='secret' name='secret' type='checkbox' value='1'".($achievement->isSecret() ? " checked='checked'" : null)."/><label for='secret'>".wfMessage('secret_achievement')->escaped()."<div class='helper_mark'><span>".wfMessage('secret_help')->escaped()."</span></div></label><br/>
					<input id='global' name='global' type='checkbox' value='1'".($achievement->isGlobal() ? " checked='checked'" : null)."/><label for='global'>".wfMessage('global_achievement')->escaped()."<div class='helper_mark'><span>".wfMessage('global_help')->escaped()."</span></div></label><br/>
					<input id='protected' name='protected' type='checkbox' value='1'".($achievement->isProtected() ? " checked='checked'" : null)."/><label for='protected'>".wfMessage('protected_achievement')->escaped()."<div class='helper_mark'><span>".wfMessage('protected_help')->escaped()."</span></div></label><br/>";

		if ($wgUser->isAllowed('edit_meta_achievements')) {
					$criteria = $achievement->getCriteria();
					$stats = ( isset($criteria['stats']) && is_array($criteria['stats']) ) ? $criteria['stats'] : [];

					$statsList = [
						'visit', 'article_edit', 'article_watch', 'article_create', 'article_delete', 'article_move', 
						'article_merge', 'article_protect', 'admin_block_ip', 'admin_patrol', 'curse_profile_comment', 
						'curse_profile_add_friend', 'curse_profile_edit', 'send_email', 'file_upload', 'wiki_points', 
						'curse_profile_edit_fav_wiki', 'curse_profile_comment_reply', 'curse_profile_edit_link_xbl', 
						'curse_profile_edit_link_psn', 'curse_profile_edit_link_steam', 'curse_profile_edit_link_facebook', 
						'curse_profile_edit_link_twitter', 'curse_profile_edit_link_reddit'
					];

					$streakEnum = ['none','hourly', 'daily', 'weekly', 'monthly', 'yearly'];

					$HTML .= "<h2>Criteria</h2>

					<label class='label_above'>".wfMessage('criteria_stats')->escaped()."<div class='helper_mark'><span>".wfMessage('criteria_stats_help')."</span></div></label>
					<div class='criteria_container'>";
					foreach($statsList as $stat) {
						$HTML .= "<label><input type='checkbox' name='criteria_stats[]' value='{$stat}'".(in_array($stat, $stats) ? " checked='checked'" : null)."/>{$stat}</label>";
					}
					$HTML .= "</div>

						<label class='label_above'>".wfMessage('criteria_value')->escaped()."<div class='helper_mark'><span>".wfMessage('criteria_value_help')."</span></div></label>
						<input name='criteria_value' type='text' value='".(isset($criteria['value']) ? $criteria['value'] : '')."' />

						<label class='label_above'>".wfMessage('criteria_streak')->escaped()."<div class='helper_mark'><span>".wfMessage('criteria_stats_help')."</span></div></label>
						<select name='criteria_streak'>";
					foreach($streakEnum as $streak) {
						$HTML .= "<option value='{$streak}' ".((isset($criteria['streak']) && $criteria['streak'] == $streak) ? 'selected' : '').">".ucfirst($streak)."</option>";
					}
					$HTML .= "</select>

						<label class='label_above'>".wfMessage('criteria_streak_progress_required')->escaped()."<div class='helper_mark'><span>".wfMessage('criteria_streak_progress_required_help')."</span></div></label>
						<input name='criteria_streak_progress_required' type='text' value='".(isset($criteria['streak_progress_required']) ? $criteria['streak_progress_required'] : '')."' />

						<label class='label_above'>".wfMessage('criteria_streak_reset_to_zero')->escaped()."<div class='helper_mark'><span>".wfMessage('criteria_streak_reset_to_zero_help')."</span></div></label>
						<select name='criteria_streak_reset_to_zero'>
							<option value='0' ".(isset($criteria['streak_reset_to_zero']) && !$criteria['streak_reset_to_zero'] ? "selected" : '').">False</option>
							<option value='1' ".(isset($criteria['streak_reset_to_zero']) && $criteria['streak_reset_to_zero'] ? "selected" : '').">True</option>
						</select>

						<label class='label_above'>".wfMessage('criteria_per_site_progress_maximum')->escaped()."<div class='helper_mark'><span>".wfMessage('criteria_per_site_progress_maximum_help')."</span></div></label>
						<input name='criteria_per_site_progress_maximum' type='text' value='".(isset($criteria['per_site_progress_maximumd']) ? $criteria['per_site_progress_maximum'] : '')."' />

						<label class='label_above'>".wfMessage('criteria_category_id')->escaped()."<div class='helper_mark'><span>".wfMessage('criteria_category_id_help')."</span></div></label>
						<select name='criteria_category_id'>
							<option value='0'>(0) None</option>";
					foreach ($categories as $category) {
						$acid = $category->getId();
						$HTML .= "<option value='{$acid}'".(( isset($criteria['category_id']) && $criteria['category_id'] == $acid ) ? " selected='selected'" : null ).">({$acid}) ".htmlentities($category->getTitle(), ENT_QUOTES)."</option>\n";
					}
					$HTML .= "</select>

						<label class='label_above'>".wfMessage('criteria_achievement_ids')->escaped()."<div class='helper_mark'><span>".wfMessage('criteria_achievement_ids_help')."</span></label>
					</div>
					<div class='criteria_container'>";
					if (count($allAchievements)) {
						foreach ($allAchievements as $aid => $info) {
							if ($aid == $achievement->getId()) { continue; }
							$HTML .= "<label><input type='checkbox' name='criteria_achievement_ids[]' value='{$aid}'".(in_array($aid, $achievement->getRequires()) ? " checked='checked'" : null)."/>{$info->getName()}</label>";
						}
					}
					$HTML .= "</div>";
		   }



			/*
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
					
		}*/

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
		$achievementsPage	= Title::newFromText('Special:ManageAchievements');
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


	public function awardForm($form, $achievements) {
		global $wgRequest;

		$awardPage		= Title::newFromText('Special:ManageAchievements/award');
		$this->awardURL	= $awardPage->getFullURL();

		$HTML = '';

		$wasAwarded = $wgRequest->getVal('do') == wfMessage('award')->escaped();
		if ($form['success']['message'] == "success") {
			$HTML = "<div class='successbox'>".wfMessage('achievement_awarded', ($wgRequest->getVal('do') == wfMessage('award')->escaped() ? wfMessage('awarded') : wfMessage('unawarded')))->escaped()."</div>";
		} elseif ($form['success'] !== NULL) {
			$HTML = "<div class='errorbox'>".wfMessage('achievement_award_failed', mb_strtolower(($wasAwarded ? wfMessage('award') : wfMessage('unaward')), 'UTF-8'), mb_strtolower(($wasAwarded ? wfMessage('awarded') : wfMessage('unawarded')), 'UTF-8'))->escaped()."
			<br />".$form['success']['message']."
			</div>";
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
			foreach ($achievements as $key => $achievement) {
				$achievementId = $achievement->getId();
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

		
		return $HTML;
	}

}
