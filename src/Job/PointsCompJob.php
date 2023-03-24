<?php
/**
 * Curse Inc.
 * Cheevos
 * Points Comp Job
 *
 * @package   Cheevos
 * @author    Alexia E. Smith
 * @copyright (c) 2017 Curse Inc.
 * @license   GPL-2.0-or-later
 * @link      https://gitlab.com/hydrawiki/extensions/cheevos
 */

namespace Cheevos\Job;

use Cheevos\Points\PointsCompReport;
use Job;
use MediaWiki\MediaWikiServices;
use MWException;

class PointsCompJob extends Job {
	private const COMMAND = 'Cheevos\Job\PointsCompJob';

	/**
	 * Queue a new job.
	 *
	 * @param array $parameters Named arguments passed by the command that queued this job.
	 *                          - threshold		[Integer] Point threshold for the report.
	 *                          - start_time	[Integer] Unix timestamp of the report start range.
	 *                          - end_time		[Integer] Unix timestamp of the report end range.
	 *                          - final			[Boolean] Finalize this report by granting compensations.
	 *                          - email			[Boolean] Email users affected.
	 *                          - report_id		[Optional] Existing report ID to update.
	 *
	 * @return void
	 */
	public static function queue( array $parameters = [] ) {
		$job = new self( self::COMMAND, $parameters );
		MediaWikiServices::getInstance()->getJobQueueGroup()->lazyPush( $job );
	}

	/**
	 * Points Comp Job
	 *
	 * @return bool Success
	 */
	public function run() {
		$args = $this->getParams();

		$minPointThreshold = isset( $args['min_point_threshold'] ) ? (int)$args[ 'min_point_threshold' ] : null;
		$maxPointThreshold = isset( $args['max_point_threshold'] ) ? (int)$args[ 'max_point_threshold' ] : null;
		$startTime = isset( $args['start_time'] ) ? (int)$args[ 'start_time' ] : 0;
		$endTime = isset( $args['end_time'] ) ? (int)$args[ 'end_time' ] : 0;
		$final = isset( $args[ 'final' ] ) && $args[ 'final' ];
		$email = isset( $args[ 'email' ] ) && $args[ 'email' ];
		$reportId = isset( $args['report_id'] ) ? (int)$args[ 'report_id' ] : null;

		// Wait for any lag, since this job was created immediately after the report was written:
		$lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
		$dbr = $lb->getConnection( DB_REPLICA );
		$lb->waitForPrimaryPos( $dbr );

		if ( $reportId > 0 ) {
			$report = PointsCompReport::newFromId( $reportId );
			if ( !$report ) {
				$this->setLastError( "Bad report ID." );
				return false;
			}
		} else {
			$report = new PointsCompReport();
		}

		try {
			$skipReport = false;
			if ( isset( $args['grantAll'] ) && $args['grantAll'] ) {
				$report->compAllSubscriptions();
				$skipReport = true;
			}
			if ( isset( $args['emailAll'] ) && $args['emailAll'] ) {
				$report->sendAllEmails();
				$skipReport = true;
			}

			if ( !$skipReport ) {
				$report->run( $minPointThreshold, $maxPointThreshold, $startTime, $endTime, $final, $email );
			}
		} catch ( MWException $e ) {
			$this->setLastError( "Failed to run report due to: " . $e->getMessage() );
			return false;
		}

		return true;
	}

	/** @inheritDoc */
	public function allowRetries() {
		return false;
	}
}
