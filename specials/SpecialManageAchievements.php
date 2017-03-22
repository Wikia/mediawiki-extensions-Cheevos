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
		$this->site_key 	= $dsSiteKey;

		if (!$dsSiteKey || empty($dsSiteKey)) {
			throw new MWException('Could not determined the site key for use for Achievements.');
			return;
		}

		if ($this->site_key == "master") {
			$this->site_key = null;
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
			case 'award':
				//$this->getUser()->isAllowed('award_achievements') <-- check this here.
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
		$achievements = Cheevos\Cheevos::getAchievements($this->site_key);
		$categories = Cheevos\Cheevos::getCategories();

		$filter = $this->wgRequest->getVal('filter');

		if ($filter !== NULL && !empty($filter)) {
			// @TODO: Make Search Work
			// @IDEA: Make a single "category" called "Search Results" and pass that.$_COOKIE
			// Make it easy on the display logic side?
		}

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
		if (!$this->wgUser->isAllowed('edit_achievements')) {
			throw new PermissionsError('edit_achievements');
			return;
		}

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
		$this->content = $this->templates->achievementsForm($this->achievement, Cheevos\Cheevos::getCategories(), Cheevos\Cheevos::getKnownHooks(),
		Cheevos\Cheevos::getAchievements($this->site_key), $return['errors']);
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
			$criteria = [];
			$criteria['stats'] = $this->wgRequest->getArray("criteria_stats", []);
			$criteria['value'] = $this->wgRequest->getInt("criteria_value");
			$criteria['streak'] = $this->wgRequest->getText("criteria_streak");
			$criteria['streak_progress_required'] = $this->wgRequest->getInt("criteria_streak_progress_required");
			$criteria['streak_reset_to_zero'] = $this->wgRequest->getBool("criteria_streak_reset_to_zero");
			$criteria['per_site_progress_maximum'] = $this->wgRequest->getInt("criteria_per_site_progress_maximum");
			$criteria['category_id'] = $this->wgRequest->getInt("criteria_category_id");
			$criteria['achievement_ids'] = $this->wgRequest->getIntArray("criteria_achievement_ids",[]);

			$forceCreate = false;
			if (empty($this->achievement->getSite_Key()) && $this->achievement->getId() > 0) {
				$forceCreate = true;
				$this->achievement->setParent_Id($this->achievement->getId());
			}
			$this->achievement->setSite_Key($this->site_key);

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

			$category = Cheevos\Cheevos::getCategory($this->wgRequest->getInt('category_id'));

			if ($category === false) {
				$errors['category'] = wfMessage('error_invalid_achievement_category')->escaped();
			} else {
				if (!$category->exists()) {
					$category->save();
				}
				$this->achievement->setCategory($category->toArray());
			}

			$this->achievement->setSecret($this->wgRequest->getBool('secret'));
			if (MASTER_WIKI === true) {
				//Set global to true should always happen after setting the site ID and site key.  Otherwise it could create a global achievement with a site ID and site key.
				$this->achievement->setGlobal($this->wgRequest->getBool('global'));
				$this->achievement->setProtected($this->wgRequest->getBool('protected'));
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
				$achievement->setSite_Key($this->site_key);
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

			$this->output->setPageTitle(wfMessage(($subpage == 'restore' ? 'restore' : 'delete').'_achievement_title')->escaped().' - '.$achievement->getName());
			$this->content = $this->templates->achievementsDelete($achievement);
		}
	}

	/**
	 * Award Form
	 *
	 * @access	public
	 * @return	void	[Outputs to screen]
	 */
	public function awardForm() {
		$this->checkPermissions();

		$return = $this->awardSave();

		$this->output->setPageTitle(wfMessage('awardachievement')->escaped());
		$this->content = $this->templates->awardForm($return, Cheevos\Cheevos::getAchievements($this->site_key));
	}

	/**
	 * Saves submitted award forms.
	 *
	 * @access	private
	 * @return	array	Array containing an array of processed form information and array of corresponding errors.
	 */
	private function awardSave() {
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

				$users = explode(",",$save['username']);
				foreach ($users as $getuser) {

					$user = User::newFromName(trim($getuser));
					$user->load();
					$lookup = CentralIdLookup::factory();
					$globalId = $lookup->centralIdFromLocalUser($user, CentralIdLookup::AUDIENCE_RAW);
					if (!$user || !$user->getId() || !$globalId) {
						$errors['username'][] = "{$getuser}: ".wfMessage('error_award_bad_user')->escaped();
						continue;
					}

					$check = Cheevos\Cheevos::getUserProgress($this->globalId);
					if (!count($check)) {
						// no progress for anything. heh.
						if ($do == 'award') {
							// award it.
							$awarded[] = Cheevos\Cheevos::putProgress([
								'achievement_id'	=> $achievement->getId(),
								'site_key'			=> $this->site_key,
								'user_id'			=> $this->globalId,
								'earned'			=> true,
								'manually_award' 	=> true,
								'awarded_at'		=> time(),
								'notified'			=> false
							]);
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
								$awarded[] = Cheevos\Cheevos::putProgress([
									'achievement_id'	=> $achievement->getId(),
									'site_key'			=> $this->site_key,
									'user_id'			=> $this->globalId,
									'earned'			=> true,
									'manually_award' 	=> true,
									'awarded_at'		=> time(),
									'notified'			=> false
								]);
								\CheevosHooks::displayAchievement($achievement);
							} else {
								// nothing was there anyway?
								$awarded[] = true;
							}
						} else {

							if ($do == 'award') {
								// award it.
								$awarded[] = Cheevos\Cheevos::putProgress([
									'achievement_id'	=> $achievement->getId(),
									'site_key'			=> $this->site_key,
									'user_id'			=> $this->globalId,
									'earned'			=> true,
									'manually_award' 	=> true,
									'awarded_at'		=> time(),
									'notified'			=> false
								],$current_progress_id);
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
