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

use Cheevos\CheevosHelper;

class TemplateAchievements {
	/**
	 * Achievement List
	 *
	 * @param array	Array of Achievement Object
	 * @param array	Array of Category Information
	 * @param array	Array of User Status for loaded user.
	 * @param object	User
	 * @param integer	User's Global ID
	 *
	 * @return string	Built HTML
	 */
	public function achievementsList($achievements, $categories, $statuses = [], $user, $globalId) {
		$currentUser = RequestContext::getMain()->getUser();
		$manageAchievementsPage = Title::newFromText('Special:ManageAchievements');
		$manageAchievementsURL = $manageAchievementsPage->getFullURL();

		$HTML = '';

		if ($currentUser->isAllowed('achievement_admin')) {
			$HTML .= "
			<div class='button_bar'>
				<div class='button_break'></div>
				<div class='buttons_right'>
					<a href='{$manageAchievementsURL}' class='mw-ui-button'>" . wfMessage('manageachievements') . "</a>
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

					$achievementStatus = (isset($statuses[$achievement->getId()]) ? $statuses[$achievement->getId()] : false);

					if (($achievement->isSecret() && $achievementStatus === false)
						|| ($achievementStatus !== false && $achievement->isSecret() && !$achievementStatus->isEarned())) {
						// Do not show show secret achievements to regular users.
						continue;
					}

					$categoryHTML[$categoryId] .= self::achievementBlockRow($achievement, false, $statuses, $achievements);
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
	 * Generates achievement block to display.
	 *
	 * @param array	Achievement Information
	 * @param string	Site Key
	 * @param integer	Global User ID
	 *
	 * @return string	Built HTML
	 */
	public static function achievementBlockPopUp($achievement, $siteKey, $globalId) {
		global $wgAchPointAbbreviation, $wgExtensionAssetsPath;

		return "
			<div class='reverb-npn-ach'>
				<div class='reverb-npn-ach-text'>
					<div class='reverb-npn-ach-name'>" . htmlentities($achievement->getName($siteKey), ENT_QUOTES) . "</div>
					<div class='reverb-npn-ach-description'>" . htmlentities($achievement->getDescription(), ENT_QUOTES) . "</div>
				</div>
				<div class='reverb-npn-ach-points'>" . $achievement->getPoints() . "<img src=\"{$wgExtensionAssetsPath}{$wgAchPointAbbreviation}\" /></div>
			</div>";
	}

	/**
	 * Generates achievement block to display.
	 *
	 * @param array	Achievement Information
	 * @param boolean	[Optional] Show Controls
	 * @param array	[Optional] AchievementStatus Objects
	 * @param array	[Optional] All loaded achievements for showing required criteria.
	 * @param boolean	[Optional] Show Required By even if hidden by secret.
	 * @param boolean	[Optional] Show revert button.
	 *
	 * @return string	Built HTML
	 */
	public static function achievementBlockRow($achievement, $showControls = true, $statuses = [], $achievements = [], $ignoreHiddenBySecretRequiredBy = false, $showRevert = false) {
		global $wgAchPointAbbreviation, $wgExtensionAssetsPath;

		$user = RequestContext::getMain()->getUser();
		$status = (isset($statuses[$achievement->getId()]) ? $statuses[$achievement->getId()] : false);

		$image = $achievement->getImage();
		$imageUrl = $achievement->getImageUrl();

		$HTML = "
			<div class='p-achievement-row" . ($status !== false && $status->isEarned() ? ' earned' : null) . ($achievement->isDeleted() ? ' deleted' : null) . ($achievement->isSecret() ? ' secret' : null) . "' data-id='{$achievement->getId()}'>
				<div class='p-achievement-icon" . (($showControls && !empty($imageUrl)) ? " edit-on-hover" : null) . "'>
					" . (!empty($imageUrl) ? "<img src='{$imageUrl}' data-img='{$image}'>" : "") . "
					" . (($showControls && !empty($imageUrl)) ? "<span class=\"image-edit-box\" style=\"display: none;\">" . wfMessage('click_to_upload_new_image')->escaped() . "</span>" : null) . "
				</div>
				<div class='p-achievement-row-inner'>
					<span class='p-achievement-name'>" . htmlentities($achievement->getName(($status !== false && !empty($status->getSite_Key()) ? $status->getSite_Key() : null)), ENT_QUOTES) . "</span>
					<span class='p-achievement-description'>" . htmlentities($achievement->getDescription(), ENT_QUOTES) . "</span>
					<div class='p-achievement-requirements'>";
		if (count($achievement->getRequiredBy())) {
			$_rbInnerHtml = '';
			foreach ($achievement->getRequiredBy() as $requiredByAid) {
				if (!isset($achievements[$requiredByAid])) {
					continue;
				}
				if (isset($achievements[$requiredByAid]) && $achievements[$requiredByAid]->isSecret() && !$showControls && !$ignoreHiddenBySecretRequiredBy) {
					if (!isset($statuses[$requiredByAid]) || !$statuses[$requiredByAid]->isEarned()) {
						continue;
					}
				}
				$_rbInnerHtml .= "
							<span>" . (isset($achievements[$requiredByAid]) ? $achievements[$requiredByAid]->getName() : "FATAL ERROR LOADING REQUIRED BY ACHIEVEMENT '{$requiredByAid}'") . "</span>";
			}
			if (!empty($_rbInnerHtml)) {
				$HTML .= "
						<div class='p-achievement-required_by'>
						" . wfMessage('required_by')->escaped() . "{$_rbInnerHtml}
						</div>";
			}
		}
		if (count($achievement->getCriteria()->getAchievement_Ids())) {
			$HTML .= "
						<div class='p-achievement-requires'>
						" . wfMessage('requires')->escaped();
			foreach ($achievement->getCriteria()->getAchievement_Ids() as $requiresAid) {
				if (isset($achievements[$requiresAid])) {
					$HTML .= "
							<span data-id='" . $achievements[$requiresAid]->getId() . "'>" . $achievements[$requiresAid]->getName() . "</span>";
				} else {
					$HTML .= "
							<span data-id=''>FATAL ERROR LOADING REQUIRED ACHIEVEMENT '{$requiresAid}'</span>";
				}
			}
			$HTML .= "
						</div>";
		}
		$HTML .= "
					</div>";
		if ($showControls) {
			$manageAchievementsPage = Title::newFromText('Special:ManageAchievements');
			$manageAchievementsURL = $manageAchievementsPage->getFullURL();
			if ($user->isAllowed('achievement_admin') &&
				(CheevosHelper::isCentralWiki() || (!CheevosHelper::isCentralWiki() && !$achievement->isProtected() && !$achievement->isGlobal()))
			) {
				if (!$achievement->isDeleted()) {
					$HTML .= "
					<div class='p-achievement-admin'>
						" . ($showRevert ? "<span class='p-achievement-revert'><a href='{$manageAchievementsURL}/revert?aid={$achievement->getId()}' class='mw-ui-button'>" . wfMessage('revert_custom_achievement')->escaped() . "</a></span>" : '') . "
						<span class='p-achievement-delete'><a href='{$manageAchievementsURL}/delete?aid={$achievement->getId()}' class='mw-ui-button mw-ui-destructive'>" . wfMessage('delete_achievement')->escaped() . "</a></span>
						<span class='p-achievement-edit'><a href='{$manageAchievementsURL}/edit?aid={$achievement->getId()}' class='mw-ui-button mw-ui-constructive'>" . wfMessage('edit_achievement')->escaped() . "</a></span>
					</div>";
				} elseif ($achievement->isDeleted() && $user->isAllowed('restore_achievements')) {
					$HTML .= "
					<div class='p-achievement-admin'>
						<span class='p-achievement-restore'><a href='{$manageAchievementsURL}/restore?aid={$achievement->getId()}' class='mw-ui-button'>" . wfMessage('restore_achievement')->escaped() . "</a></span>
					</div>";
				}

			}

			if (!CheevosHelper::isCentralWiki() && ($achievement->isProtected() || $achievement->isGlobal())) {
				$HTML .= "<div class='p-achievement-admin'>";
				if ($achievement->isProtected()) {
					$HTML .= "<p>" . wfMessage('edit_disabled_protected')->escaped() . "</p>";
				}
				if ($achievement->isGlobal()) {
					$HTML .= "<p>" . wfMessage('edit_disabled_global')->escaped() . "</p>";
				}
				$HTML .= "</div>";
			}
		}

		if ($status !== false && $status->getTotal() > 0 && !$status->isEarned()) {
			$width = ($status->getProgress() / $status->getTotal()) * 100;
			if ($width > 100) {
				$width = 100;
			}
			$HTML .= "
					<div class='p-achievement-progress'>
						<div class='progress-background'><div class='progress-bar' style='width: {$width}%;'></div></div><span>" . $status->getProgress() . "/{$status->getTotal()}</span>
					</div>";
		}
		if ($status !== false && $status->isEarned()) {
			$timestamp = new MWTimestamp($status->getEarned_At());
			$HTML .= "
					<div class='p-achievement-earned'>
						" . $timestamp->getTimestamp(TS_DB) . "
					</div>";
		}
		$HTML .= "
				</div>
				<span class='p-achievement-points'>" . intval($achievement->getPoints()) . "<img src=\"{$wgExtensionAssetsPath}{$wgAchPointAbbreviation}\" /></span>
			</div>";

		return $HTML;
	}
}
