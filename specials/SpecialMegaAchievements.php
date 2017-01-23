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

class SpecialMegaAchievements extends SpecialPage {
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
		$this->wgUser		= $this->getUser();

		parent::__construct('MegaAchievements', 'mega_achievement_admin', $this->wgUser->isAllowed('mega_achievement_admin'));

		$this->wgRequest	= $this->getRequest();
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
		$this->checkPermissions();

		$this->templates = new TemplateMegaAchievements;

		$this->output->addModules(['ext.achievements.styles', 'ext.achievements.js', 'ext.wikiSelect']);

		$this->setHeaders();

		if (!defined('ACHIEVEMENTS_MASTER') || ACHIEVEMENTS_MASTER !== true) {
			//Users should not have the mega_achievement_admin permission on child wikis so this check exists in case a naughty admin gives it to someone.
			throw new PermissionsError('mega_achievement_admin');
			return;
		}

		if (CheevosHooks::inMaintenance()) {
			$this->output->showErrorPage('mega_achievements_error', 'error_maintenance_mode');
			return;
		}

		switch ($subpage) {
			default:
			case 'achievements':
				$this->megaAchievementsList();
				break;
			case 'admin':
				$this->megaAchievementsForm();
				break;
			case 'delete':
			case 'restore':
				$this->megaAchievementsDelete($subpage);
				break;
			case 'siteAchievements':
				$this->getSiteAchievements($this->wgRequest->getVal('siteKey'));
				break;
			case 'updateSiteMega':
				$this->updateSiteMegaForm($this->wgRequest->getVal('siteKey'));
				break;
		}

		$this->output->addHTML($this->content);
	}

	/**
	 * Mega Achievements List
	 *
	 * @access	public
	 * @return	void	[Outputs to screen]
	 */
	public function megaAchievementsList() {
		$start = $this->wgRequest->getInt('st');
		$itemsPerPage = 100;

		$hide['deleted'] = false;
		$achievements = \Cheevos\MegaAchievement::getAll(true);

		$searchTerm = '';

		if (count($achievements)) {
			if ($this->wgRequest->getVal('do') == 'resetSearch') {
				$this->wgRequest->response()->setcookie('megaAchievementSearchTerm', '', 1);
			} else {
				$listSearch = $this->wgRequest->getVal('list_search');
				$cookieSearch = $this->wgRequest->getCookie('megaAchievementSearchTerm');
				if (($this->wgRequest->getVal('do') == 'search' && !empty($listSearch)) || !empty($cookieSearch)) {
					if (!empty($cookieSearch) && empty($listSearch)) {
						$searchTerm = $this->wgRequest->getCookie('megaAchievementSearchTerm');
					} else {
						$searchTerm = $this->wgRequest->getVal('list_search');
					}
					$achievements = CheevosHooks::searchByObjectValue($achievements, ['name', 'description'], $searchTerm);
					$this->wgRequest->response()->setcookie('megaAchievementSearchTerm', $searchTerm, $cookieExpire);
				}
			}
		}

		if ($this->wgRequest->getVal('hide_deleted') == 'false' || ($this->wgRequest->getCookie('hideDeletedMegaAchievements') == 'false' && $this->wgRequest->getVal('hide_deleted') != 'true')) {
			$hide['deleted'] = false;
			$this->wgRequest->response()->setcookie('hideDeletedMegaAchievements', 'false');
		} elseif ($this->wgRequest->getVal('hide_deleted') == 'true') {
			$hide['deleted'] = true;
			$this->wgRequest->response()->setcookie('hideDeletedMegaAchievements', 'true');
		}

		if ($hide['deleted']) {
			foreach ($achievements as $globalId => $achievement) {
				if ($achievement->isDeleted() && $hide['deleted']) {
					unset($achievements[$globalId]);
				}
			}
		}

		//TODO: PAGINATION.
		$total = 0;

		$pagination = HydraCore::generatePaginationHtml($total, $itemsPerPage, $start);

		$lookup = CentralIdLookup::factory();
		$globalId = $lookup->centralIdFromLocalUser($this->getUser(), CentralIdLookup::AUDIENCE_RAW);
		$progress = \Cheevos\MegaProgress::newFromGlobalId($globalId);

		$this->output->setPageTitle(wfMessage('mega_achievements')->escaped());
		$this->content = $this->templates->megaAchievementsList($achievements, $progress, $hide, $searchTerm, $pagination);
	}

	/**
	 * Mega Achievements Form
	 *
	 * @access	public
	 * @return	void	[Outputs to screen]
	 */
	public function megaAchievementsForm() {
		if (!$this->wgUser->isAllowed('edit_mega_achievements')) {
			throw new PermissionsError('edit_mega_achievements');
			return;
		}

		$this->output->addModules(['ext.achievements.triggerBuilder.js']);

		$megaAchievement = [];

		if ($this->wgRequest->getInt('id')) {
			$achievementId = $this->wgRequest->getInt('id');

			$this->megaAchievement = \Cheevos\MegaAchievement::newFromId($achievementId);

			if ($this->megaAchievement === false || $achievementId != $this->megaAchievement->getId()) {
				$this->output->showErrorPage('mega_achievements_error', 'error_bad_achievement_id');
				return;
			}
		} else {
			$this->megaAchievement = new \Cheevos\MegaAchievement;
		}

		$return = $this->megaAcheivementsSave();

		$site == false;
		if (strlen($this->megaAchievement->getSiteKey()) == 32) {
			$wiki = \DynamicSettings\Wiki::loadFromHash($this->megaAchievement->getSiteKey());
			if ($wiki !== false) {
				$site = [
					'siteKey'	=> $wiki->getSiteKey(),
					'name'		=> $wiki->getName(),
					'language'	=> $wiki->getLanguage()
				];
			} else {
				$this->output->showErrorPage('mega_achievements_error', 'error_bad_wiki_site_key');
				return;
			}
			$siteAchievements = $this->getSiteAchievements($this->megaAchievement->getSiteKey(), true);
			if ($siteAchievements['success'] === true) {
				$siteAchievements = $siteAchievements['achievements'];
			} else {
				$siteAchievements = [];
			}
		} else {
			$siteAchievements = \Cheevos\Achievement::getAll();
		}

		if ($this->megaAchievement->getId()) {
			$this->output->setPageTitle(wfMessage('edit_achievement')->escaped().' - '.wfMessage('mega_achievements')->escaped().' - '.$this->megaAchievement->getName());
		} else {
			$this->output->setPageTitle(wfMessage('add_achievement')->escaped().' - '.wfMessage('mega_achievements')->escaped());
		}
		$this->content = $this->templates->megaAchievementsForm($this->megaAchievement, \Cheevos\Achievement::getKnownHooks(), $siteAchievements, $site, $return['errors']);
	}

	/**
	 * Saves submitted mega achievement forms.
	 *
	 * @access	private
	 * @return	array	Array containing an array of processed form information and array of corresponding errors.
	 */
	private function megaAcheivementsSave() {
		global $achImageDomainWhiteList;

		$errors = [];
		if ($this->wgRequest->getVal('do') == 'save' && $this->wgRequest->wasPosted()) {
			$name = $this->wgRequest->getText('name');
			if (!$name || strlen($name) > 255) {
				$errors['name'] = wfMessage('error_invalid_achievement_name')->escaped();
			} else {
				$this->megaAchievement->setName($name);
			}

			$description = substr($this->wgRequest->getText('description'), 0, 255);
			if (!$description || strlen($description) > 255) {
				$errors['description'] = wfMessage('error_invalid_achievement_description')->escaped();
			} else {
				$this->megaAchievement->setDescription($description);
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
						$this->megaAchievement->setImageUrl($imageUrl);
					}
				} else {
					$this->megaAchievement->setImageUrl($imageUrl);
				}
			}

			//Site Key and Required Achievements
			$wikis = $this->wgRequest->getVal('site_key');
			$wikis = @json_decode($wikis, true);
			$siteKey = $wikis['single'];
			$requiredAchievements = $this->wgRequest->getArray('required_achievements');

			$siteAchievements = [];
			if (strlen($siteKey) == 32) {
				$siteAchievements = $this->getSiteAchievements($siteKey, true);
				if ($siteAchievements['success'] == true && array_key_exists('achievements', $siteAchievements)) {
					$siteAchievements = $siteAchievements['achievements'];
				} else {
					$siteAchievements = [];
				}
				$this->megaAchievement->setSiteKey($siteKey);
			} else {
				$siteAchievements = \Cheevos\Achievement::getAll();
			}

			if (is_array($requiredAchievements)) {
				foreach ($requiredAchievements as $key => $achievementHash) {
					$found = false;
					foreach ($siteAchievements as $aid => $achievement) {
						if ($achievement->getHash() == $achievementHash) {
							$found = true;
							break;
						}
					}
					if (!$found) {
						unset($requiredAchievements[$key]);
					}
				}
			} else {
				$requiredAchievements = [];
			}
			$this->megaAchievement->setRequires($requiredAchievements);

			$success = false;
			if (!count($errors)) {
				$success = $this->megaAchievement->save();
				if ($success == false) {
					$lastError = $this->megaAchievement->getLastError();
					if (!empty($lastError)) {
						$errors['save'] = wfMessage('error_message_achievement_service', $lastError)->escaped();
					} else {
						$errors['save'] = wfMessage('error_unknown_achievement_service')->escaped();
					}
				} else {
					CheevosHooks::invalidateCache();

					$page = Title::newFromText('Special:MegaAchievements');
					$this->output->redirect($page->getFullURL());
				}
			}

			$requires = $requiredAchievements;
		}
		return [
			'save'		=> $save,
			'errors'	=> $errors,
			'success'	=> $success
		];
	}

	/**
	 * Mega Achievements Delete
	 *
	 * @access	public
	 * @param	string	Delete or Restore action take.
	 * @return	void	[Outputs to screen]
	 */
	public function megaAchievementsDelete($action) {
		if (!$this->wgUser->isAllowed('edit_mega_achievements') || !$this->wgUser->isAllowed('delete_achievements')) {
			throw new PermissionsError('edit_mega_achievements');
			return;
		}

		$achievementId = $this->wgRequest->getInt('id');

		$megaAchievement = \Cheevos\MegaAchievement::newFromId($achievementId);

		if ($megaAchievement === false || $achievementId != $megaAchievement->getId()) {
			$this->output->showErrorPage('mega_achievements_error', 'error_bad_achievement_id');
			return;
		}

		$error = false;
		if ($this->wgRequest->getVal('confirm') == 'true') {
			$success = false;
			if ($action == 'restore') {
				$megaAchievement->setDeleted(false);
			} else {
				$megaAchievement->setDeleted(true);
			}
			$success = $megaAchievement->save();

			if ($success == false) {
				$lastError = $this->megaAchievement->getLastError();
				if (!empty($lastError)) {
					$error = wfMessage('error_could_not_delete_mega', $lastError)->escaped();
				} else {
					$error = wfMessage('error_unknown_achievement_service')->escaped();
				}
			} else {
				CheevosHooks::invalidateCache();

				$page = Title::newFromText('Special:MegaAchievements');
				$this->output->redirect($page->getFullURL());
				return;
			}
		}

		if ($megaAchievement->isDeleted()) {
			$this->output->setPageTitle(wfMessage('restore_achievement_title')->escaped().' - '.$megaAchievement->getName());
		} else {
			$this->output->setPageTitle(wfMessage('delete_achievement_title')->escaped().' - '.$megaAchievement->getName());
		}
		$this->content = $this->templates->megaAchievementsDelete($megaAchievement, $error);
	}

	/**
	 * Update Site Mega Form
	 *
	 * @access	public
	 * @param	string	Wiki Site Key.
	 * @return	void	[Outputs to screen]
	 */
	public function updateSiteMegaForm($siteKey) {
		if (!$this->wgUser->isAllowed('edit_mega_achievements')) {
			throw new PermissionsError('edit_mega_achievements');
			return;
		}

		$wiki = \DynamicSettings\Wiki::loadFromHash($siteKey);
		if ($wiki !== false) {
			if ($this->wgRequest->getCheck('confirm')) {
				\Cheevos\SiteMegaUpdate::queue(['site_key' => $wiki->getSiteKey()]);
				$page = Title::newFromText('Special:WikiSites');
				$this->output->redirect($page->getFullURL());
				return;
			}
			$this->output->setPageTitle(wfMessage('update_site_mega')->escaped().' - '.wfMessage('mega_achievements')->escaped());
			$this->content = $this->templates->updateSiteMegaForm($wiki);
		} else {
			$this->output->showErrorPage('mega_achievements_error', 'error_site_not_found');
			return;
		}
	}

	/**
	 * Dumps out an API response for site achievements.
	 *
	 * @access	public
	 * @param	string	Site Key
	 * @param	boolean	[Optional] Return Array
	 * @return	mixed	Outputs to screen and exits or returns an array.
	 */
	public function getSiteAchievements($siteKey, $returnArray = false) {
		$wiki = \DynamicSettings\Wiki::loadFromHash($siteKey);
		if ($wiki !== false) {
			//Error checks pass, get and send data.
			try {
				$siteDB = $wiki->getDatabaseLB()->getConnection(DB_MASTER);

				$results = $siteDB->select(
					['achievement'],
					['*'],
					null,
					__METHOD__
				);

				$siteAchievements = [];
				while ($row = $results->fetchRow()) {
					if ($returnArray) {
						$siteAchievements[$row['aid']] = \Cheevos\FakeAchievement::newFromRow($row);
					} else {
						$siteAchievements[$row['aid']] = $row;
					}
				}

				$data = [
					'success'		=> true,
					'achievements'	=> $siteAchievements
				];

				$siteDB->close();
			} catch (Exception $e) {
				$data = ['error' => 'error_connecting_to_site'];
			}
		} else {
			if ($siteKey == 'master') {
				$data = [
					'success'		=> true,
					'achievements'	=> CheevosHooks::getAchievement()
				];
			} else {
				$data = ['error' => 'bad_site_key'];
			}
		}
		if (!$returnArray) {
			header('Content-Type: application/json');
			echo @json_encode($data);
			exit;
		} else {
			return $data;
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
