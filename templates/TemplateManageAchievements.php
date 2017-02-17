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
					if (($achievement->isDeleted() == 1 && $hide['deleted'] === true) || $achievement->getCategoryId() != $categoryId) {
						continue;
					}
					$categoryHTML[$categoryId] .= $this->achievementBlockRow($achievement, true, ($progress !== false ? $progress->forAchievement($achievement->getHash()) : []));
				}
				if (!empty($categoryHTML[$categoryId])) {
					$HTML .= "<li class='achievement_category_select".($firstCategory ? ' begin' : '')."' data-slug='{$category->getTitleSlug()}'>{$category->getTitle()}</li>";
					$firstCategory = false;
				}
			}
			$HTML .= "
			</ul>";
			foreach ($categories as $categoryId => $category) {
				if ($categoryHTML[$categoryId]) {
					$HTML .= "
			<div class='achievement_category' data-slug='{$category->getTitleSlug()}'>
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

}
