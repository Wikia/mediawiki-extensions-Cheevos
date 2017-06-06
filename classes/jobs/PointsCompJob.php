<?php
/**
 * Curse Inc.
 * Cheevos
 * Points Comp Job
 *
 * @author		Alexia E. Smith
 * @copyright	(c) 2017 Curse Inc.
 * @license		All Rights Reserved
 * @package		Cheevos
 * @link		http://www.curse.com/
 *
**/

namespace Cheevos\Job;

class PointsCompJob extends \SyncService\Job {
	/**
	 * Periodic schedule to run like a cron job.  Leave as false to not have a schedule.
	 *
	 * @var		array
	 */
	static public $schedule = [];

	/**
	 * Runs points compensation reports and grants through the command line maintenance script.
	 *
	 * @access	public
	 * @param	array	Named Arguments:
	 * - threshold		[Integer] Point threshold for the report.
	 * - start_time		[Integer] Unix timestamp of the report start range.
	 * - end_time		[Integer] Unix timestamp of the report end range.
	 * - final			[Boolean] Finalize this report by granting compensations.
	 * - email			[Boolean] Email users affected.
	 * - report_id		[Optional] Existing report ID to update.
	 * @return	integer	Exit value for this thread.
	 */
	public function execute($args = []) {
		$minPointThreshold = (isset($args['min_point_threshold']) ? intval($args['min_point_threshold']) : null);
		$maxPointThreshold = (isset($args['max_point_threshold']) ? intval($args['max_point_threshold']) : null);
		$startTime = intval($args['start_time']);
		$endTime = intval($args['end_time']);
		$final = boolval($args['final']);
		$email = boolval($args['email']);
		$reportId = intval($args['report_id']);

		if ($reportId > 0) {
			$report = \Cheevos\Points\PointsCompReport::newFromId($reportId);
			if (!$report) {
				$this->outputLine(__METHOD__.": Bad report ID.", time());
				return 1;
			}
		} else {
			$report = new \Cheevos\Points\PointsCompReport();
		}

		try {
			$report->run($minPointThreshold, $maxPointThreshold, $startTime, $endTime, $final, $email);
		} catch (\MWException $e) {
			$this->outputLine(__METHOD__.": Failed to run report due to: ".$e->getMessage(), time());
			return 1;
		}

		return 0;
	}

	/**
	 * Return cron schedule if applicable.
	 *
	 * @access	public
	 * @return	mixed	False for no schedule or an array of schedule information.
	 */
	static public function getSchedule() {
		$config = \ConfigFactory::getDefaultInstance()->makeConfig('main');
		$maxPointThreshold = intval($config->get('CompedSubscriptionThreshold'));
		return [
			[
				'minutes' => 0,
				'hours' => 8,
				'days' => 2,
				'months' => '*',
				'weekdays' => '*',
				'arguments' => [
					'min_point_threshold'	=> 0,
					'max_point_threshold'	=> $maxPointThreshold,
					'start_time'			=> strtotime(date('Y-m-d', strtotime('first day of 1 month ago')).'T00:00:00+00:00'),
					'end_time'				=> strtotime(date('Y-m-d', strtotime('last day of last month')).'T23:59:59+00:00')
				]
			]
		];
	}
}
