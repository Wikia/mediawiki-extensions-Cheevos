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
		parent::__construct('ManageAchievements');

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
		$this->globalId = $lookup->centralIdFromLocalUser($this->wgUser, CentralIdLookup::AUDIENCE_RAW);

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
		$achievements = \Cheevos\CheevosAchievement::pruneAchievements($achievements, true, false);

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

		if ($this->wgRequest->getInt('aid')) {
			$achievementId = $this->wgRequest->getInt('aid');

			$this->achievement = Cheevos\Cheevos::getAchievement($achievementId);

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

		$allAchievements = \Cheevos\CheevosAchievement::pruneAchievements(\Cheevos\Cheevos::getAchievements($this->siteKey), true, true);
		$achievement = array_pop(\Cheevos\CheevosAchievement::correctCriteriaChildAchievements([$this->achievement]));

		$this->content = $this->templates->achievementsForm($achievement, \Cheevos\Cheevos::getCategories(), \Cheevos\Cheevos::getKnownHooks(), $allAchievements, $return['errors']);
	}

	/**
	 * Saves submitted achievement forms.
	 *
	 * @access	private
	 * @return	array	Array containing an array of processed form information and array of corresponding errors.
	 */
	private function acheivementsSave() {
		global $achImageDomainWhiteList;

		if ($this->wgRequest->getVal('do') == 'save' && $this->wgRequest->wasPosted()) {
			$forceCreate = false;
			if (!empty($this->siteKey) && empty($this->achievement->getSite_Key()) && $this->achievement->getId() > 0) {
				$forceCreate = true;
				$this->achievement->setParent_Id($this->achievement->getId());
				$this->achievement->setId(0); // <-- do this AFTER setting parent_id... ALEX
			}
			$this->achievement->setSite_Key($this->siteKey);

			$criteria = new \Cheevos\CheevosAchievementCriteria($criteria);
			$criteria->setStats($this->wgRequest->getArray("criteria_stats", []));
			$criteria->setValue($this->wgRequest->getInt("criteria_value"));
			$criteria->setStreak($this->wgRequest->getText("criteria_streak"));
			$criteria->setStreak_Progress_Required($this->wgRequest->getInt("criteria_streak_progress_required"));
			$criteria->setStreak_Reset_To_Zero($this->wgRequest->getBool("criteria_streak_reset_to_zero"));
			$criteria->setPer_Site_Progress_Maximum($this->wgRequest->getInt("criteria_per_site_progress_maximum"));
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

			$category = \Cheevos\Cheevos::getCategory($this->wgRequest->getInt('category_id'));

			if ($category === false) {
				$errors['category'] = wfMessage('error_invalid_achievement_category')->escaped();
			} else {
				if (!$category->exists()) {
					$category->save();
				}
				$this->achievement->setCategory($category);
			}

			$this->achievement->setSecret($this->wgRequest->getBool('secret'));
			if (MASTER_WIKI === true) {
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
		if ($subpage == "restore") {
			// a restore is a delete on a child. This will be fine.
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
				if (empty($achievement->getSite_Key()) && $achievement->getId() > 0) {
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
		$allAchievements = \Cheevos\CheevosAchievement::pruneAchievements(\Cheevos\Cheevos::getAchievements($dsSiteKey), true, true);

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

		$do = strtolower($this->wgRequest->getVal('do'));
		$save = [];
		$errors = [];
		$awarded = null;
		if (($do == 'award' || $do == 'unaward') && $this->wgRequest->wasPosted()) {
			$awarded = false;
			$save['username'] = $this->wgRequest->getVal('username');
			if (empty($save['username'])) {
				$errors['username'][] = wfMessage('error_award_bad_user')->escaped();
			}

			$save['achievement_id'] = $this->wgRequest->getInt('achievement_id');

			$achievement = Cheevos\Cheevos::getAchievement($save['achievement_id']);
			if ($achievement === false) {
				$errors['achievement_id'] = wfMessage('error_award_bad_achievement')->escaped();
			}

			if (!count($errors)) {
				$users = explode(",", $save['username']);
				foreach ($users as $getuser) {
					$user = User::newFromName(trim($getuser));
					$user->load();
					$lookup = CentralIdLookup::factory();
					$globalId = $lookup->centralIdFromLocalUser($user, CentralIdLookup::AUDIENCE_RAW);
					if (!$user || !$user->getId() || !$globalId) {
						$errors['username'][] = "{$getuser}: ".wfMessage('error_award_bad_user')->escaped();
						continue;
					}

					$check = \Cheevos\Cheevos::getAchievementProgress(['user_id' => $this->globalId, 'site_key' => $dsSiteKey]);
					if (!count($check)) {
						// no progress for anything. heh.
						if ($do == 'award') {
							// award it.
							$awarded[] = Cheevos\Cheevos::putProgress(
								[
									'achievement_id'	=> $achievement->getId(),
									'site_key'			=> $dsSiteKey,
									'user_id'			=> $this->globalId,
									'earned'			=> true,
									'manual_award' 		=> true,
									'awarded_at'		=> time(),
									'notified'			=> false
								]
							);
						} else {
							// nothing was there anyway?
							$awarded = true;
						}
					} else {
						// The users has progress on achievements (this is almost always the case)
						// so lets see if they have progress on *this* achievement.
						$current_progress_id = false;
						foreach ($check as $ca) {
							if ($ca->getAchievement_Id() == $achievement->getId()) {
								$current_progress_id = $ca->getId();
							}
						}

						if (!$current_progress_id) {
							// They dont have any current progress for this specific achievement. Same as if they had no progress at all.
							if ($do == 'award') {
								// award it.
								$awarded[] = Cheevos\Cheevos::putProgress(
									[
										'achievement_id'	=> $achievement->getId(),
										'site_key'			=> $dsSiteKey,
										'user_id'			=> $this->globalId,
										'earned'			=> true,
										'manual_award' 		=> true,
										'awarded_at'		=> time(),
										'notified'			=> false
									]
								);
								\CheevosHooks::displayAchievement($achievement);
							} else {
								// nothing was there anyway?
								$awarded[] = true;
							}
						} else {

							if ($do == 'award') {
								// award it.
								$awarded[] = Cheevos\Cheevos::putProgress(
									[
										'achievement_id'	=> $achievement->getId(),
										'site_key'			=> $dsSiteKey,
										'user_id'			=> $this->globalId,
										'earned'			=> true,
										'manual_award' 		=> true,
										'awarded_at'		=> time(),
										'notified'			=> false
									],
									$current_progress_id
								);
								\CheevosHooks::displayAchievement($achievement);

							} elseif ($do == 'unaward') {
								// unaward it.
								$awarded[] = Cheevos\Cheevos::deleteProgress($current_progress_id, $this->globalId);
							}
						}
					}
				}
			}
		}

		return [
			'save'		=> $save,
			'errors'	=> $errors,
			'success'	=> $awarded
		];
	}

	private function invalidateCache() {
		Cheevos\Cheevos::invalidateCache();

		$page = Title::newFromText('Special:ManageAchievements');
		$this->output->redirect($page->getFullURL());
		return;
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
