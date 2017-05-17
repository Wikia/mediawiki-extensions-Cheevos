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

		$compedSubscriptionThreshold = intval($config->get('CompedSubscriptionThreshold'));
		if ($this->hasOption('threshold')) {
			if ($this->getOption('threshold') > 0) {
				$compedSubscriptionThreshold = intval($this->getOption('threshold'));
			} else {
				throw new MWException(__METHOD__.': Invalid threshold provided.');
			}
		}

		$compedSubscriptionMonths = intval($config->get('CompedSubscriptionMonths'));

		$monthsAgo = 1;
		if ($this->hasOption('monthsAgo')) {
			$monthsAgo = intval($this->getOption('monthsAgo'));

			if ($monthsAgo < 1) {
				$this->error("Number of monthsAgo is invalid.");
				exit;
			}
		}

		$report = new \Cheevos\Points\PointsCompReport();
		$report->run($this->getOption('threshold'), $monthsAgo, $this->hasOption('final'));
	}
}

$maintClass = "CompSubscriptions";
require_once(RUN_MAINTENANCE_IF_MAIN);
