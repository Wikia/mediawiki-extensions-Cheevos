<?php
/**
 * Curse Inc.
 * Cheevos
 * Comp Subscriptions Maintenance Script
 *
 * @package   Cheevos
 * @author    Alexia E. Smith
 * @copyright (c) 2016 Curse Inc.
 * @license   GPL-2.0-or-later
 * @link      https://gitlab.com/hydrawiki/extensions/cheevos
 */

namespace Cheevos;

require_once dirname( __DIR__, 3 ) . '/maintenance/Maintenance.php';

use Cheevos\Points\PointsCompReport;
use ExtensionRegistry;
use Maintenance;

class CompSubscriptions extends Maintenance {

	public function __construct() {
		parent::__construct();

		$this->addDescription(
			"Comp subscriptions to those who hit a monthly configured point value." .
			"Requires Extension:Subscription to be installed."
		);

		$this->addOption( 'monthsAgo', 'How many months to look into the past, defaults to 1 month.', false, true );
		$this->addOption(
			'timeRange',
			'Timestamp range to use for the report. ' .
			'Overrides monthsAgo.  Format: {startTime}-{endTime} 1493596800-1496275199',
			false,
			true
		);
		$this->addOption( 'threshold', 'Override the default point threshold.', false, true );
		$this->addOption( 'final', 'Finalize, do not do a test run.', false, false );
	}

	/** @inheritDoc */
	public function execute() {
		if ( !ExtensionRegistry::getInstance()->isLoaded( 'Subscription' ) ) {
			$this->error( "Extension:Subscription must be loaded for this functionality." );
			exit;
		}

		$compedSubscriptionThreshold =
			(int)$this->getOption( 'threshold', $this->getConfig()->get( 'CompedSubscriptionThreshold' ) );

		$status = PointsCompReport::validatePointThresholds( $compedSubscriptionThreshold );
		if ( !$status->isGood() ) {
			$this->error( $status->getMessage()->plain(), 1 );
		}

		$monthsAgo = (int)$this->getOption( 'monthsAgo', 1 );
		if ( $monthsAgo < 1 ) {
			$this->error( "Number of monthsAgo is invalid.", 1 );
		}

		$startTime = strtotime(
			date(
				'Y-m-d',
				strtotime( 'first day of ' . $monthsAgo . ' month ago' ) ) . 'T00:00:00+00:00'
		);
		$endTime = strtotime( date( 'Y-m-d', strtotime( 'last day of last month' ) ) . 'T23:59:59+00:00' );

		if ( $this->hasOption( 'timeRange' ) ) {
			[ $_startTime, $_endTime ] = explode( '-', $this->getOption( 'timeRange' ) );
			$startTime = (int)$_startTime;
			$endTime = (int)$_endTime;
		}
		$status = PointsCompReport::validateTimeRange( $startTime, $endTime );
		if ( !$status->isGood() ) {
			$this->error( $status->getMessage()->plain(), 1 );
		}

		$report = new PointsCompReport();
		$report->run( $compedSubscriptionThreshold, null, $startTime, $endTime, $this->hasOption( 'final' ) );
	}
}

$maintClass = CompSubscriptions::class;
require_once RUN_MAINTENANCE_IF_MAIN;
