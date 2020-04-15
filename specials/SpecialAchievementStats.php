<?php
/**
 * Cheevos
 * Cheevos Special Page
 *
 * @package   Cheevos
 * @author    Hydra Wiki Platform Team
 * @copyright (c) 2017 Curse Inc.
 * @license   GPL-2.0-or-later
 * @link      https://gitlab.com/hydrawiki/extensions/cheevos
 **/

use Cheevos\Cheevos;
use DynamicSettings\Wiki;

class SpecialAchievementStats extends SpecialPage {
	/**
	 * Output HTML
	 *
	 * @var string
	 */
	private $content;

	/**
	 * Main Constructor
	 *
	 * @return void
	 */
	public function __construct() {
		global $dsSiteKey;

		parent::__construct('AchievementStats', 'achievement_stats', ($this->getUser()->isAllowed('achievement_stats')));

		$this->wgRequest	= $this->getRequest();
		$this->wgUser		= $this->getUser();
		$this->output		= $this->getOutput();
		$this->site_key 	= $dsSiteKey;

		if (!$dsSiteKey || empty($dsSiteKey)) {
			throw new MWException('Could not determined the site key for use for Achievements.');
			return;
		}

		$this->globalId = Cheevos::getUserIdForService($this->wgUser);
	}

	/**
	 * Main Executor
	 *
	 * @param string	Sub page passed in the URL.
	 *
	 * @return void	[Outputs to screen]
	 */
	public function execute($subpage) {
		if (!$this->userCanExecute($this->getUser())) {
			$this->displayRestrictionError();
			return;
		}

		$this->templates = new TemplateAchievementStats;
		$this->output->addModuleStyles(['ext.cheevos.styles', 'ext.cheevos.stats.css']);
		$this->output->addModules(['ext.cheevos.stats.js']);
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
	 * @return void	[Outputs to screen]
	 */
	public function achievementsStats() {
		$sites = Wiki::loadAll();

		$this->output->setPageTitle(wfMessage('achievement_stats')->escaped());
		$this->content = $this->templates->achievementsStats($sites);
	}

	/**
	 * Lets others determine that this special page is restricted.
	 *
	 * @return boolean	 True
	 */
	public function isRestricted() {
		return true;
	}

	/**
	 * Return the group name for this special page.
	 *
	 * @return string
	 */
	protected function getGroupName() {
		return 'users';
	}
}
