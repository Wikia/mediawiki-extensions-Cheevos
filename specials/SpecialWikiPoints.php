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
		$this->templateWikiPoints = new TemplateWikiPoints;

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
		$itemsPerPage = 200;
		$total = 0;

		$modifiers = explode('/', trim(trim($subpage), '/'));

		$filters = [
			'stat'		=> 'wiki_points',
			'limit'		=> $itemsPerPage,
			'offset'	=> $start
		];

		$loadSites = false;
		if (!in_array('sites', $modifiers)) {
			$filters['site_key'] = $dsSiteKey;
		} else {
			$loadSites = true;
		}

		$isMonthly = false;
		if (in_array('monthly', $modifiers)) {
			$isMonthly = true;
		}

		$statProgress = [];
		try {
			$statProgress = \Cheevos\Cheevos::getStatProgress($filters);
		} catch (\Cheevos\CheevosException $e) {
			throw new ErrorPageError("Encountered Cheevos API error {$e->getMessage()}\n");
		}

		$userPoints = [];
		$siteKeys = [];
		foreach ($statProgress as $progress) {
			$globalId = $progress->getUser_Id();
			if (isset($userPoints[$globalId])) {
				continue;
			}
			$user = $lookup->localUserFromCentralId($globalId);
			if ($globalId < 1) {
				continue;
			}
			$userPointsRow = new stdClass();
			if ($user !== null) {
				$userPointsRow->userName = $user->getName();
				$userPointsRow->userToolsLinks = Linker::userToolLinks($user->getId(), $user->getName());
				$userPointsRow->userLink = Linker::link(Title::newFromText("User:".$user->getName()));
			} else {
				$userPointsRow->userName = "GID: ".$progress->getUser_Id();
				$userPointsRow->userToolsLinks = $userPointsRow->userName;
				$userPointsRow->userLink = '';
			}
			$userPointsRow->score = $progress->getCount();
			$userPointsRow->siteKey = $progress->getSite_Key();
			$userPoints[$globalId] = $userPointsRow;
			if ($loadSites) {
				$siteKeys[] = $progress->getSite_Key();
			}
		}
		$siteKeys = array_unique($siteKeys);

		$wikis = [];
		if ($loadSites && !empty($siteKeys)) {
			$wikis = \DynamicSettings\Wiki::loadFromHash($siteKeys);
		}

		$pagination = HydraCore::generatePaginationHtml($total, $itemsPerPage, $start);

		$this->output->setPageTitle(wfMessage('top_wiki_editors'.($modifier === 'monthly' ? '_monthly' : '')));
		$this->content = $this->templateWikiPoints->wikiPoints($userPoints, $pagination, $wikis, $loadSites, $isMonthly);
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
