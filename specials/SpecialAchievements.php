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

		\Cheevos\Cheevos::checkUnnotified($globalId, $this->siteKey, true); //Just a helper to fix cases of missed achievements.

		$awarded = \Cheevos\Cheevos::getUserProgress($globalId, null, $this->siteKey);
		$_achievements = \Cheevos\Cheevos::getAchievements($dsSiteKey);

		$achievements = [];
		$categories = [];
		if (!empty($_achievements)) {
			foreach ($awarded as $aa) {
				if ($aa->getEarned()) {
					$achievements[$aa->getAchievement_Id()] = $_achievements[$aa->getAchievement_Id()];
					if (!array_key_exists($achievements[$aa->getAchievement_Id()]->getCategory()->getId(), $categories)) {
						$categories[$achievements[$aa->getAchievement_Id()]->getCategory()->getId()] = $achievements[$aa->getAchievement_Id()]->getCategory();
					}
				}
			}
		}

		$title = wfMessage('achievements')->escaped();
		if ($user) {
			$title .= " for {$user->getName()}";
		}

		$this->output->setPageTitle($title);
		$this->content = $this->templates->achievementsList($achievements, $categories);
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
