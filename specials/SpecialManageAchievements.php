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
		parent::__construct('ManageAchievements');

		$this->wgRequest	= $this->getRequest();
		$this->wgUser		= $this->getUser();
		$this->output		= $this->getOutput();
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

		$site_id = 0; // int | The site id to use for locally overridden achievements.

		$achievements = Cheevos\Cheevos::getAchievements($site_id);
		$categories = Cheevos\Cheevos::getCategories();

		// @TODO: Figure out what this stuff does.
		$hide['deleted'] = true;
		$hide['secret'] = true;

		$lookup = CentralIdLookup::factory();
		$globalId = $lookup->centralIdFromLocalUser($this->wgUser, CentralIdLookup::AUDIENCE_RAW);
		$progress = \Achievements\Progress::newFromGlobalId($globalId);

		$searchTerm = '';

		$this->output->setPageTitle(wfMessage('achievements')->escaped());
		$this->content = $this->templates->achievementsList($achievements, $categories, $progress, $hide, $searchTerm);
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
		Cheevos\Cheevos::getAchievements($site_id), $return['errors']);
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

			/*
			$imageUrl = $this->wgRequest->getText('image_url');
			if (!$imageUrl || !filter_var($imageUrl, FILTER_VALIDATE_URL)) {
				$errors['image_url'] = wfMessage('error_invalid_achievement_image_url')->escaped();
			} else {
				if (is_array($achImageDomainWhiteList) && count($achImageDomainWhiteList)) {
					$host = parse_url($imageUrl, PHP_URL_HOST);
					$approvedImageDomain = false;
					foreach ($achImageDomainWhiteList as $domain) {
						if (strpos($host, $domain) !== false) {
							$approvedImageDomain = true;
						}
					}
					if (!$approvedImageDomain) {
						$errors['image_url'] = wfMessage('error_invalid_achievement_url_domain')->escaped();
					} else {
						$this->achievement->setImageUrl($imageUrl);
					}
				} else {
					$this->achievement->setImageUrl($imageUrl);
				}
			}*/

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

			//$this->achievement->setPartOfDefaultMega($this->wgRequest->getBool('part_of_default_mega'));
			//$this->achievement->setManuallyAwarded($this->wgRequest->getBool('manual_award'));

			/*if ($this->wgUser->isAllowed('edit_achievement_triggers')) {
				$rules = null;
				$triggers = @json_decode($this->wgRequest->getText('triggers'), true);
				if (!is_array($triggers)) {
					$triggers = [];
				}
				$this->achievement->setTriggers($triggers);

				$this->achievement->setIncrement($this->wgRequest->getInt('increment'));
			}*/

			/*if ($this->wgUser->isAllowed('edit_meta_achievements')) {
				$requiredAchievements = $this->wgRequest->getArray('required_achievements');

				if (is_array($requiredAchievements)) {
					foreach ($requiredAchievements as $key => $achievementId) {
						$achievements = \Achievements\Achievement::newFromId($achievementId);
						if ($achievements === false || !$achievements->exists()) {
							unset($requiredAchievements[$key]);
						}
					}
				} else {
					$requiredAchievements = [];
				}
				$this->achievement->setRequires($requiredAchievements);
			}*/

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
	 * Return the group name for this special page.
	 *
	 * @access protected
	 * @return string
	 */
	protected function getGroupName() {
		return 'users';
	}
}
