<?php
/**
 * Cheevos
 * Cheevos Special Points Comp Page
 *
 * @package   Cheevos
 * @author    Alexia E. Smith
 * @copyright (c) 2017 Curse Inc.
 * @license   GPL-2.0-or-later
 * @link      https://gitlab.com/hydrawiki/extensions/cheevos
 **/

use DynamicSettings\Environment;

class SpecialPointsComp extends SpecialPage {
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
		parent::__construct('PointsComp', 'points_comp_reports');
	}

	/**
	 * Main Executor
	 *
	 * @param  string	Sub page passed in the URL.
	 *
	 * @return void	[Outputs to screen]
	 */
	public function execute($subpage) {
		$this->getOutput()->addModuleStyles(['ext.cheevos.styles']);
		$this->getOutput()->addModules(['ext.cheevos.js', 'ext.cheevos.pointsComp.js']);
		$this->checkPermissions();
		$this->setHeaders();
		$this->pointsCompReports($subpage);
		$this->getOutput()->addHTML($this->content);
	}

	/**
	 * Points Comp Reports
	 *
	 * @param  mixed	Passed subpage parameter to be intval()'ed for a Global ID.
	 *
	 * @return void	[Outputs to screen]
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
			$this->getOutput()->setPageTitle(wfMessage('pointscomp_detail', $report->getReportId(), gmdate('Y-m-d', $report->getRunTime()))->escaped());
			if ($this->getRequest()->getBool('csv')) {
				return $this->downloadCSV(TemplatePointsComp::pointsCompReportCSV($report), $report->getReportId());
			}
			$this->content = TemplatePointsComp::pointsCompReportDetail($report);
		} else {
			$reportData = \Cheevos\Points\PointsCompReport::getReportsList($start, $itemsPerPage);

			$pagination = HydraCore::generatePaginationHtml($this->getFullTitle(), $reportData['total'], $itemsPerPage, $start);
			$this->getOutput()->setPageTitle(wfMessage('pointscomp')->escaped());
			$this->content = TemplatePointsComp::pointsCompReports($reportData['reports'], $pagination);
		}
	}

	/**
	 * Run a report into the job queue.
	 *
	 * @return void
	 */
	public function runReport() {
		if ($this->getRequest()->wasPosted()) {
			$report = false;
			$reportId = $this->getRequest()->getInt('report_id');
			if ($reportId > 0) {
				$report = \Cheevos\Points\PointsCompReport::newFromId($reportId);
				if (!$report) {
					throw new ErrorPageError('points_comp_report_error', 'report_does_not_exist');
				}
			}

			$doCompUser = $this->getRequest()->getInt('compUser');
			$doEmailUser = $this->getRequest()->getVal('emailUser');
			if (($doCompUser > 0 || $doEmailUser > 0) && $report !== null) {
				$pointsCompPage	= SpecialPage::getTitleFor('PointsComp', $reportId);
				if ($doCompUser > 0) {
					$config = \ConfigFactory::getDefaultInstance()->makeConfig('main');
					$compedSubscriptionMonths = intval($config->get('CompedSubscriptionMonths'));
					$userComped = $report->compSubscription($doCompUser, $compedSubscriptionMonths);
					$this->getOutput()->redirect($pointsCompPage->getFullURL(['userComped' => intval($userComped)]));
				}
				if ($doEmailUser > 0) {
					$emailSent = $report->sendUserEmail($doEmailUser);
					$this->getOutput()->redirect($pointsCompPage->getFullURL(['emailSent' => intval($emailSent)]));
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
			if (($do === 'grantAll' || $do === 'emailAll' || $do === 'grantAndEmailAll') && $report !== null) {
				$success = \Cheevos\Job\PointsCompJob::queue(
					[
						'report_id'	=> $reportId,
						'grantAll'	=> $final,
						'emailAll'	=> $email
					]
				);
				$pointsCompPage	= SpecialPage::getTitleFor('PointsComp');
				$this->getOutput()->redirect($pointsCompPage->getFullURL(['queued' => intval($success)]));
				return;
			}

			if ($report === false) {
				$startTime = $this->getRequest()->getInt('start_time');
				$startTime = strtotime(date('Y-m-d', $startTime) . 'T00:00:00+00:00');
				$endTime = $this->getRequest()->getInt('end_time');
				$endTime = strtotime(date('Y-m-d', $endTime) . 'T23:59:59+00:00');
				$status = \Cheevos\Points\PointsCompReport::validateTimeRange($startTime, $endTime);
				if (!$status->isGood()) {
					throw new ErrorPageError('points_comp_report_error', $status->getMessage());
				}

				$minPointThreshold = $this->getRequest()->getInt('min_point_threshold');
				$maxPointThreshold = $this->getRequest()->getVal('max_point_threshold');
				if ($maxPointThreshold !== '0' && empty($maxPointThreshold)) {
					$maxPointThreshold = null;
				} else {
					$maxPointThreshold = intval($maxPointThreshold);
				}
				$status = \Cheevos\Points\PointsCompReport::validatePointThresholds($minPointThreshold, $maxPointThreshold);
				if (!$status->isGood()) {
					throw new ErrorPageError('points_comp_report_error', $status->getMessage());
				}

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
					'report_id'	=> $reportId,
					'final'		=> $final,
					'email'		=> $email
				]
			);

			$pointsCompPage	= SpecialPage::getTitleFor('PointsComp');
			$this->getOutput()->redirect($pointsCompPage->getFullURL(['queued' => intval($success)]));
			return;
		}
	}

	/**
	 * Download CSV to client.
	 *
	 * @return void
	 */
	private function downloadCSV($csv, $reportId) {
		$filename = 'points_comp_report_' . $reportId;

		header("Content-type: text/csv");
		header("Content-Disposition: attachment; filename=$filename.csv");
		header("Pragma: no-cache");
		header("Expires: 0");

		$output = fopen("php://output", "w");
		fwrite($output, $csv);
		fclose($output);
		exit;
	}

	/**
	 * Hides special page from SpecialPages special page.
	 *
	 * @return boolean
	 */
	public function isListed() {
		if (Environment::isMasterWiki() && $this->getUser()->isAllowed('points_comp_reports')) {
			return true;
		}
		return false;
	}

	/**
	 * Lets others determine that this special page is restricted.
	 *
	 * @return boolean	True
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
