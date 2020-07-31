<?php
/**
 * Curse Inc.
 * Cheevos
 * A contributor scoring system
 *
 * @package   Cheevos
 * @author    Noah Manneschmidt
 * @copyright (c) 2014 Curse Inc.
 * @license   GPL-2.0-or-later
 * @link      https://gitlab.com/hydrawiki/extensions/cheevos
**/

use Cheevos\Cheevos;
use Cheevos\CheevosHelper;
use Cheevos\Points\PointsDisplay;

class SpecialWikiPoints extends HydraCore\SpecialPage {
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
		parent::__construct('WikiPoints');
	}

	/**
	 * Main Executor
	 *
	 * @param string	Sub page passed in the URL.
	 *
	 * @return void	[Outputs to screen]
	 */
	public function execute($subpage) {
		$this->output->addModuleStyles(['ext.cheevos.wikiPoints.styles', 'ext.hydraCore.pagination.styles', 'mediawiki.ui', 'mediawiki.ui.input', 'mediawiki.ui.button']);

		$this->setHeaders();

		$this->wikiPoints($subpage);

		$this->output->addHTML($this->content);
	}

	/**
	 * Display the wiki points page.
	 *
	 * @param string	[Optional] Subpage
	 *
	 * @return void
	 */
	public function wikiPoints($subpage = null) {
		$dsSiteKey = CheevosHelper::getSiteKey();

		$start = $this->wgRequest->getInt('st');
		$itemsPerPage = 100;

		$form['username'] = $this->wgRequest->getVal('user');

		$globalId = null;
		if (!empty($form['username'])) {
			$user = User::newFromName($form['username']);

			if ($user->getId()) {
				$globalId = Cheevos::getUserIdForService($user);
			}

			$pointsLog = [];
			if (!$globalId) {
				$globalId = null;
				$form['error'] = wfMessage('error_wikipoints_user_not_found')->escaped();
			}
		}

		$modifiers = explode('/', trim(trim($subpage), '/'));
		$isSitesMode = in_array('sites', $modifiers) && CheevosHelper::isCentralWiki();
		$isMonthly = in_array('monthly', $modifiers);
		$isGlobal = in_array('global', $modifiers);

		$thisPage = SpecialPage::getTitleFor('WikiPoints', $subpage);
		$this->output->setPageTitle(wfMessage('top_wiki_editors' . ($isGlobal ? '_global' : '') . ($isSitesMode ? '_sites' : '') . ($isMonthly ? '_monthly' : '')));
		$this->content = TemplateWikiPoints::getWikiPointsLinks();
		if (!$isMonthly) {
			$this->content .= TemplateWikiPointsAdmin::userSearch($thisPage, $form) . "<hr/>";
		}
		$this->content .= PointsDisplay::pointsBlockHtml(($isSitesMode || $isGlobal ? null : $dsSiteKey), $globalId, $itemsPerPage, $start, $isSitesMode, $isMonthly, 'table', $thisPage);
	}

	/**
	 * Return the group name for this special page.
	 *
	 * @return string
	 */
	protected function getGroupName() {
		return 'wikipoints';
	}
}
