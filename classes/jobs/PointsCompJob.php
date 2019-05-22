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
**/

namespace Cheevos\Job;

class PointsCompJob extends \SyncService\Job {
	/**
	 * Runs points compensation reports and grants through the command line maintenance script.
	 *
	 * @param  array	Named Arguments:
	 * - threshold		[Integer] Point threshold for the report.
	 * - start_time		[Integer] Unix timestamp of the report start range.
	 * - end_time		[Integer] Unix timestamp of the report end range.
	 * - final			[Boolean] Finalize this report by granting compensations.
	 * - email			[Boolean] Email users affected.
	 * - report_id		[Optional] Existing report ID to update.
	 *
	 * @return integer	Exit value for this thread.
	 */
	public function execute($args = []) {
		$minPointThreshold = (isset($args['min_point_threshold']) ? intval($args['min_point_threshold']) : null);
		$maxPointThreshold = (isset($args['max_point_threshold']) ? intval($args['max_point_threshold']) : null);
		$startTime = (isset($args['start_time']) ? intval($args['start_time']) : 0);
		$endTime = (isset($args['end_time']) ? intval($args['end_time']) : 0);
		$final = (isset($args['final']) ? boolval($args['final']) : false);
		$email = (isset($args['email']) ? boolval($args['email']) : false);
		$reportId = (isset($args['report_id']) ? intval($args['report_id']) : null);

		sleep(2); // Database transaction commits on AWS are slow.

		if ($reportId > 0) {
			$report = \Cheevos\Points\PointsCompReport::newFromId($reportId);
			if (!$report) {
				$this->outputLine(__METHOD__ . ": Bad report ID.", time());
				return 1;
			}
		} else {
			$report = new \Cheevos\Points\PointsCompReport();
		}

		try {
			$skipReport = false;
			if (isset($args['grantAll']) && $args['grantAll'] = true) {
				$report->compAllSubscriptions();
				$skipReport = true;
			}
			if (isset($args['emailAll']) && $args['emailAll'] = true) {
				$report->sendAllEmails();
				$skipReport = true;
			}

			if (!$skipReport) {
				$report->run($minPointThreshold, $maxPointThreshold, $startTime, $endTime, $final, $email);
			}
		} catch (\MWException $e) {
			$this->outputLine(__METHOD__ . ": Failed to run report due to: " . $e->getMessage(), time());
			return 1;
		}

		return 0;
	}
}
