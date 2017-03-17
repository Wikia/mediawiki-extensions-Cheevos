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
			throw new MWException( 'Could not determin the site key for use with Achievements.' );
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

			echo "<pre>";
			var_dump($filter);
			var_dump($categories); 
			die();

		}

		




		$this->output->setPageTitle(wfMessage('achievements')->escaped());
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
		} else {
			$this->achievement = new Cheevos\CheevosAchievement();
		}

		$return = $this->acheivementsSave();

		if ($this->achievement->exists()) {
			$this->output->setPageTitle(wfMessage('edit_achievement')->escaped().' - '.wfMessage('achievements')->escaped().' - '.$this->achievement->getName());
		} else {
			$this->output->setPageTitle(wfMessage('add_achievement')->escaped().' - '.wfMessage('achievements')->escaped());
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
			$this->achievement->setGlobal($this->wgRequest->getBool('global'));
			$this->achievement->setProtected($this->wgRequest->getBool('protected'));

			if (!count($errors)) {
				$success = $this->achievement->save();

				if ($success['code'] == 200) {
					AchievementsHooks::invalidateCache();
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
	public function achievementsDelete($action) {
		if ($this->wgUser->isAllowed('delete_achievements')) {
			$achievementId = $this->wgRequest->getInt('aid');

			if ($this->wgRequest->getInt('aid')) {
				$achievementId = $this->wgRequest->getInt('aid');

				//$achievement = \Achievements\Achievement::newFromId($achievementId);
				$achievement = Cheevos\Cheevos::getAchievement($achievementId);

				if ($achievement === false || $achievementId != $achievement->getId()) {
					$this->output->showErrorPage('achievements_error', 'error_bad_achievement_id');
					return;
				}
			}

			if ($this->wgRequest->getVal('confirm') == 'true') {
				
				Cheevos\Cheevos::deleteAchievement($achievementId);
				AchievementsHooks::invalidateCache();

				$page = Title::newFromText('Special:ManageAchievements');
				$this->output->redirect($page->getFullURL());
				return;
			}

			$this->output->setPageTitle(wfMessage('delete_achievement_title')->escaped().' - '.$achievement->getName());
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
				$errors['username'] = wfMessage('error_award_bad_user')->escaped();
			} else {
				$user = User::newFromName($save['username']);
				$user->load();
				$lookup = CentralIdLookup::factory();
				$globalId = $lookup->centralIdFromLocalUser($user, CentralIdLookup::AUDIENCE_RAW);
				if (!$user || !$user->getId() || !$globalId) {
					$errors['username'] = wfMessage('error_award_bad_user')->escaped();
				}
			}

			
			$save['achievement_id'] = $this->wgRequest->getInt('achievement_id');

			$achievement = Cheevos\Cheevos::getAchievement($save['achievement_id']);
			if ($achievement === false) {
				$errors['achievement_id'] = wfMessage('error_award_bad_achievement')->escaped();
			}
			
			if (!count($errors)) {
				$check = Cheevos\Cheevos::getUserProgress($this->globalId);
				if (!count($check)) {
					// no progress for anything. heh.
					if ($do == 'award') {
						// award it.
						$awarded = Cheevos\Cheevos::putProgress([
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
							$awarded = Cheevos\Cheevos::putProgress([
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

						if ($do == 'award') {
							// award it.
							$awarded = Cheevos\Cheevos::putProgress([
								'achievement_id'	=> $achievement->getId(),
								'site_key'			=> $this->site_key,
								'user_id'			=> $this->globalId,
								'earned'			=> true,
								'manually_award' 	=> true,
								'awarded_at'		=> time(),
								'notified'			=> false
							],$current_progress_id);

						} elseif ($do == 'unaward') {
							// unaward it.
							$awarded = Cheevos\Cheevos::deleteProgress($current_progress_id, $this->globalId);
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
