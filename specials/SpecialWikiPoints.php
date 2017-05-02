<?php
/**
 * Curse Inc.
 * Wiki Points
 * A contributor scoring system
 *
 * @author		Noah Manneschmidt
 * @copyright	(c) 2014 Curse Inc.
 * @license		All Rights Reserved
 * @package		Wiki Points
 * @link		http://www.curse.com/
 *
**/

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
		$this->output->addModules('ext.cheevos.wikiPoints');

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
		$total = 0;

		$modifiers = explode('/', trim(trim($subpage), '/'));
		$isSitesMode = in_array('sites', $modifiers);
		$isMonthly = in_array('monthly', $modifiers);

		$pagination = HydraCore::generatePaginationHtml($total, $itemsPerPage, $start);

		$this->output->setPageTitle(wfMessage('top_wiki_editors'.($isSitesMode ? '_sites' : '').($isMonthly ? '_monthly' : '')));
		$this->content = TemplateWikiPoints::getWikiPointsLinks()."<div>{$pagination}</div>".\Cheevos\Points\PointsDisplay::pointsBlockHtml(($isSitesMode ? null : $dsSiteKey), null, $itemsPerPage, $start, $isSitesMode, $isMonthly)."<div>{$pagination}</div>";
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
