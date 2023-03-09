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
 */

namespace Cheevos\Specials;

use Cheevos\CheevosHelper;
use Cheevos\Job\PointsCompJob;
use Cheevos\Points\PointsCompReport;
use Cheevos\Templates\TemplatePointsComp;
use ErrorPageError;
use HydraCore;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentityLookup;
use OutputPage;
use SpecialPage;
use WebRequest;

class SpecialPointsComp extends SpecialPage {

	public function __construct(
		private UserIdentityLookup $userIdentityLookup,
		private UserFactory $userFactory
	) {
		parent::__construct( 'PointsComp', 'points_comp_reports' );
	}

	/** @inheritDoc */
	public function execute( $subPage ) {
		$output = $this->getOutput();
		$output->addModuleStyles( [
			'ext.cheevos.styles',
			"ext.hydraCore.button.styles",
			'ext.hydraCore.pagination.styles',
			'mediawiki.ui.button',
			'mediawiki.ui.input'
		] );
		$output->addModules( [ 'ext.cheevos.js', 'ext.cheevos.pointsComp.js' ] );
		$this->checkPermissions();
		$this->setHeaders();
		$this->pointsCompReports( $subPage, $output, $this->getRequest() );
	}

	/** Points Comp Reports */
	public function pointsCompReports( ?string $subPage, OutputPage $output, WebRequest $request ): void {
		$this->runReport( $output, $request );
		$reportId = (int)$subPage;
		if ( $reportId > 0 ) {
			$report = PointsCompReport::newFromId( $reportId );
			if ( !$report ) {
				throw new ErrorPageError( 'points_comp_report_error', 'report_does_not_exist' );
			}
			$output->setPageTitle(
				$this->msg(
					'pointscomp_detail',
					$report->getReportId(),
					gmdate( 'Y-m-d', $report->getRunTime() )
				)->escaped()
			);
			if ( $request->getBool( 'csv' ) ) {
				$this->downloadCSV( TemplatePointsComp::pointsCompReportCSV( $report ), $report->getReportId() );
			}
			$output->addHTML( TemplatePointsComp::pointsCompReportDetail(
				$report,
				$request->getVal( 'userComped' ),
				$request->getVal( 'emailSent' )
			) );
			return;
		}

		$start = $request->getInt( 'st' );
		$itemsPerPage = 50;
		$reportData = PointsCompReport::getReportsList( $start, $itemsPerPage );

		$pagination = HydraCore::generatePaginationHtml(
			$this->getFullTitle(),
			$reportData['total'],
			$itemsPerPage,
			$start
		);
		$output->setPageTitle( $this->msg( 'pointscomp' )->escaped() );
		$output->addHTML( TemplatePointsComp::pointsCompReports(
			$reportData['reports'],
			$pagination,
			(int)$this->getConfig()->get( 'CompedSubscriptionThreshold' ),
			$request->getBool( 'queued' )
		) );
	}

	/** Run a report into the job queue. */
	private function runReport( OutputPage $output, WebRequest $request ): void {
		if ( !$request->wasPosted() ) {
			return;
		}

		$reportId = $request->getInt( 'report_id' );
		if ( $reportId <= 0 ) {
			throw new ErrorPageError( 'points_comp_report_error', 'report_does_not_exist' );
		}

		$report = PointsCompReport::newFromId( $reportId );
		$doCompUser = $this->userIdentityLookup->getUserIdentityByUserId( $request->getInt( 'compUser' ) );
		$doEmailUser = $this->userIdentityLookup->getUserIdentityByUserId( $request->getInt( 'emailUser' ) );
		if ( $report && ( $doCompUser?->isRegistered() || $doEmailUser?->isRegistered() ) ) {

			$pointsCompPage	= SpecialPage::getTitleFor( 'PointsComp', (string)$reportId );
			if ( $doCompUser && $doCompUser->isRegistered() ) {
				$compedSubscriptionMonths = (int)$this->getConfig()->get( 'CompedSubscriptionMonths' );
				$userComped = $report->compSubscription( $doCompUser, $compedSubscriptionMonths );
				$output->redirect( $pointsCompPage->getFullURL( [ 'userComped' => (int)$userComped ] ) );
			}

			if ( $doEmailUser && $doEmailUser->isRegistered() ) {
				$emailSent = $report->sendUserEmail( $this->userFactory->newFromUserIdentity( $doEmailUser ) );
				$output->redirect( $pointsCompPage->getFullURL( [ 'emailSent' => (int)$emailSent ] ) );
			}

			return;
		}

		$do = $request->getVal( 'do' );
		$final = $do === 'grantAll' || $do === 'grantAndEmailAll';
		$email = $do === 'emailAll' || $do === 'grantAndEmailAll';

		if ( $report && in_array( $do, [ 'grantAll', 'emailAll', 'grantAndEmailAll' ] ) ) {
			PointsCompJob::queue( [ 'report_id' => $reportId, 'grantAll' => $final, 'emailAll' => $email ] );
			$pointsCompPage	= SpecialPage::getTitleFor( 'PointsComp' );
			$output->redirect( $pointsCompPage->getFullURL( [ 'queued' => 0 ] ) );
			return;
		}

		if ( !$report ) {
			$startTimestamp = $request->getInt( 'start_time' );
			$startTime = strtotime( date( 'Y-m-d', $startTimestamp ) . 'T00:00:00+00:00' );
			// Infer end time as last second of the month. Since switching to monthly
			// stats, the end time isn't needed to generate the report, but it could
			// be useful for data keeping.
			$endTime = strtotime( date( 'Y-m-t', $startTimestamp ) . 'T23:59:59+00:00' );

			$status = PointsCompReport::validateTimeRange( $startTime, $endTime );
			if ( !$status->isGood() ) {
				throw new ErrorPageError( 'points_comp_report_error', $status->getMessage() );
			}

			$minPointThreshold = $request->getInt( 'min_point_threshold' );
			$maxPointThreshold = $request->getVal( 'max_point_threshold' );
			$maxPointThreshold = $maxPointThreshold !== '0' && empty( $maxPointThreshold ) ?
				null :
				(int)$maxPointThreshold;

			$status = PointsCompReport::validatePointThresholds( $minPointThreshold, $maxPointThreshold );
			if ( !$status->isGood() ) {
				throw new ErrorPageError( 'points_comp_report_error', $status->getMessage() );
			}

			$report = new PointsCompReport();
			$report->setMinPointThreshold( $minPointThreshold );
			$report->setMaxPointThreshold( $maxPointThreshold );
			$report->setStartTime( $startTime );
			$report->setEndTime( $endTime );
			$report->save();
			$reportId = $report->getReportId();
		}

		PointsCompJob::queue( [ 'report_id' => $reportId, 'final' => $final, 'email' => $email ] );

		$output->redirect( SpecialPage::getTitleFor( 'PointsComp' )->getFullURL( [ 'queued' => 0 ] ) );
	}

	/**
	 * Download CSV to client.
	 * @return never
	 */
	private function downloadCSV( $csv, $reportId ) {
		$filename = 'points_comp_report_' . $reportId;

		header( "Content-type: text/csv" );
		header( "Content-Disposition: attachment; filename=$filename.csv" );
		header( "Pragma: no-cache" );
		header( "Expires: 0" );

		$output = fopen( "php://output", "w" );
		fwrite( $output, $csv );
		fclose( $output );
		exit;
	}

	/** @inheritDoc */
	public function isListed(): bool {
		return CheevosHelper::isCentralWiki() && $this->getUser()->isAllowed( 'points_comp_reports' );
	}

	/** @inheritDoc */
	public function isRestricted() {
		return true;
	}

	/** @inheritDoc */
	protected function getGroupName() {
		return 'users';
	}
}
