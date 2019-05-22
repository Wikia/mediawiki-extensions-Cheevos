<?php
/**
 * Cheevos
 * Cheevos Template
 *
 * @package   Cheevos
 * @author    Hydra Wiki Platform Team
 * @copyright (c) 2017 Curse Inc.
 * @license   GPL-2.0-or-later
 * @link      https://gitlab.com/hydrawiki/extensions/cheevos
 **/

class TemplateManageAchievements {
	/**
	 * Achievement List
	 *
	 * @param  array	Array of Achievement Object
	 * @param  array	Array of Category Information
	 * @param  array	Array of achievements that can be reverted.  All child achievements can be reverted, but this hides the button if the child achievement is effectively the same as the parent.
	 *
	 * @return string	Built HTML
	 */
	public function achievementsList($achievements, $categories, $revertHints) {
		global $wgOut, $wgRequest, $wgUser;

		$achievementsPage	= Title::newFromText('Special:ManageAchievements');
		$achievementsURL	= $achievementsPage->getFullURL();

		$HTML = '';

		// pass a list of achievements by Id into javascript space.
		$jsList = [];
		foreach ($achievements as $id => $achievement) {
			$jsList[$id] = $achievement->getName();
		}
		$HTML .= "<script type=\"text/javascript\"> window.achievementsList = " . json_encode($jsList) . "; </script>";

		if ($wgUser->isAllowed('achievement_admin')) {
			$HTML .= "
		<div class='button_bar'>
			<div class='buttons_left'>
				<fieldset>
					<input type='text' name='filter' id='search_field' value='' class='mw-ui-input' />
					<input id='search_button' type='button' value='" . wfMessage('list_search') . "' class='mw-ui-button mw-ui-progressive' />
					<input id='search_reset_button' type='button' value='" . wfMessage('list_reset') . "' class='mw-ui-button mw-ui-destructive' />
				</fieldset>
			</div>
			<div class='button_break'></div>
			<div class='buttons_right'>
				" . ($wgUser->isAllowed('achievement_admin') ? "<a href='{$achievementsURL}/invalidatecache' class='mw-ui-button mw-ui-destructive'>" . wfMessage('invalidatecache_achievement') . "</a>" : null) . "
				" . ($wgUser->isAllowed('achievement_admin') ? "<a href='{$achievementsURL}/award' class='mw-ui-button'>" . wfMessage('award_achievement') . "</a>" : null) . "
				" . ($wgUser->isAllowed('achievement_admin') ? "<a href='{$achievementsURL}/add' class='mw-ui-button mw-ui-progressive'>" . wfMessage('add_achievement') . "</a>" : null) . "
			</div>
		</div>";
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
					$categoryHTML[$categoryId] .= TemplateAchievements::achievementBlockRow($achievement, true, [], $achievements, false, isset($revertHints[$achievementId]));
				}
				if (!empty($categoryHTML[$categoryId])) {
					$HTML .= "<li class='achievement_category_select" . ($firstCategory ? ' begin' : '') . "' data-slug='{$category->getSlug()}'>{$category->getTitle()}</li>";
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
				<h4 class='achievement_category_title'>{$category->getName()}</h4>
				{$categoryHTML[$categoryId]}
			</div>";
				}
			}
		} else {
			$HTML .= "
			<span class='p-achievement-error large'>" . wfMessage('no_achievements_found')->escaped() . "</span>
			<span class='p-achievement-error small'>" . wfMessage('no_achievements_found_help')->escaped() . "</span>";
		}
		$HTML .= "
		</div>";

		return $HTML;
	}

	/**
	* Achievements Form
	*
	* @access public
	* @param  array	Array of achievement information.
	* @param  array	Achievement Categories
	* @param  array	All Achievements
	* @param  array	Key name => Error of errors
	* @return string	Built HTML
	*/
	public function achievementsForm($achievement, $categories, $allAchievements, $errors) {
		global $wgUser, $wgScriptPath, $wgCheevosStats, $dsSiteKey;

		$achievementsPage	= Title::newFromText('Special:ManageAchievements');
		$achievementsURL	= $achievementsPage->getFullURL();
		$category = $achievement->getCategory();

		$HTML = TemplateAchievements::achievementBlockRow($achievement, false, [], $allAchievements, true);

		$HTML .= "<h2>" . wfMessage('general_achievement_section')->escaped() . "</h2>";

		$HTML .= "
			<form id='achievement_form' class=\"pure-form pure-form-stacked\" method='post' action='{$achievementsURL}/admin?do=save'>
				<fieldset>
					" . (isset($errors['name']) ? '<span class="error">' . $errors['name'] . '</span>' : '') . "
					<label for='name' class='label_above'>" . wfMessage('achievement_name')->escaped() . "</label>
					<input id='name' name='name' type='text' maxlength='50' placeholder='" . wfMessage('achievement_name_helper')->escaped() . "' value='" . htmlentities($achievement->getName(), ENT_QUOTES) . "' />

					" . (isset($errors['description']) ? '<span class="error">' . $errors['description'] . '</span>' : '') . "
					<label for='description' class='label_above'>" . wfMessage('achievement_description')->escaped() . "</label>
					<input id='description' name='description' type='text' maxlength='150' placeholder='" . wfMessage('achievement_description_helper')->escaped() . "' value='" . htmlentities($achievement->getDescription(), ENT_QUOTES) . "' />

					" . (isset($errors['category']) ? '<span class="error">' . $errors['category'] . '</span>' : '') . "
					<label for='category' class='label_above'>" . wfMessage('achievement_category')->escaped() . "</label>
					<input id='category_id' name='category_id' type='hidden' value='" . $category->getId() . "'/>
					<input id='category' name='category' type='text' maxlength='30' placeholder='" . wfMessage('achievement_category_helper')->escaped() . "' value='" . $category->getName() . "'/>";

		if (is_array($categories) && count($categories)) {
			$HTML .= "<select id='achievement_category_select'>
									<option value='0'></option>\n";
			foreach ($categories as $gid => $category) {
				$acid = $category->getId();
				$HTML .= "<option value='{$acid}'" . ($achievement->getCategoryId() == $acid ? " selected='selected'" : null) . ">" . htmlentities($category->getTitle(), ENT_QUOTES) . "</option>\n";
			}
			$HTML .= "</select>";

		}

		$HTML .= (isset($errors['image']) ? '<span class="error">' . $errors['image'] . '</span>' : '') . "
			<div id='image_upload'>
				<img id='image_loading' src='" . wfExpandUrl($wgScriptPath . "/extensions/Achievements/images/loading.gif") . "'/>
				<p class='image_hint'>" . wfMessage('image_hint')->escaped() . "</p>
			</div>
			<label for='image' class='label_above'>" . wfMessage('achievement_image')->escaped() . "<div class='helper_mark'><span>" . wfMessage('image_upload_help') . "</span></div></label>
			<input id='image' name='image' type='text' value='" . htmlentities($achievement->getImage(), ENT_QUOTES) . "' />

			" . (isset($errors['points']) ? '<span class="error">' . $errors['points'] . '</span>' : '') . "
			<label for='points' class='label_above'>" . wfMessage('achievement_points')->escaped() . "<div class='helper_mark'><span>" . wfMessage('points_help') . "</span></div></label>
			<input id='points' name='points' type='text' value='" . htmlentities($achievement->getPoints(), ENT_QUOTES) . "' /><br/>

			<input id='secret' name='secret' type='checkbox' value='1'" . ($achievement->isSecret() ? " checked='checked'" : null) . "/><label for='secret'>" . wfMessage('secret_achievement')->escaped() . "<div class='helper_mark'><span>" . wfMessage('secret_help')->escaped() . "</span></div></label><br/>";
		if ($dsSiteKey === 'master') {
			$HTML .= "
			<input id='global' name='global' type='checkbox' value='1'" . ($achievement->isGlobal() ? " checked='checked'" : null) . "/><label for='global'>" . wfMessage('global_achievement')->escaped() . "<div class='helper_mark'><span>" . wfMessage('global_help')->escaped() . "</span></div></label><br/>
			<input id='protected' name='protected' type='checkbox' value='1'" . ($achievement->isProtected() ? " checked='checked'" : null) . "/><label for='protected'>" . wfMessage('protected_achievement')->escaped() . "<div class='helper_mark'><span>" . wfMessage('protected_help')->escaped() . "</span></div></label><br/>
			<input id='special' name='special' type='checkbox' value='1'" . ($achievement->getSpecial() ? " checked='checked'" : null) . "/><label for='special'>" . wfMessage('special_achievement')->escaped() . "<div class='helper_mark'><span>" . wfMessage('special_help')->escaped() . "</span></div></label><br/>
			<input id='show_on_all_sites' name='show_on_all_sites' type='checkbox' value='1'" . ($achievement->getShow_On_All_Sites() ? " checked='checked'" : null) . "/><label for='protected'>" . wfMessage('show_on_all_sites_achievement')->escaped() . "<div class='helper_mark'><span>" . wfMessage('show_on_all_sites_help')->escaped() . "</span></div></label><br/>";

		}

		if ($wgUser->isAllowed('edit_meta_achievements')) {
			$criteria = $achievement->getCriteria();
			$stats = (isset($criteria['stats']) && is_array($criteria['stats'])) ? $criteria['stats'] : [];

			$streakEnum = ['none','hourly', 'daily', 'weekly', 'monthly', 'yearly'];

			$HTML .= "<h2>" . wfMessage('critera')->escaped() . "</h2>

			<label class='label_above'>" . wfMessage('criteria_stats')->escaped() . "<div class='helper_mark'><span>" . wfMessage('criteria_stats_help') . "</span></div></label>
			<div class='criteria_container'>";
			foreach ($wgCheevosStats as $stat) {
				$HTML .= "<label><input type='checkbox' name='criteria_stats[]' value='{$stat}'" . (in_array($stat, $stats) ? " checked='checked'" : null) . "/>{$stat}</label>";
			}
			$HTML .= "</div>

				<label class='label_above'>" . wfMessage('criteria_value')->escaped() . "<div class='helper_mark'><span>" . wfMessage('criteria_value_help') . "</span></div></label>
				<input name='criteria_value' type='text' value='" . (isset($criteria['value']) ? $criteria['value'] : '') . "' />

				<label class='label_above'>" . wfMessage('criteria_streak')->escaped() . "<div class='helper_mark'><span>" . wfMessage('criteria_stats_help') . "</span></div></label>
				<select name='criteria_streak'>";
			foreach ($streakEnum as $streak) {
				$HTML .= "<option value='{$streak}' " . ((isset($criteria['streak']) && $criteria['streak'] == $streak) ? 'selected' : '') . ">" . ucfirst($streak) . "</option>";
			}
			$HTML .= "</select>

				<label class='label_above'>" . wfMessage('criteria_streak_progress_required')->escaped() . "<div class='helper_mark'><span>" . wfMessage('criteria_streak_progress_required_help') . "</span></div></label>
				<input name='criteria_streak_progress_required' type='text' value='" . (isset($criteria['streak_progress_required']) ? $criteria['streak_progress_required'] : '') . "' />

				<label class='label_above'>" . wfMessage('criteria_streak_reset_to_zero')->escaped() . "<div class='helper_mark'><span>" . wfMessage('criteria_streak_reset_to_zero_help') . "</span></div></label>
				<select name='criteria_streak_reset_to_zero'>
					<option value='0' " . (isset($criteria['streak_reset_to_zero']) && !$criteria['streak_reset_to_zero'] ? "selected" : '') . ">False</option>
					<option value='1' " . (isset($criteria['streak_reset_to_zero']) && $criteria['streak_reset_to_zero'] ? "selected" : '') . ">True</option>
				</select>

				" . (isset($errors['date_range_start']) ? '<span class="error">' . $errors['date_range_start'] . '</span>' : '') . "
				<label for='date_range_start' class='label_above'>" . wfMessage('criteria_not_before')->escaped() . "</label>
				<input id='date_range_start_datepicker' data-input='date_range_start' type='text' value='" . (isset($criteria['date_range_start']) && $criteria['date_range_start'] > 0 ? htmlentities(gmdate('Y-m-d', $criteria['date_range_start']), ENT_QUOTES) : '') . "'/>
				<input id='date_range_start' name='date_range_start' type='hidden' value='" . htmlentities($criteria['date_range_start'], ENT_QUOTES) . "'/>

				" . (isset($errors['date_range_end']) ? '<span class="error">' . $errors['date_range_end'] . '</span>' : '') . "
				<label for='date_range_end' class='label_above'>" . wfMessage('criteria_not_after')->escaped() . "</label>
				<input id='date_range_end_datepicker' data-input='date_range_end' type='text' value='" . (isset($criteria['date_range_end']) && $criteria['date_range_end'] > 0 ? htmlentities(gmdate('Y-m-d', $criteria['date_range_end']), ENT_QUOTES) : '') . "'/>
				<input id='date_range_end' name='date_range_end' type='hidden' value='" . htmlentities($criteria['date_range_end'], ENT_QUOTES) . "'/>
				";

			if ($dsSiteKey === 'master') {
				$HTML .= "
				<label class='label_above'>" . wfMessage('criteria_per_site_progress_maximum')->escaped() . "<div class='helper_mark'><span>" . wfMessage('criteria_per_site_progress_maximum_help') . "</span></div></label>
				<input name='criteria_per_site_progress_maximum' type='text' value='" . (isset($criteria['per_site_progress_maximumd']) ? $criteria['per_site_progress_maximum'] : '') . "' />";
			}

			$HTML .= "
				<label class='label_above'>" . wfMessage('criteria_category_id')->escaped() . "<div class='helper_mark'><span>" . wfMessage('criteria_category_id_help') . "</span></div></label>
				<select name='criteria_category_id'>
					<option value='0'>(0) None</option>";
			foreach ($categories as $category) {
				$acid = $category->getId();
				$HTML .= "<option value='{$acid}'" . ((isset($criteria['category_id']) && $criteria['category_id'] == $acid) ? " selected='selected'" : null) . ">({$acid}) " . htmlentities($category->getTitle(), ENT_QUOTES) . "</option>\n";
			}
			$HTML .= "</select>

				<label class='label_above'>" . wfMessage('criteria_achievement_ids')->escaped() . "<div class='helper_mark'><span>" . wfMessage('criteria_achievement_ids_help') . "</span></label>
			</div>
			<div class='criteria_container'>";
			if (count($allAchievements)) {
				$seenIds = [];
				foreach ($allAchievements as $aid => $info) {
					$id = ($info->getParent_Id() ? $info->getParent_Id() : $info->getId());
					if ($info->getId() == $achievement->getId() || isset($seenIds[$id])) {
						continue;
					}
					$HTML .= "<label><input type='checkbox' name='criteria_achievement_ids[]' value='{$id}'" . (in_array($info->getId(), $criteria['achievement_ids']) || in_array($info->getParent_Id(), $criteria['achievement_ids']) ? " checked='checked'" : null) . "/>{$info->getName()}</label>";
					$seenIds[$id] = true;
				}
			}
			$HTML .= "</div>";
		}

		$HTML .= "
			</fieldset>
			<fieldset class='submit'>
				<input id='aid' name='aid' type='hidden' value='{$achievement->getId()}'/><br/>
				<button id='wiki_submit' name='wiki_submit' class='mw-ui-button mw-ui-progressive'>" . wfMessage('achievement_save')->escaped() . "</button>
			</fieldset>
		</form>";

		return $HTML;
	}

	/**
	 * Achievement Delete/Restore/Revert Form
	 *
	 * @param  array	Achievement information.
	 *
	 * @return string	Built HTML
	 */
	public function achievementStateChange($achievement, $action) {
		$target = Title::newFromText('Special:ManageAchievements/' . $action);
		$html = "
		<form method='post' action='" . $target->getFullUrl() . "'>
			" . wfMessage($action . '_achievement_confirm')->escaped() . "<br/>
			<input type='hidden' name='confirm' value='true'/>
			<input type='hidden' name='aid' value='{$achievement->getId()}'/>
			<button type='submit' class='mw-ui-button mw-ui-destructive'>" . wfMessage($action . '_achievement')->escaped() . "</button>
		</form>";

		return $html;
	}

	public function awardForm($form, $achievements) {
		global $wgRequest;

		$awardPage		= Title::newFromText('Special:ManageAchievements/award');
		$this->awardURL	= $awardPage->getFullURL();

		$HTML = '';
		$wasAwarded = $wgRequest->getVal('do') == wfMessage('award')->escaped();
		if (isset($form['success']) && is_array($form['success'])) {
			foreach ($form['success'] as $s) {
				if ($s['message'] == "success") {
					$HTML .= "<div class='successbox'>" . wfMessage('achievement_awarded_to', $s['username'], ($wgRequest->getVal('do') == wfMessage('award')->escaped() ? wfMessage('awarded') : wfMessage('unawarded')))->escaped() . "</div><br />";
				}
				if ($s['message'] == "nochange") {
					$HTML .= "<div class='successbox'>" . wfMessage('achievement_nochange_to', $s['username'], ($wgRequest->getVal('do') == wfMessage('award')->escaped() ? wfMessage('awarded') : wfMessage('unawarded')))->escaped() . "</div><br />";
				}
			}
			if (isset($form['errors'])) {
				foreach ($form['errors'] as $e) {
					$HTML .= "<div class='errorbox'>" . $e['username'] . ": " . $e['message'] . "</div><br />";
				}
			}
		} elseif ($form['success'] !== null) {
			if (isset($form['errors'])) {
				foreach ($form['errors'] as $e) {
					$HTML .= "<div class='errorbox'>" . $e['username'] . ": " . $e['message'] . "</div><br />";
				}
			} else {
				$HTML .= "<div class='errorbox'>" . wfMessage('achievement_award_failed', mb_strtolower(($wasAwarded ? wfMessage('award') : wfMessage('unaward')), 'UTF-8'), mb_strtolower(($wasAwarded ? wfMessage('awarded') : wfMessage('unawarded')), 'UTF-8'))->escaped() . "
					<br />" . $form['success']['message'] . "
					</div>";
			}
		}

		$HTML .= "
		<form action='{$this->awardURL}' id='mw-awardachievement-form' method='post' name='mw-awardachievement-form'>
			<fieldset>
				<legend>" . wfMessage('award_hint')->escaped() . "</legend>";
		if (isset($form['errors']['username'])) {
			foreach ($form['errors']['username'] as $err) {
				$HTML .= '<span class="error">' . $err . '</span><br/>';
			}
		}
				$HTML .= "<label for='offset'>" . wfMessage('local_username')->escaped() . "</label>
				<textarea id='username_list' name='username' placeholder='Single username, or comma delimited list of usernames.'>" . (isset($form['save']['username']) ? $form['save']['username'] : '') . "</textarea>";
		if (is_array($achievements) && count($achievements)) {
			$HTML .= "
				" . (isset($form['errors']['achievement_id']) ? '<span class="error">' . $form['errors']['achievement_id'] . '</span><br/>' : '') . "
				<select id='achievement_id' name='achievement_id'>\n";
			foreach ($achievements as $key => $achievement) {
				$achievementId = $achievement->getId();
				$HTML .= "
					<option value='{$achievementId}'" . (isset($form['save']['achievement_id']) && $form['save']['achievement_id'] == $achievementId ? " selected='selected'" : null) . ">" . htmlentities($achievement->getName(), ENT_QUOTES) . "</option>\n";
			}
			$HTML .= "
				</select><br/>";
		}
		$HTML .= "
				<input name='type' type='hidden' value='local'/>
				<input name='do' type='submit' value='" . wfMessage('award')->escaped() . "'><input name='do' type='submit' value='" . wfMessage('unaward')->escaped() . "'>
			</fieldset>
		</form>";

		return $HTML;
	}
}
