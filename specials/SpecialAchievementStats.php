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

class SpecialAchievementStats extends SpecialPage {
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
		parent::__construct('AchievementStats');

		$this->wgRequest	= $this->getRequest();
		$this->wgUser		= $this->getUser();
		$this->output		= $this->getOutput();
		$this->site_key 	= $dsSiteKey;

		if (!$dsSiteKey || empty($dsSiteKey)) {
			throw new MWException('Could not determined the site key for use for Achievements.');
			return;
		}

		if ($this->site_key == "master") {
			$this->site_key = '';
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
		$this->templates = new TemplateAchievementStats;
		$this->output->addModules(['ext.cheevos.styles','ext.cheevos.stats.css','ext.cheevos.stats.js']);
		$this->setHeaders();

		switch ($subpage) {
			default:
			case 'view':
				$this->achievementsStats();
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
	public function achievementsStats() {
		$sites = \DynamicSettings\Wiki::loadAll();

		$this->output->setPageTitle(wfMessage('achievement_stats')->escaped());
		$this->content = $this->templates->achievementsStats($sites);
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
