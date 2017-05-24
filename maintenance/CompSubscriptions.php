<?php
/**
 * Curse Inc.
 * Cheevos
 * Comp Subscriptions Maintenance Script
 *
 * @author		Alexia E. Smith
 * @copyright	(c) 2016 Curse Inc.
 * @license		All Rights Reserved
 * @package		Cheevos
 * @link		http://www.curse.com/
 *
**/

require_once(dirname(dirname(dirname(__DIR__))).'/maintenance/Maintenance.php');

class CompSubscriptions extends Maintenance {
	/**
	 * Main Constructor
	 *
	 * @access	public
	 * @return	void
	 */
	public function __construct() {
		parent::__construct();

		$this->mDescription = "Comp subscriptions to those who hit a monthly configured point value.  Requires Extension:Subscription to be installed.";

		$this->addOption('monthsAgo', 'How many months to look into the past, defaults to 1 month.', false, true);
		$this->addOption('timeRange', 'Timestamp range to use for the report.  Overrides monthsAgo.  Format: {startTime}-{endTime} 1493596800-1496275199', false, true);
		$this->addOption('threshold', 'Override the default point threshold.', false, true);
		$this->addOption('final', 'Finalize, do not do a test run.', false, false);
	}

	/**
	 * Run comps.
	 *
	 * @access	public
	 * @return	void
	 */
	public function execute() {
		if (!ExtensionRegistry::getInstance()->isLoaded('Subscription')) {
			$this->error("Extension:Subscription must be loaded for this functionality.");
			exit;
		}

		$db = wfGetDB(DB_MASTER);

		$config = ConfigFactory::getDefaultInstance()->makeConfig('main');

		$maxPointThreshold = intval($config->get('CompedSubscriptionThreshold'));
		if ($this->hasOption('threshold')) {
			$maxPointThreshold = intval($this->getOption('threshold'));
		}
		$status = \Cheevos\Points\PointsCompReport::validatePointThresholds(0, $maxPointThreshold);
		if (!$status->isGood()) {
			$this->error($status->getMessage()->plain(), 1);
		}

		$compedSubscriptionMonths = intval($config->get('CompedSubscriptionMonths'));

		$monthsAgo = 1;
		if ($this->hasOption('monthsAgo')) {
			$monthsAgo = intval($this->getOption('monthsAgo'));

			if ($monthsAgo < 1) {
				$this->error("Number of monthsAgo is invalid.", 1);
			}
		}
		$startTime = strtotime(date('Y-m-d', strtotime('first day of '.$monthsAgo.' month ago')).'T00:00:00+00:00');
		$endTime = strtotime(date('Y-m-d', strtotime('last day of last month')).'T23:59:59+00:00');

		if ($this->hasOption('timeRange')) {
			list($_startTime, $_endTime) = explode('-', $this->getOption('timeRange'));
			$startTime = intval($_startTime);
			$endTime = intval($_endTime);
		}
		$status = \Cheevos\Points\PointsCompReport::validateTimeRange($startTime, $endTime);
		if (!$status->isGood()) {
			$this->error($status->getMessage()->plain(), 1);
		}

		$report = new \Cheevos\Points\PointsCompReport();
		$report->run(0, $maxPointThreshold, $startTime, $endTime, $this->hasOption('final'));
	}
}

$maintClass = "CompSubscriptions";
require_once(RUN_MAINTENANCE_IF_MAIN);
