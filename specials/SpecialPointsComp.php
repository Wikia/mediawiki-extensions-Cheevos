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
		$this->getOutput()->addModules(['ext.cheevos.styles', 'ext.cheevos.js', 'ext.cheevos.pointsComp.js']);
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
		$reportId = intval($subpage);
		$messages = $this->runReport();
		if ($reportId > 0) {
			$report = \Cheevos\Points\PointsCompReport::newFromId($reportId);
			if (!$report) {
				throw new ErrorPageError('points_comp_report_error', 'report_does_not_exist');
			}
			$pagination = HydraCore::generatePaginationHtml($reportData['total'], $itemsPerPage, $start);
			$this->getOutput()->setPageTitle(wfMessage('pointscomp_detail', $report->getReportId(), gmdate('Y-m-d', $report->getRunTime()))->escaped());
			$this->content = $pagination.TemplatePointsComp::pointsCompReportDetail($report).$pagination;
		} else {
			$reportData = \Cheevos\Points\PointsCompReport::getReportsList($start, $itemsPerPage);

			$pagination = HydraCore::generatePaginationHtml($reportData['total'], $itemsPerPage, $start);
			$this->getOutput()->setPageTitle(wfMessage('pointscomp')->escaped());
			$this->content = TemplatePointsComp::pointsCompReports($reportData['reports'], $pagination);
		}
	}

	/**
	 * Run a report into the job queue.
	 *
	 * @access	public
	 * @return	void
	 */
	public function runReport() {
		if ($this->getRequest()->wasPosted()) {
			$lookup = CentralIdLookup::factory();

			$doCompUser = $this->getRequest()->getInt('compUser');
			$doEmailUser = $this->getRequest()->getVal('emailUser');
			if ($doCompUser > 0 || $doEmailUser > 0) {
				if ($doCompUser > 0) {
					
				}
				if ($doEmailUser > 0) {
					$user = $lookup->localUserFromCentralId($doEmailUser);
					if ($user !== null) {
						\Cheevos\Points\PointsCompReport::sendUserEmail($user);
					}
				}
				return;
			}

			$final = false;
			$email = false;

			$do = $this->getRequest()->getVal('do');
			if ($do === 'grantAll' || $do === 'grantAndEmailAll') {
				$final = true;
			}

			if ($do === 'emailAll' || $do === 'grantAndEmailAll') {
				$email = true;
			}

			$startTime = $this->getRequest()->getInt('start_time');
			$startTime = strtotime(date('Y-m-d', $startTime).'T00:00:00+00:00');
			$endTime = $this->getRequest()->getInt('end_time');
			$endTime = strtotime(date('Y-m-d', $endTime).'T23:59:59+00:00');
			$status = \Cheevos\Points\PointsCompReport::validateTimeRange($startTime, $endTime);
			if (!$status->isGood()) {
				throw new ErrorPageError('points_comp_report_error', $status->getMessage());
			}

			$minPointThreshold = $this->getRequest()->getInt('min_point_threshold');
			$maxPointThreshold = $this->getRequest()->getInt('max_point_threshold');
			$status = \Cheevos\Points\PointsCompReport::validatePointThresholds($minPointThreshold, $maxPointThreshold);
			if (!$status->isGood()) {
				throw new ErrorPageError('points_comp_report_error', $status->getMessage());
			}

			$reportId = $this->getRequest()->getInt('report_id');
			if ($reportId > 0) {
				$report = \Cheevos\Points\PointsCompReport::newFromId($reportId);
				if (!$report) {
					throw new ErrorPageError('points_comp_report_error', 'report_does_not_exist');
				}
			} else {
				$report = new \Cheevos\Points\PointsCompReport();
				$report->setMinPointThreshold($minPointThreshold);
				$report->setMaxPointThreshold($maxPointThreshold);
				$report->setStartTime($startTime);
				$report->setEndTime($endTime);
				$report->save();
				$reportId = $report->getReportId();
			}

			$success = \Cheevos\Job\PointsCompJob::queue(
				[
					'report_id'				=> $reportId,
					'final'					=> $final,
					'email'					=> $email
				]
			);

			$pointsCompPage	= SpecialPage::getTitleFor('PointsComp');
			$this->getOutput()->redirect($pointsCompPage->getFullURL(['queued' => intval($success)]));
			return;
		}
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
