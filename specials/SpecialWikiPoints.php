<?php
/**
 * Curse Inc.
 * Cheevos
 * A contributor scoring system
 *
 * @author		Noah Manneschmidt
 * @copyright	(c) 2014 Curse Inc.
 * @license		GNU General Public License v2.0 or later
 * @package		Cheevos
 * @link		https://gitlab.com/hydrawiki
 *
**/

use DynamicSettings\Environment;

class SpecialWikiPoints extends HydraCore\SpecialPage {
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
		parent::__construct('WikiPoints');
	}

	/**
	 * Main Executor
	 *
	 * @access	public
	 * @param	string	Sub page passed in the URL.
	 * @return	void	[Outputs to screen]
	 */
	public function execute($subpage) {
		$this->output->addModuleStyles(['ext.cheevos.wikiPoints.styles']);
		$this->output->addModules(['ext.cheevos.wikiPoints.scripts']);

		$this->setHeaders();

		$this->wikiPoints($subpage);

		$this->output->addHTML($this->content);
	}

	/**
	 * Display the wiki points page.
	 *
	 * @access	public
	 * @param	string	[Optional] Subpage
	 * @return	void
	 */
	public function wikiPoints($subpage = null) {
		global $dsSiteKey;

		$lookup = CentralIdLookup::factory();

		$start = $this->wgRequest->getInt('st');
		$itemsPerPage = 100;

		$form['username'] = $this->wgRequest->getVal('user');

		$globalId = null;
		if (!empty($form['username'])) {
			$user = User::newFromName($form['username']);

			if ($user->getId()) {
				$lookup = \CentralIdLookup::factory();
				$globalId = $lookup->centralIdFromLocalUser($user);
			}

			$pointsLog = [];
			if (!$globalId) {
				$globalId = null;
				$form['error'] = wfMessage('error_wikipoints_user_not_found')->escaped();
			}
		}

		$modifiers = explode('/', trim(trim($subpage), '/'));
		$isSitesMode = in_array('sites', $modifiers) && Environment::isMasterWiki();
		$isMonthly = in_array('monthly', $modifiers);
		$isGlobal = in_array('global', $modifiers);

		$thisPage = SpecialPage::getTitleFor('WikiPoints', $subpage);
		$this->output->setPageTitle(wfMessage('top_wiki_editors'.($isGlobal ? '_global' : '').($isSitesMode ? '_sites' : '').($isMonthly ? '_monthly' : '')));
		$this->content = TemplateWikiPoints::getWikiPointsLinks();
		if (!$isMonthly) {
			$this->content .= TemplateWikiPointsAdmin::userSearch($thisPage, $form)."<hr/>";
		}
		$this->content .= \Cheevos\Points\PointsDisplay::pointsBlockHtml(($isSitesMode || $isGlobal ? null : $dsSiteKey), $globalId, $itemsPerPage, $start, $isSitesMode, $isMonthly, 'table', $thisPage);
	}

	/**
	 * Return the group name for this special page.
	 *
	 * @access	protected
	 * @return	string
	 */
	protected function getGroupName() {
		return 'wikipoints';
	}
}
