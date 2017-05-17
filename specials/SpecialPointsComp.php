<?php
/**
 * Cheevos
 * Cheevos Special Points Comp Page
 *
 * @author		Alexia E. Smith
 * @copyright	(c) 2017 Curse Inc.
 * @license		All Rights Reserved
 * @package		Cheevos
 * @link		http://www.curse.com/
 *
 **/

class SpecialPointsComp extends SpecialPage {
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
		parent::__construct('PointsComp');

	}

	/**
	 * Main Executor
	 *
	 * @access	public
	 * @param	string	Sub page passed in the URL.
	 * @return	void	[Outputs to screen]
	 */
	public function execute($subpage) {
		$this->getOutput()->addModules(['ext.cheevos.styles', 'ext.cheevos.js']);
		$this->setHeaders();
		$this->pointsCompReports($subpage);
		$this->getOutput()->addHTML($this->content);
	}

	/**
	 * Points Comp Reports
	 *
	 * @access	public
	 * @param	mixed	Passed subpage parameter to be intval()'ed for a Global ID.
	 * @return	void	[Outputs to screen]
	 */
	public function pointsCompReports($subpage = null) {
		$start = $this->getRequest()->getInt('st');
		$itemsPerPage = 50;

		$reportData = \Cheevos\Points\PointsCompReport::getReportsList($start, $itemsPerPage);

		$pagination = HydraCore::generatePaginationHtml($reportData['total'], $itemsPerPage, $start);
		$this->content = $pagination.TemplatePointsComp::pointsCompReports($reportData['reports']).$pagination;
	}

	/**
	 * Hides special page from SpecialPages special page.
	 *
	 * @access	public
	 * @return	boolean
	 */
	public function isListed() {
		if ($this->wgUser->isAllowed('achievement_admin')) {
			return true;
		}
		return false;
	}

	/**
	 * Lets others determine that this special page is restricted.
	 *
	 * @access	public
	 * @return	boolean	True
	 */
	public function isRestricted() {
		return true;
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
