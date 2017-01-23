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

class SpecialAchievements extends SpecialPage {
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
		parent::__construct('Achievements');

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
		$this->templates = new TemplateAchievements;

		$this->output->addModules(['ext.achievements.styles', 'ext.achievements.js']);

		$this->setHeaders();

		if (CheevosHooks::inMaintenance()) {
			$this->output->showErrorPage('achievements_error', 'error_maintenance_mode');
			return;
		}

		switch ($subpage) {
			default:
			case 'achievements':
				$this->achievementsList();
				break;
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
		$hide['deleted'] = true;
		$hide['secret'] = true;
		$achievements = \Cheevos\Achievement::getAll(true);

		$searchTerm = '';
		if ($this->wgUser->isAllowed('achievement_admin')) {
			$hide['secret'] = false;
			if (count($achievements)) {
				if ($this->wgRequest->getVal('do') == 'resetSearch') {
					$this->wgRequest->response()->setcookie('achievementSearchTerm', '', 1);
				} else {
					$listSearch = $this->wgRequest->getVal('list_search');
					$cookieSearch = $this->wgRequest->getCookie('achievementSearchTerm');
					if (($this->wgRequest->getVal('do') == 'search' && !empty($listSearch)) || !empty($cookieSearch)) {
						if (!empty($cookieSearch) && empty($listSearch)) {
							$searchTerm = $this->wgRequest->getCookie('achievementSearchTerm');
						} else {
							$searchTerm = $this->wgRequest->getVal('list_search');
						}
						$achievements = CheevosHooks::searchByObjectValue($achievements, ['name', 'description'], $searchTerm);
						$this->wgRequest->response()->setcookie('achievementSearchTerm', $searchTerm, $cookieExpire);
					}
				}
			}

			if ($this->wgRequest->getVal('hide_deleted') == 'false' || ($this->wgRequest->getCookie('hideDeletedAchievements') == 'false' && $this->wgRequest->getVal('hide_deleted') != 'true')) {
				$hide['deleted'] = false;
				$this->wgRequest->response()->setcookie('hideDeletedAchievements', 'false');
			} elseif ($this->wgRequest->getVal('hide_deleted') == 'true') {
				$this->wgRequest->response()->setcookie('hideDeletedAchievements', 'true');
			}
		}

		if ($hide['deleted'] || $hide['secret']) {
			foreach ($achievements as $aid => $achievement) {
				if (($achievement->isDeleted() == 1 && $hide['deleted']) || ($achievement->isSecret() == 1 && $hide['secret'])) {
					unset($achievements[$aid]);
				}
			}
		}

		$lookup = CentralIdLookup::factory();
		$globalId = $lookup->centralIdFromLocalUser($this->wgUser, CentralIdLookup::AUDIENCE_RAW);
		$progress = \Cheevos\Progress::newFromGlobalId($globalId);

		$this->output->setPageTitle(wfMessage('achievements')->escaped());
		$this->content = $this->templates->achievementsList($achievements, \Cheevos\Category::getAll(), $progress, $hide, $searchTerm);
	}

	/**
	 * Cheevos Form
	 *
	 * @access	public
	 * @return	void	[Outputs to screen]
	 */
	public function achievementsForm() {
		if (!$this->wgUser->isAllowed('edit_achievements')) {
			throw new PermissionsError('edit_achievements');
			return;
		}

		$this->output->addModules(['ext.achievements.triggerBuilder.js']);

		if ($this->wgRequest->getInt('aid')) {
			$achievementId = $this->wgRequest->getInt('aid');

			$this->achievement = \Cheevos\Achievement::newFromId($achievementId);

			if ($this->achievement === false || $achievementId != $this->achievement->getId()) {
				$this->output->showErrorPage('achievements_error', 'error_bad_achievement_id');
				return;
			}
		} else {
			$this->achievement = new \Cheevos\Achievement;
		}

		$return = $this->acheivementsSave();

		if ($this->achievement->exists()) {
			$this->output->setPageTitle(wfMessage('edit_achievement')->escaped().' - '.wfMessage('achievements')->escaped().' - '.$this->achievement->getName());
		} else {
			$this->output->setPageTitle(wfMessage('add_achievement')->escaped().' - '.wfMessage('achievements')->escaped());
		}
		$this->content = $this->templates->achievementsForm($this->achievement, \Cheevos\Category::getAll(), \Cheevos\Achievement::getKnownHooks(), \Cheevos\Achievement::getAll(), $return['errors']);
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
			}

			$this->achievement->setPoints($this->wgRequest->getInt('points'));

			$category = \Cheevos\Category::newFromText($this->wgRequest->getText('category'));
			if ($category === false) {
				$errors['category'] = wfMessage('error_invalid_achievement_category')->escaped();
			} else {
				if (!$category->exists()) {
					$category->save();
				}
				$this->achievement->setCategoryId($category->getId());
			}

			$this->achievement->setSecret($this->wgRequest->getBool('secret'));

			$this->achievement->setPartOfDefaultMega($this->wgRequest->getBool('part_of_default_mega'));

			$this->achievement->setManuallyAwarded($this->wgRequest->getBool('manual_award'));

			if ($this->wgUser->isAllowed('edit_achievement_triggers')) {
				$rules = null;
				$triggers = @json_decode($this->wgRequest->getText('triggers'), true);
				if (!is_array($triggers)) {
					$triggers = [];
				}
				$this->achievement->setTriggers($triggers);

				$this->achievement->setIncrement($this->wgRequest->getInt('increment'));
			}

			if ($this->wgUser->isAllowed('edit_meta_achievements')) {
				$requiredAchievements = $this->wgRequest->getArray('required_achievements');

				if (is_array($requiredAchievements)) {
					foreach ($requiredAchievements as $key => $achievementId) {
						$achievements = \Cheevos\Achievement::newFromId($achievementId);
						if ($achievements === false || !$achievements->exists()) {
							unset($requiredAchievements[$key]);
						}
					}
				} else {
					$requiredAchievements = [];
				}
				$this->achievement->setRequires($requiredAchievements);
			}

			if (!count($errors)) {
				$success = $this->achievement->save();

				if ($success) {
					CheevosHooks::invalidateCache();
				}

				$page = Title::newFromText('Special:Achievements');
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
	 * Cheevos Delete
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

				$achievement = \Cheevos\Achievement::newFromId($achievementId);

				if ($achievement === false || $achievementId != $achievement->getId()) {
					$this->output->showErrorPage('achievements_error', 'error_bad_achievement_id');
					return;
				}
			}

			if ($this->wgRequest->getVal('confirm') == 'true') {
				if ($action == 'restore') {
					$achievement->setDeleted(false);
				} else {
					$achievement->setDeleted(true);
				}
				$achievement->save();

				CheevosHooks::invalidateCache();

				$page = Title::newFromText('Special:Achievements');
				$this->output->redirect($page->getFullURL());
				return;
			}

			if ($achievement->isDeleted()) {
				$this->output->setPageTitle(wfMessage('restore_achievement_title')->escaped().' - '.$achievement->getName());
			} else {
				$this->output->setPageTitle(wfMessage('delete_achievement_title')->escaped().' - '.$achievement->getName());
			}
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
