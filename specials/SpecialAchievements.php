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
		$this->wgUser		= $this->getUser();
		$this->output		= $this->getOutput();
		$this->site_key 	= $dsSiteKey;

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

		$awarded = Cheevos\Cheevos::getUserProgress($this->globalId);
		$achievements = [];

		foreach($awarded as $aa) {
			if ($aa->getEarned()) {
				$achievements[] = Cheevos\Cheevos::getAchievement($aa->getAchievement_Id());
			}
		}

	
		//$achievements = Cheevos\Cheevos::getAchievements($this->site_key);
		$categories = Cheevos\Cheevos::getCategories();

		$this->output->setPageTitle(wfMessage('achievements')->escaped());
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
