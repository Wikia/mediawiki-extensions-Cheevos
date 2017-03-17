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

class TemplateAchievements {
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
					<img src='{$achievement->getImageUrl()}'/>
				</div>
				<div class='p-achievement-row-inner'>
					<span class='p-achievement-name'>".htmlentities($achievement->getName(), ENT_QUOTES)."</span>
					<span class='p-achievement-description'>".htmlentities($achievement->getDescription(), ENT_QUOTES)."</span>
					<div class='p-achievement-requirements'>";
		$HTML .= "
					</div>";
		$HTML .= "
				</div>
				<span class='p-achievement-points'>".intval($achievement->getPoints())."{$achPointAbbreviation}</span>
			</div>";

		return $HTML;
	}

	/**
	 * Generates block of achievements to display.
	 *
	 * @access	public
	 * @param	string	HTML blocks of achievements.
	 * @return	string	Built HTML
	 */
	public function achievementDisplay($blocks) {
		$HTML = "
		<div id='p-achievement-notices' class='p-achievement'>
			{$blocks}
		</div>";

		return $HTML;
	}

}
