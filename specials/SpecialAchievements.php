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
		global $dsSiteKey;
		parent::__construct('Achievements');

		$this->wgRequest	= $this->getRequest();
		$this->output		= $this->getOutput();
		$this->siteKey		= $dsSiteKey;

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
		$this->output->addModules(['ext.cheevos.styles', 'ext.cheevos.js']);
		$this->setHeaders();
		$this->achievementsList();
		$this->output->addHTML($this->content);
	}

	/**
	 * Cheevos List
	 *
	 * @access	public
	 * @return	void	[Outputs to screen]
	 */
	public function achievementsList() {
		global $dsSiteKey;

		$lookup = CentralIdLookup::factory();

		$globalId = false;
		if ($this->getUser()->isLoggedIn()) {
			$globalId = $lookup->centralIdFromLocalUser($this->getUser(), CentralIdLookup::AUDIENCE_RAW);
			$user = $this->getUser();
		}

		if ($this->wgRequest->getVal('globalid') > 0) {
			$globalId = $this->wgRequest->getVal('globalid');
			$user = $lookup->localUserFromCentralId($globalId);
			if ($globalId < 1 || $user === null) {
				throw new ErrorPageError('achievements', 'no_user_to_display_achievements');
			}
		}

		if ($globalId < 1 || $user === null) {
			throw new UserNotLoggedIn('login_to_display_achievements', 'achievements');
		}

		try {
			\Cheevos\Cheevos::checkUnnotified($globalId, $this->siteKey, true); //Just a helper to fix cases of missed achievements.
			$_statuses = \Cheevos\Cheevos::getUserStatus($globalId, $this->siteKey);
			$achievements = \Cheevos\Cheevos::getAchievements($dsSiteKey);
		} catch (\Cheevos\CheevosException $e) {
			throw new ErrorPageError('achievements', 'error_cheevos_service', [$e->getMessage()]);
		}

		$categories = [];
		if (!empty($achievements)) {
			foreach ($achievements as $aid => $achievement) {
				if (!array_key_exists($achievement->getCategory()->getId(), $categories)) {
					$categories[$achievement->getCategory()->getId()] = $achievement->getCategory();
				}
			}
		}

		$statuses = [];
		if (!empty($_statuses)) {
			foreach ($_statuses as $_status) {
				$statuses[$_status->getAchievement_Id()] = $_status;
			}
		}

		$title = wfMessage('achievements')->escaped();
		if ($user) {
			$title .= " for {$user->getName()}";
		}

		$this->output->setPageTitle($title);
		$this->content = $this->templates->achievementsList($achievements, $categories, $statuses);
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
