<?php
/**
 * Cheevos
 * Cheevos Special Page
 *
 * @package   Cheevos
 * @author    Hydra Wiki Platform Team
 * @copyright (c) 2017 Curse Inc.
 * @license   GPL-2.0-or-later
 * @link      https://gitlab.com/hydrawiki/extensions/cheevos
 **/

use Cheevos\Cheevos;
use Cheevos\CheevosAchievement;
use Cheevos\CheevosAchievementCategory;
use Cheevos\CheevosAchievementCriteria;
use Cheevos\CheevosException;
use Cheevos\CheevosHelper;

class SpecialManageAchievements extends SpecialPage {
	/**
	 * Output HTML
	 *
	 * @var string
	 */
	private $content;

	/**
	 * Main Constructor
	 *
	 * @return void
	 */
	public function __construct() {
		parent::__construct('ManageAchievements', 'achievement_admin', $this->getUser()->isAllowed('achievement_admin'));

		$dsSiteKey = CheevosHelper::getSiteKey();

		$this->wgRequest	= $this->getRequest();
		$this->wgUser		= $this->getUser();
		$this->output		= $this->getOutput();
		$this->siteKey		= $dsSiteKey;
		$this->isMaster 	= false;

		if (!$this->siteKey || empty($this->siteKey)) {
			throw new MWException('Could not determined the site key for use for Achievements.');
			return;
		}

		if ($this->siteKey == "master") {
			$this->siteKey = '';
			$this->isMaster = true;
		}
	}

	/**
	 * Main Executor
	 *
	 * @param string	Sub page passed in the URL.
	 *
	 * @return void	[Outputs to screen]
	 */
	public function execute($subpage) {
		$this->templates = new TemplateManageAchievements;
		$this->output->addModuleStyles(['ext.cheevos.styles']);
		$this->output->addModules(['ext.cheevos.js']);
		$this->setHeaders();

		if (!$this->wgUser->isAllowed('edit_achievements')) {
			throw new PermissionsError('edit_achievements');
		}

		switch ($subpage) {
			default:
			case 'view':
				$this->achievementsList();
				break;
			case 'add':
			case 'edit':
			case 'admin':
				$this->achievementsForm();
				break;
			case 'delete':
			case 'restore':
				$this->achievementsDelete($subpage);
				break;
			case 'revert':
				$this->achievementsRevert();
				break;
			case 'award':
				$this->awardForm();
				break;
			case 'invalidatecache':
				$this->invalidateCache();
				break;
		}

		$this->output->addHTML($this->content);
	}

	/**
	 * Cheevos List
	 *
	 * @return void	[Outputs to screen]
	 */
	public function achievementsList() {
		$achievements = Cheevos::getAchievements($this->siteKey);
		$categories = Cheevos::getCategories();

		if ($this->isMaster) {
			foreach ($achievements as $i => $a) {
				if ($a->getSite_Key() !== $this->siteKey) {
					unset($achievements[$i]);
				}
			}
		}

		$filter = $this->wgRequest->getVal('filter');

		if ($filter !== null && !empty($filter)) {
			// @TODO: Make Search Work
			// @IDEA: Make a single "category" called "Search Results" and pass that.$_COOKIE
			// Make it easy on the display logic side?
		}

		$revertHints = [];
		foreach ($achievements as $achievement) {
			if (!$achievement->getParent_Id() || !isset($achievements[$achievement->getParent_Id()])) {
				continue;
			}
			if (!$achievement->sameAs($achievements[$achievement->getParent_Id()])) {
				$revertHints[$achievement->getId()] = true;
			}
		}

		// Fix requires achievement child IDs for display purposes.
		$achievements = CheevosAchievement::correctCriteriaChildAchievements($achievements);
		// Remove achievements that should not be shown in this context.
		list($achievements, ) = CheevosAchievement::pruneAchievements([$achievements, []], true, false);

		$this->output->setPageTitle(wfMessage('manage_achievements')->escaped());
		$this->content = $this->templates->achievementsList($achievements, $categories, $revertHints);
	}

	/**
	 * Achievements Form
	 *
	 * @return void	[Outputs to screen]
	 */
	public function achievementsForm() {
		$this->output->addModules(['ext.achievements.triggerBuilder.js']);

		$allAchievements = Cheevos::getAchievements($this->siteKey);
		$allAchievements = CheevosAchievement::correctCriteriaChildAchievements($allAchievements);
		list($allAchievements, ) = CheevosAchievement::pruneAchievements([$allAchievements, []], false, true);

		if ($this->wgRequest->getInt('aid')) {
			$achievementId = $this->wgRequest->getInt('aid');
			$this->achievement = false;
			if (isset($allAchievements[$achievementId])) {
				$this->achievement = $allAchievements[$achievementId];
			}

			if ($this->achievement === false || $achievementId != $this->achievement->getId()) {
				$this->output->showErrorPage('achievements_error', 'error_bad_achievement_id');
				return;
			}
			if (!CheevosHelper::isCentralWiki() && ($this->achievement->isProtected() || $this->achievement->isGlobal())) {
				$this->output->showErrorPage('achievements_error', 'error_achievement_protected_global');
				return;
			}
		} else {
			$this->achievement = new CheevosAchievement();
		}

		$return = $this->acheivementsSave();

		if ($this->achievement->exists()) {
			$this->output->setPageTitle(wfMessage('edit_achievement')->escaped() . ' - ' . wfMessage('manage_achievements')->escaped() . ' - ' . $this->achievement->getName());
		} else {
			$this->output->setPageTitle(wfMessage('add_achievement')->escaped() . ' - ' . wfMessage('manage_achievements')->escaped());
		}

		$this->content = $this->templates->achievementsForm($this->achievement, Cheevos::getCategories(), $allAchievements, $return['errors']);
	}

	/**
	 * Saves submitted achievement forms.
	 *
	 * @return array	Array containing an array of processed form information and array of corresponding errors.
	 */
	private function acheivementsSave() {
		global $achImageDomainWhiteList;

		$save = [];
		$errors = [];
		if ($this->wgRequest->getVal('do') == 'save' && $this->wgRequest->wasPosted()) {
			$forceCreate = false;
			if (!empty($this->siteKey) && empty($this->achievement->getSite_Key()) && $this->achievement->getId() > 0) {
				$forceCreate = true;
				$this->achievement->setParent_Id($this->achievement->getId());
				$this->achievement->setId(0);
			}
			$this->achievement->setSite_Key($this->siteKey);

			$criteria = new CheevosAchievementCriteria($this->achievement->getCriteria()->toArray());
			$criteria->setStats($this->wgRequest->getArray("criteria_stats", []));
			$criteria->setValue($this->wgRequest->getInt("criteria_value"));
			$criteria->setStreak($this->wgRequest->getText("criteria_streak"));
			$criteria->setStreak_Progress_Required($this->wgRequest->getInt("criteria_streak_progress_required"));
			$criteria->setStreak_Reset_To_Zero($this->wgRequest->getBool("criteria_streak_reset_to_zero"));
			if ($this->siteKey === 'master') {
				$criteria->setPer_Site_Progress_Maximum($this->wgRequest->getInt("criteria_per_site_progress_maximum"));
			}
			$criteria->setDate_Range_Start($this->wgRequest->getInt("date_range_start"));
			$criteria->setDate_Range_End($this->wgRequest->getInt("date_range_end"));
			$criteria->setCategory_Id($this->wgRequest->getInt("criteria_category_id"));
			$criteria->setAchievement_Ids($this->wgRequest->getIntArray("criteria_achievement_ids", []));
			$this->achievement->setCriteria($criteria);

			$name = $this->wgRequest->getText('name');
			if (!$name || strlen($name) > 50) {
				$errors['name'] = wfMessage('error_invalid_achievement_name')->escaped();
			} else {
				$this->achievement->setName($name);
			}

			$description = $this->wgRequest->getText('description');
			if (!$description || strlen($description) > 150) {
				$errors['description'] = wfMessage('error_invalid_achievement_description')->escaped();
			} else {
				$this->achievement->setDescription($description);
			}

			$this->achievement->setImage($this->wgRequest->getVal('image'));
			$this->achievement->setPoints($this->wgRequest->getInt('points'));

			$categoryId = $this->wgRequest->getInt('category_id');
			$categoryName = trim($this->wgRequest->getText('category'));
			$category = Cheevos::getCategory($categoryId);
			$categories = Cheevos::getCategories(true);
			if ($category !== false && $categoryId > 0 && $categoryId == $category->getId() && $categoryName == $category->getName()) {
				$this->achievement->setCategory($category);
			} elseif (!empty($categoryName)) {
				$found = false;
				foreach ($categories as $_categoryId => $_category) {
					if ($categoryName == $_category->getName()) {
						$this->achievement->setCategory($_category);
						$found = true;
						break;
					}
				}
				if (!$found) {
					$globalId = Cheevos::getUserIdForService($this->getUser());

					$category = new CheevosAchievementCategory();
					$category->setName($categoryName);
					$category->setCreated_At(time());
					$category->setCreated_By($globalId);
					$return = $category->save();
					if (isset($return['code']) && $return['code'] !== 200) {
						throw new CheevosException($return['message'], $return['code']);
					}
					if (isset($return['object_id'])) {
						$category = Cheevos::getCategory($return['object_id']);
						$this->achievement->setCategory($category);
					} else {
						$category = false;
					}
				}
			} else {
				$category = false;
			}

			if ($category === false) {
				$errors['category'] = wfMessage('error_invalid_achievement_category')->escaped();
			}

			$this->achievement->setSecret($this->wgRequest->getBool('secret'));
			if ($this->siteKey === 'master') {
				// Set global to true should always happen after setting the site ID and site key.  Otherwise it could create a global achievement with a site ID and site key.
				$this->achievement->setGlobal($this->wgRequest->getBool('global'));
				$this->achievement->setProtected($this->wgRequest->getBool('protected'));
				$this->achievement->setSpecial($this->wgRequest->getBool('special'));
				$this->achievement->setShow_On_All_Sites($this->wgRequest->getBool('show_on_all_sites'));
			}

			if (!count($errors)) {
				$success = $this->achievement->save($forceCreate);

				if ($success['code'] == 200) {
					Cheevos::invalidateCache();
				}

				$page = Title::newFromText('Special:ManageAchievements');
				$this->output->redirect($page->getFullURL());
				return;
			}

			if ($this->wgUser->isAllowed('edit_meta_achievements')) {
				$save['requires'] = $requiredAchievements;
			}
		}
		return [
			'save' => $save,
			'errors' => $errors
		];
	}

	/**
	 * Achievements Revert
	 *
	 * @return void	[Outputs to screen]
	 */
	public function achievementsRevert() {
		$achievementId = $this->wgRequest->getInt('aid');

		if ($achievementId) {
			$achievement = Cheevos::getAchievement($achievementId);

			if ($achievement === false || $achievementId != $achievement->getId()) {
				$this->output->showErrorPage('achievements_error', 'error_bad_achievement_id');
				return;
			}
		}

		if ($achievement->isDeleted() && !$this->getUser()->isAllowed('restore_achievements')) {
			throw new PermissionsError('restore_achievements');
		}

		if (!$achievement->getParent_Id()) {
			$this->output->showErrorPage('achievements_error', 'error_achievement_unrevertable');
		}

		$parentAch = Cheevos::getAchievement($achievement->getParent_Id());

		if ($parentAch === false || $achievement->getParent_Id() != $parentAch->getId()) {
			$this->output->showErrorPage('achievements_error', 'error_bad_achievement_parent_id');
			return;
		}

		if ($this->wgRequest->getVal('confirm') == 'true' && $this->wgRequest->wasPosted()) {
			$globalId = Cheevos::getUserIdForService($this->wgUser);
			if (!$globalId) {
				throw new MWException('Could not obtain the global ID for the user attempting to revert an achievement.');
			}

			foreach (['name', 'description', 'image', 'category', 'points', 'global', 'protected', 'secret', 'special', 'show_on_all_sites', 'created_at', 'updated_at', 'deleted_at', 'created_by', 'updated_by', 'deleted_by', 'criteria'] as $field) {
				$achievement[$field] = $parentAch[$field];
			}

			$success = $achievement->save(false);

			if ($success['code'] == 200) {
				Cheevos::invalidateCache();
			}

			$page = Title::newFromText('Special:ManageAchievements');
			$this->output->redirect($page->getFullURL());
			return;
		}

		$this->output->setPageTitle(wfMessage('revert_achievement_title')->escaped() . ' - ' . $achievement->getName());
		$this->content = $this->templates->achievementStateChange($achievement, 'revert');
	}

	/**
	 * Achievements Delete/Restore
	 *
	 * @param string	Delete or Restore action take.
	 *
	 * @return void	[Outputs to screen]
	 */
	public function achievementsDelete($action) {
		if ($action == 'delete' && !$this->wgUser->isAllowed('delete_achievements')) {
			throw new PermissionsError('delete_achievements');
		}
		if ($action == 'restore' && !$this->wgUser->isAllowed('restore_achievements')) {
			throw new PermissionsError('restore_achievements');
		}
		if ($this->wgUser->isAllowed('delete_achievements') || $this->wgUser->isAllowed('restore_achievements')) {
			$achievementId = $this->wgRequest->getInt('aid');

			if ($achievementId) {
				$achievement = Cheevos::getAchievement($achievementId);

				if ($achievement === false || $achievementId != $achievement->getId()) {
					$this->output->showErrorPage('achievements_error', 'error_bad_achievement_id');
					return;
				}
			}

			if ($this->wgRequest->getVal('confirm') == 'true' && $this->wgRequest->wasPosted()) {
				$globalId = Cheevos::getUserIdForService($this->wgUser);
				if (!$globalId) {
					throw new MWException('Could not obtain the global ID for the user attempting to ' . $action . ' an achievement.');
				}
				$forceCreate = false;
				if (!$achievement->getParent_Id() && !$this->isMaster) {
					$forceCreate = true;
					$achievement->setParent_Id($achievement->getId());
					$achievement->setId(0);
				}
				$achievement->setSite_Key($this->siteKey);
				$achievement->setDeleted_At(($action == 'restore' ? 0 : time()));
				$achievement->setDeleted_By(($action == 'restore' ? 0 : $globalId));

				$success = $achievement->save($forceCreate);

				if ($success['code'] == 200) {
					Cheevos::invalidateCache();
				}

				$page = Title::newFromText('Special:ManageAchievements');
				$this->output->redirect($page->getFullURL());
				return;
			}

			$this->output->setPageTitle(wfMessage($action . '_achievement_title')->escaped() . ' - ' . $achievement->getName());
			$this->content = $this->templates->achievementStateChange($achievement, $action);
		}
	}

	/**
	 * Award Form
	 *
	 * @return void	[Outputs to screen]
	 */
	public function awardForm() {
		if (!$this->getUser()->isAllowed('award_achievements')) {
			throw new PermissionsError('award_achievements');
		}
		$this->checkPermissions();

		$return = $this->awardSave();

		// Using the 'master' site key for the awarding form.
		list($allAchievements, ) = CheevosAchievement::pruneAchievements([Cheevos::getAchievements($this->siteKey), []], true, true);

		$this->output->setPageTitle(wfMessage('awardachievement')->escaped());
		$this->content = $this->templates->awardForm($return, $allAchievements);
	}

	/**
	 * Saves submitted award forms.
	 *
	 * @return array	Array containing an array of processed form information and array of corresponding errors.
	 */
	private function awardSave() {
		$do = strtolower($this->wgRequest->getText('do', '')); // This will break logic below if "Award" and "Unaward" are ever localized.  --Alexia 2017-04-07
		$save = [];
		$errors = [];
		$awarded = null;
		if (($do === 'award' || $do === 'unaward') && $this->wgRequest->wasPosted()) {
			$awarded = false;
			$save['username'] = $this->wgRequest->getVal('username');
			if (empty($save['username'])) {
				$errors[] = [
					'username' => $save['username'],
					'message' => wfMessage('error_award_bad_user')->escaped()
				];
			}

			$save['achievement_id'] = $this->wgRequest->getInt('achievement_id');

			$achievement = Cheevos::getAchievement($save['achievement_id']);
			if ($achievement === false) {
				$errors[] = [
					'username' => $save['username'],
					'message' => wfMessage('error_award_bad_achievement')->escaped()
				];
			}

			if (!count($errors)) {
				$users = explode(",", $save['username']);
				foreach ($users as $getUser) {
					$user = User::newFromName(trim($getUser));
					$user->load();
					$globalId = Cheevos::getUserIdForService($user);
					if (!$user || !$user->getId() || !$globalId) {
						$errors[] = [
							'username' => $getUser,
							'message' => wfMessage('error_award_bad_user')->escaped()
						];
						continue;
					}

					$award = [];

					$currentProgress = Cheevos::getAchievementProgress(['user_id' => $globalId, 'achievement_id' => $achievement->getId(), 'site_key' => $this->siteKey]);
					if (is_array($currentProgress)) {
						$currentProgress = array_pop($currentProgress);
					} else {
						$currentProgress = null;
					}
					if (!$currentProgress && $do === 'award') {
						try {
							$award = Cheevos::putProgress(
								[
									'achievement_id'	=> $achievement->getId(),
									'site_key'			=> (!$achievement->isGlobal() ? $this->siteKey : ''),
									'user_id'			=> $globalId,
									'earned'			=> true,
									'manual_award' 		=> true,
									'awarded_at'		=> time(),
									'notified'			=> false
								]
							);
							CheevosHooks::broadcastAchievement($achievement, $this->siteKey, $globalId);
							Hooks::run('AchievementAwarded', [$achievement, $globalId]);
						} catch (CheevosException $e) {
							$errors[] = [
								'username' => $save['username'],
								'message' => "There was an API failure attempting to putProgress: " . $e->getMessage()
							];
						}

					} elseif ($do === 'award') {
						$award = ['message' => 'nochange'];
					}

					if ($currentProgress !== null && $currentProgress->getId() && $do === 'unaward') {
						try {
							$award = Cheevos::deleteProgress($currentProgress->getId(), $globalId);
							Hooks::run('AchievementUnawarded', [$achievement, $globalId]);
						} catch (CheevosException $e) {
							$errors[] = [
								'username' => $save['username'],
								'message' => "There was an API failure attempting to deleteProgress: " . $e->getMessage()
							];
						}

					} elseif ($do === 'unaward') {
						$award = ['message' => 'nochange'];
					}

					$award['username'] = $user->getName();
					$awarded[] = $award;
				}
			}
		}

		return [
			'save'		=> $save,
			'errors'	=> $errors,
			'success'	=> $awarded
		];
	}

	/**
	 * Invalidates the cache
	 *
	 * @return void
	 */
	private function invalidateCache() {
		Cheevos::invalidateCache();

		$page = Title::newFromText('Special:ManageAchievements');
		$this->output->redirect($page->getFullURL());
		return;
	}

	/**
	 * Hides special page from SpecialPages special page.
	 *
	 * @return boolean
	 */
	public function isListed() {
		if ($this->wgUser->isAllowed('achievement_admin')) {
			return true;
		}
		return false;
	}

	/**
	 * Lets others determine that this special page is restricted.
	 *
	 * @return boolean	True
	 */
	public function isRestricted() {
		return true;
	}

	/**
	 * Return the group name for this special page.
	 *
	 * @return string
	 */
	protected function getGroupName() {
		return 'users';
	}
}
