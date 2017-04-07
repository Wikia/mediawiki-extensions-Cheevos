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
	 * @param	array	[Optional] Array of User Progress for loaded user if applicable.
	 * @param	array	[Optional] Array of User Status for loaded user if applicable.
	 * @return	string	Built HTML
	 */
	public function achievementsList($achievements, $categories, $status = []) {
		global $wgOut, $wgRequest, $wgUser;

		$manageAchievementsPage	= Title::newFromText('Special:ManageAchievements');
		$manageAchievementsURL	= $manageAchievementsPage->getFullURL();

		$HTML = '';

		if ($wgUser->isAllowed('achievement_admin')) {
			$HTML .= "
			<div class='buttons'>
				<a href='{$manageAchievementsURL}' class='mw-ui-button'>".wfMessage('manageachievements')."</a>
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

					$achievementStatus = (isset($status[$achievement->getId()]) ? $status[$achievement->getId()] : false);

					if (($achievement->isSecret() && $achievementStatus === false)
						|| ($achievementStatus !== false && $achievement->isSecret() && !$achievementStatus->isEarned())) {
						//Do not show show secret achievements to regular users.
						continue;
					}

					$categoryHTML[$categoryId] .= TemplateAchievements::achievementBlockRow($achievement, false, $status, $achievements);
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
	 * @param	string	Site Key
	 * @param	integer	Global User ID
	 * @return	string	Built HTML
	 */
	public function achievementBlockPopUp($achievement, $siteKey, $globalId) {
		global $wgAchPointAbbreviation, $wgSitename, $dsSiteKey;

		$achievementsPage = Title::newFromText('Special:Achievements');

		$imageUrl = $achievement->getImageUrl();

		$HTML = "
			<div class='p-achievement-row p-achievement-notice p-achievement-remote' data-hash='{$siteKey}-{$achievement->getId()}'>
				<div class='p-achievement-source'>".($achievement->isGlobal() ? wfMessage('mega_achievement_earned')->escaped() : $wgSitename)."</div>
				<div class='p-achievement-icon'>
					".(!empty($imageUrl) ? "<img src='{$imageUrl}'/>" : "")."
				</div>
				<div class='p-achievement-row-inner'>
					<span class='p-achievement-name'>".htmlentities($achievement->getName($siteKey), ENT_QUOTES)."</span>
					<span class='p-achievement-description'>".htmlentities($achievement->getDescription(), ENT_QUOTES)."</span>
				</div>
				<span class='p-achievement-points'>".$achievement->getPoints()."{$wgAchPointAbbreviation}</span>
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
	 * @param	array	[Optional] AchievementStatus Objects
	 * @param	array	[Optional] All loaded achievements for showing required criteria.
	 * @return	string	Built HTML
	 */
	static public function achievementBlockRow($achievement, $showControls = true, $statuses = [], $achievements = []) {
		global $wgUser, $wgAchPointAbbreviation;

		$status = (isset($statuses[$achievement->getId()]) ? $statuses[$achievement->getId()] : false);

		$imageUrl = $achievement->getImageUrl();

		$HTML = "
			<div class='p-achievement-row".($status !== false && $status->isEarned() ? ' earned' : null).($achievement->isDeleted() ? ' deleted' : null).($achievement->isSecret() ? ' secret' : null)."' data-id='{$achievement->getId()}'>
				<div class='p-achievement-icon'>
					".(!empty($imageUrl) ? "<img src='{$imageUrl}'/>" : "")."
				</div>
				<div class='p-achievement-row-inner'>
					<span class='p-achievement-name'>".htmlentities($achievement->getName(($status !== false && !empty($status->getSite_Key()) ? $status->getSite_Key() : null)), ENT_QUOTES)."</span>
					<span class='p-achievement-description'>".htmlentities($achievement->getDescription(), ENT_QUOTES)."</span>
					<div class='p-achievement-requirements'>";
		if (count($achievement->getRequiredBy())) {
			foreach ($achievement->getRequiredBy() as $requiredByAId) {
				if (isset($achievements[$requiredByAId]) && $achievements[$requiredByAId]->isSecret()) {
					if (!isset($statuses[$requiredByAId]) || !$statuses[$requiredByAId]->isEarned()) {
						continue;
					}
				}
				$_rbInnerHtml .= "
							<span>".(isset($achievements[$requiredByAId]) ? $achievements[$requiredByAId]->getName() : "FATAL ERROR LOADING REQUIRED BY ACHIEVEMENT - PLEASE FIX THIS")."</span>";
			}
			if (!empty($_rbInnerHtml)) {
				$HTML .= "
						<div class='p-achievement-required_by'>
						".wfMessage('required_by')->escaped()."{$_rbInnerHtml}
						</div>";
			}
		}
		if (count($achievement->getCriteria()->getAchievement_Ids())) {
			$HTML .= "
						<div class='p-achievement-requires'>
						".wfMessage('requires')->escaped();
			foreach ($achievement->getCriteria()->getAchievement_Ids() as $requiresAid) {
				$HTML .= "
							<span>".(isset($achievements[$requiresAid]) ? $achievements[$requiresAid]->getName() : "FATAL ERROR LOADING REQUIRED ACHIEVEMENTS - PLEASE FIX THIS")."</span>";
			}
			$HTML .= "
						</div>";
		}
		$HTML .= "
					</div>";
		if ($showControls) {
			$manageAchievementsPage = Title::newFromText('Special:ManageAchievements');
			$manageAchievementsURL = $manageAchievementsPage->getFullURL();
			if (
				$wgUser->isAllowed('achievement_admin') &&
				(MASTER_WIKI === true || (MASTER_WIKI !== true && !$achievement->isProtected() && !$achievement->isGlobal()))
			) {
				if (!$achievement->isDeleted()) {
					$HTML .= "
					<div class='p-achievement-admin'>
						<span class='p-achievement-delete'><a href='{$manageAchievementsURL}/".($achievement->isChild() ? 'revert' : 'delete')."?aid={$achievement->getId()}' class='mw-ui-button".($achievement->isChild() ? '' : ' mw-ui-destructive')."'>".wfMessage(($achievement->isChild() ? 'revert_custom_achievement' : 'delete_achievement'))->escaped()."</a></span>
						<span class='p-achievement-edit'><a href='{$manageAchievementsURL}/edit?aid={$achievement->getId()}' class='mw-ui-button mw-ui-constructive'>".wfMessage('edit_achievement')->escaped()."</a></span>
					</div>";
				} elseif ($achievement->isDeleted() && $wgUser->isAllowed('restore_achievements')) {
					$HTML .= "
					<div class='p-achievement-admin'>
						<span class='p-achievement-restore'><a href='{$manageAchievementsURL}/restore?aid={$achievement->getId()}' class='mw-ui-button'>".wfMessage('restore_achievement')->escaped()."</a></span>
					</div>";
				}
			}
		}
		if ($status !== false && $status->getTotal() > 0 && !$status->isEarned()) {
			$width = ($status->getProgress() / $status->getTotal()) * 100;
			if ($width > 100) {
				$width = 100;
			}
			$HTML .= "
					<div class='p-achievement-progress'>
						<div class='progress-background'><div class='progress-bar' style='width: {$width}%;'></div></div><span>".$status->getProgress()."/{$status->getTotal()}</span>
					</div>";
		}
		if ($status !== false && $status->isEarned()) {
			$timestamp = new MWTimestamp($status->getEarned_At());
			$HTML .= "
					<div class='p-achievement-earned'>
						".$timestamp->getTimestamp(TS_DB)."
					</div>";
		}
		$HTML .= "
				</div>
				<span class='p-achievement-points'>".intval($achievement->getPoints())."{$wgAchPointAbbreviation}</span>
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
