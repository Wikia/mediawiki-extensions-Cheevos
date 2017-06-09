<?php
/**
 * Cheevos
 * Cheevos Special Page
 *
 * @author		Wiki Platform Team
 * @copyright	(c) 2017 Curse Inc.
 * @license		All Rights Reserved
 * @package		Cheevos
 * @link		http://www.curse.com/
 *
 **/

class SpecialManageAchievements extends SpecialPage {
	/**
	 * Output HTML
	 *
	 * @var		string
	 */
	private $content;

	/**
	 * Main Constructor
	 *
	 * @access	public
	 * @return	void
	 */
	public function __construct() {
		global $dsSiteKey;

		parent::__construct('ManageAchievements', 'achievement_admin', $this->getUser()->isAllowed('achievement_admin'));

		$this->wgRequest	= $this->getRequest();
		$this->wgUser		= $this->getUser();
		$this->output		= $this->getOutput();
		$this->siteKey		= $dsSiteKey;
		$this->isMaster 	= false;

		if (!$dsSiteKey || empty($dsSiteKey)) {
			throw new MWException('Could not determined the site key for use for Achievements.');
			return;
		}

		if ($this->siteKey == "master") {
			$this->siteKey = '';
			$this->isMaster = true;
		}

		$lookup = CentralIdLookup::factory();
	}

	/**
	 * Main Executor
	 *
	 * @access	public
	 * @param	string	Sub page passed in the URL.
	 * @return	void	[Outputs to screen]
	 */
	public function execute($subpage) {
		$this->templates = new TemplateManageAchievements;
		$this->output->addModules(['ext.cheevos.styles', 'ext.cheevos.js']);
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
			case 'revert':
			case 'restore':
				$this->achievementsDelete($subpage);
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
	 * @access	public
	 * @return	void	[Outputs to screen]
	 */
	public function achievementsList() {
		$achievements = Cheevos\Cheevos::getAchievements($this->siteKey);
		$categories = Cheevos\Cheevos::getCategories();

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

		//Fix requires achievement child IDs for display purposes.
		$achievements = \Cheevos\CheevosAchievement::correctCriteriaChildAchievements($achievements);
		//Remove achievements that should not be shown in this context.
		list($achievements, ) = \Cheevos\CheevosAchievement::pruneAchievements([$achievements, []], true, false);

		$this->output->setPageTitle(wfMessage('manage_achievements')->escaped());
		$this->content = $this->templates->achievementsList($achievements, $categories);
	}

	/**
	 * Achievements Form
	 *
	 * @access	public
	 * @return	void	[Outputs to screen]
	 */
	public function achievementsForm() {
		$side_id = 0;

		$this->output->addModules(['ext.achievements.triggerBuilder.js']);

		$allAchievements = \Cheevos\Cheevos::getAchievements($this->siteKey);
		$allAchievements = \Cheevos\CheevosAchievement::correctCriteriaChildAchievements($allAchievements);
		list($allAchievements, ) = \Cheevos\CheevosAchievement::pruneAchievements([$allAchievements, []], true, true);

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
			if (MASTER_WIKI !== true && ($this->achievement->isProtected() || $this->achievement->isGlobal())) {
				$this->output->showErrorPage('achievements_error', 'error_achievement_protected_global');
				return;
			}
		} else {
			$this->achievement = new \Cheevos\CheevosAchievement();
		}

		$return = $this->acheivementsSave();

		if ($this->achievement->exists()) {
			$this->output->setPageTitle(wfMessage('edit_achievement')->escaped().' - '.wfMessage('manage_achievements')->escaped().' - '.$this->achievement->getName());
		} else {
			$this->output->setPageTitle(wfMessage('add_achievement')->escaped().' - '.wfMessage('manage_achievements')->escaped());
		}

		$this->content = $this->templates->achievementsForm($this->achievement, \Cheevos\Cheevos::getCategories(), $allAchievements, $return['errors']);
	}

	/**
	 * Saves submitted achievement forms.
	 *
	 * @access	private
	 * @return	array	Array containing an array of processed form information and array of corresponding errors.
	 */
	private function acheivementsSave() {
		global $achImageDomainWhiteList, $dsSiteKey;

		if ($this->wgRequest->getVal('do') == 'save' && $this->wgRequest->wasPosted()) {
			$forceCreate = false;
			if (!empty($this->siteKey) && empty($this->achievement->getSite_Key()) && $this->achievement->getId() > 0) {
				$forceCreate = true;
				$this->achievement->setParent_Id($this->achievement->getId());
				$this->achievement->setId(0);
			}
			$this->achievement->setSite_Key($this->siteKey);

			$criteria = new \Cheevos\CheevosAchievementCriteria($criteria);
			$criteria->setStats($this->wgRequest->getArray("criteria_stats", []));
			$criteria->setValue($this->wgRequest->getInt("criteria_value"));
			$criteria->setStreak($this->wgRequest->getText("criteria_streak"));
			$criteria->setStreak_Progress_Required($this->wgRequest->getInt("criteria_streak_progress_required"));
			$criteria->setStreak_Reset_To_Zero($this->wgRequest->getBool("criteria_streak_reset_to_zero"));
			if ($dsSiteKey === 'master') {
				$criteria->setPer_Site_Progress_Maximum($this->wgRequest->getInt("criteria_per_site_progress_maximum"));
			}
			//$criteria->setDate_Range_Start();
			//$criteria->setDate_Range_End();
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
			$category = \Cheevos\Cheevos::getCategory($categoryId);
			$categories = Cheevos\Cheevos::getCategories(true);
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
					$lookup = CentralIdLookup::factory();
					$globalId = $lookup->centralIdFromLocalUser($this->getUser(), CentralIdLookup::AUDIENCE_RAW);

					$category = new \Cheevos\CheevosAchievementCategory();
					$category->setName($categoryName);
					$category->setCreated_At(time());
					$category->setCreated_By($globalId);
					$return = $category->save();
					if (isset($return['code']) && $return['code'] !== 200) {
						throw new \Cheeovs\CheevosException($return['message'], $return['code']);
					}
					if (isset($return['object_id'])) {
						$category = \Cheevos\Cheevos::getCategory($return['object_id']);
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
			if ($dsSiteKey === 'master') {
				//Set global to true should always happen after setting the site ID and site key.  Otherwise it could create a global achievement with a site ID and site key.
				$this->achievement->setGlobal($this->wgRequest->getBool('global'));
				$this->achievement->setProtected($this->wgRequest->getBool('protected'));
				$this->achievement->setSpecial($this->wgRequest->getBool('special'));
				$this->achievement->setShow_On_All_Sites($this->wgRequest->getBool('show_on_all_sites'));
			}

			if (!count($errors)) {
				$success = $this->achievement->save($forceCreate);

				if ($success['code'] == 200) {
					Cheevos\Cheevos::invalidateCache();
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
	 * Achievements Delete
	 *
	 * @access	public
	 * @param	string	Delete or Restore action take.
	 * @return	void	[Outputs to screen]
	 */
	public function achievementsDelete($subpage) {
		$action = $subpage; // saving original intent for language strings.
		if ($subpage == "revert") {
			// a revert is a delete on a child. This will be fine.
			$subpage = "delete";
		}

		if ($subpage == 'delete' && !$this->wgUser->isAllowed('delete_achievements')) {
			throw new PermissionsError('delete_achievements');
		}
		if ($subpage == 'restore' && !$this->wgUser->isAllowed('restore_achievements')) {
			throw new PermissionsError('restore_achievements');
		}
		if ($this->wgUser->isAllowed('delete_achievements') || $this->wgUser->isAllowed('restore_achievements')) {
			$achievementId = $this->wgRequest->getInt('aid');

			if ($achievementId) {
				$achievement = \Cheevos\Cheevos::getAchievement($achievementId);

				if ($achievement === false || $achievementId != $achievement->getId()) {
					$this->output->showErrorPage('achievements_error', 'error_bad_achievement_id');
					return;
				}
			}

			if ($this->wgRequest->getVal('confirm') == 'true') {
				$lookup = CentralIdLookup::factory();
				$globalId = $lookup->centralIdFromLocalUser($this->wgUser, CentralIdLookup::AUDIENCE_RAW);
				if (!$globalId) {
					throw new MWException('Could not obtain the global ID for the user attempting to delete an achievement.');
				}
				$forceCreate = false;
				if (empty($achievement->getSite_Key()) && $achievement->getId() > 0 && !$this->isMaster) {
					$forceCreate = true;
					$achievement->setParent_Id($achievement->getId());
				}
				$achievement->setSite_Key($this->siteKey);
				$achievement->setDeleted_At(($subpage == 'restore' ? 0 : time()));
				$achievement->setDeleted_By(($subpage == 'restore' ? 0 : $globalId));

				$success = $achievement->save($forceCreate);

				if ($success['code'] == 200) {
					\Cheevos\Cheevos::invalidateCache();
				}

				$page = Title::newFromText('Special:ManageAchievements');
				$this->output->redirect($page->getFullURL());
				return;
			}

			$this->output->setPageTitle(wfMessage($action.'_achievement_title')->escaped().' - '.$achievement->getName());
			$this->content = $this->templates->achievementsDelete($achievement,$action);
		}
	}

	/**
	 * Award Form
	 *
	 * @access	public
	 * @return	void	[Outputs to screen]
	 */
	public function awardForm() {
		global $dsSiteKey;

		if (!$this->getUser()->isAllowed('award_achievements')) {
			throw new PermissionsError('award_achievements');
		}
		$this->checkPermissions();

		$return = $this->awardSave();

		//Using the 'master' site key for the awarding form.
		list($allAchievements, ) = \Cheevos\CheevosAchievement::pruneAchievements([\Cheevos\Cheevos::getAchievements($dsSiteKey), []], true, true);

		$this->output->setPageTitle(wfMessage('awardachievement')->escaped());
		$this->content = $this->templates->awardForm($return, $allAchievements);
	}

	/**
	 * Saves submitted award forms.
	 *
	 * @access	private
	 * @return	array	Array containing an array of processed form information and array of corresponding errors.
	 */
	private function awardSave() {
		global $dsSiteKey;

		$do = strtolower($this->wgRequest->getText('do', '')); //This will break logic below if "Award" and "Unaward" are ever localized.  --Alexia 2017-04-07
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

			$achievement = \Cheevos\Cheevos::getAchievement($save['achievement_id']);
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
					$lookup = CentralIdLookup::factory();
					$globalId = $lookup->centralIdFromLocalUser($user, CentralIdLookup::AUDIENCE_RAW);
					if (!$user || !$user->getId() || !$globalId) {
						$errors[] = [
							'username' => $getUser, 
							'message' => wfMessage('error_award_bad_user')->escaped()
						];
						continue;
					}

					$award = [];

					$currentProgress = \Cheevos\Cheevos::getAchievementProgress(['user_id' => $globalId, 'achievement_id' => $achievement->getId(), 'site_key' => $dsSiteKey]);
					if (is_array($currentProgress)) {
						$currentProgress = array_pop($currentProgress);
					} else {
						$currentProgress = null;
					}
					if (!$currentProgress && $do === 'award') {
						try {
							$award = Cheevos\Cheevos::putProgress(
								[
									'achievement_id'	=> $achievement->getId(),
									'site_key'			=> (!$achievement->isGlobal() ? $dsSiteKey : ''),
									'user_id'			=> $globalId,
									'earned'			=> true,
									'manual_award' 		=> true,
									'awarded_at'		=> time(),
									'notified'			=> false
								]
							);
							\CheevosHooks::displayAchievement($achievement, $dsSiteKey, $globalId);
							Hooks::run('AchievementAwarded', [$achievement, $globalId]);
						} catch (CheevosException $e) {
							$errors[] = [
								'username' => $save['username'], 
								'message' => "There was an API failure attempting to putProgress: ".$e->getMessage()
							];
						}
						
					} elseif ($do === 'award') {
						$award = ['message'=>'nochange'];
					}

					if ($currentProgress !== null && $currentProgress->getId() && $do === 'unaward') {
						try {
							$award = Cheevos\Cheevos::deleteProgress($currentProgress->getId(), $globalId);
							Hooks::run('AchievementUnawarded', [$achievement, $globalId]);
						} catch (CheevosException $e) {
							$errors[] = [
								'username' => $save['username'], 
								'message' => "There was an API failure attempting to deleteProgress: ".$e->getMessage()
							];
						}

					} elseif ($do === 'unaward') {
						$award = ['message'=>'nochange'];
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
		Cheevos\Cheevos::invalidateCache();

		$page = Title::newFromText('Special:ManageAchievements');
		$this->output->redirect($page->getFullURL());
		return;
	}

	/**
	 * Hides special page from SpecialPages special page.
	 *
	 * @access	public
	 * @return	boolean
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
	 * @access	public
	 * @return	boolean	True
	 */
	public function isRestricted() {
		return true;
	}

	/**
	 * Return the group name for this special page.
	 *
	 * @access protected
	 * @return string
	 */
	protected function getGroupName() {
		return 'users';
	}
}
