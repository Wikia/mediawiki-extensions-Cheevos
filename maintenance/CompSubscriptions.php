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

		$this->addOption('monthsAgo', 'How many months to look into the past, defaults to 1 month.');
		$this->addOption('final', 'Finalize, do not do a test run.');
	}

	/**
	 * Run comps.
	 *
	 * @access	public
	 * @return	void
	 */
	public function execute() {
		if (MASTER_WIKI !== true) {
			$this->error("Subscription comps can only be run against the master wiki.");
			exit;
		}

		if (!ExtensionRegistry::getInstance()->isLoaded('Subscription')) {
			$this->error("Extension:Subscription must be loaded for this functionality.");
			exit;
		}

		$db = wfGetDB(DB_MASTER);

		if (!$this->hasOption('final')) {
			$this->output("=================================\n");
			$this->output("TEST RUN - NO COMPS WILL BE GIVEN\n");
			$this->output("=================================\n\n");
		}

		$config = ConfigFactory::getDefaultInstance()->makeConfig('main');
		$compedSubscriptionThreshold = intval($config->get('CompedSubscriptionThreshold'));
		$compedSubscriptionMonths = intval($config->get('CompedSubscriptionMonths'));

		//$epochFirst = strtotime(date('Y-m-d', strtotime('first day of 0 month ago')).'T00:00:00+00:00');
		//$epochLast = strtotime(date('Y-m-d', strtotime('last day of +2 months')).'T23:59:59+00:00');
		$newExpires = strtotime(date('Y-m-d', strtotime('last day of +'.($compedSubscriptionMonths - 1).' months')).'T23:59:59+00:00'); //Get the last day of two months from now, fix the hours:minutes:seconds, and then get the corrected epoch.

		$gamepediaPro = \Hydra\SubscriptionProvider::factory('GamepediaPro');
		\Hydra\Subscription::skipCache(true);

		$this->output("Point threshold is {$compedSubscriptionThreshold} for {$compedSubscriptionMonths} months of comped subscription.\n");
		$this->output("...new comped subscriptions will expire ".date('c', $newExpires)."\n\n");

		$monthsAgo = 1;
		if ($this->hasOption('monthsAgo')) {
			$monthsAgo = intval($this->getOption('monthsAgo'));

			if ($monthsAgo < 1) {
				$this->error("Number of monthsAgo is invalid.");
				exit;
			}
		}

		$filters = [
			'stat'				=> 'wiki_points',
			'limit'				=> 0,
			'sort_direction'	=> 'desc',
			'global'			=> true,
			'start_time'		=> strtotime(date('Y-m-d', strtotime('first day of '.$monthsAgo.' month ago')).'T00:00:00+00:00'),
			'end_time'			=> strtotime(date('Y-m-d', strtotime('last day of last month')).'T23:59:59+00:00')
		];

		try {
			$statProgress = \Cheevos\Cheevos::getStatProgress($filters);
		} catch (\Cheevos\CheevosException $e) {
			throw new \ErrorPageError(wfMessage('cheevos_api_error_title'), wfMessage('cheevos_api_error', $e->getMessage()));
		}

		$report = new \Cheevos\Points\PointsCompReport();
		$report->setPointThreshold($compedSubscriptionThreshold);
		$report->setMonthStart($filters['start_time']);
		$report->setMonthEnd($filters['end_time']);

		$totalCompsGiven = 0;
		$totalCompsSkipped = 0;
		$totalPaidSkipped = 0;
		$totalCompsFailed = 0;

		foreach ($statProgress as $progress) {
			$isNew = false;
			$isExtended = false;

			if ($progress->getCount() < $compedSubscriptionThreshold) {
				continue;
			}

			$globalId = $progress->getUser_Id();
			if ($globalId < 1) {
				continue;
			}

			$this->output("{$globalId} has {$progress->getCount()} points for {$filters['start_time']}-{$filters['end_time']}...");

			$success = false;

			$subscription = $gamepediaPro->getSubscription($globalId);
			$expires = false;
			if ($subscription !== false && is_array($subscription)) {
				if ($subscription['plan_id'] !== 'complimentary') {
					//TODO: Have this mark the person in a table for a future comped subscription when the paid subscription expires since the billing system does not support having a paid subscription and comped subscription at the same time.
					$this->output("\n...paid subscription expiring ".date('c', $subscription['expires']->getTimestamp(TS_UNIX)).".\n");
					$totalPaidSkipped++;
					continue;
				}
				$expires = ($subscription['expires'] !== false ? $subscription['expires']->getTimestamp(TS_UNIX) : null);

				if ($newExpires > $expires) {
					$isExtended = true;
					$this->output("\n...new comp expires later than old comp, extending from ".date('c', $expires)." to ".date('c', $newExpires));
					$expires = $newExpires;
					if ($this->hasOption('final')) {
						$gamepediaPro->cancelCompedSubscription($globalId);
					}
				} else {
					$this->output("\n...already has a valid longer comp expiring ".date('c', $expires).".\n");
					$totalCompsSkipped++;
					continue;
				}
			}

			if ($this->hasOption('final')) {
				if (!$isExtended) {
					$isNew = true;
				}

				$comp = $gamepediaPro->createCompedSubscription($globalId, $compedSubscriptionMonths);

				if ($comp !== false) {
					$success = true;
					$body = [
						'text' => wfMessage('automatic_comp_email_body_text', $user->getName())->text(),
						'html' => wfMessage('automatic_comp_email_body', $user->getName())->text()
					];
					$user->sendMail(wfMessage('automatic_comp_email_subject')->parse(), $body);
				}
			} else {
				$success = true;
			}

			if ($success) {
				$report->addRow($globalId, $progress->getCount(), $isNew, $isExtended, $expires);
				$this->output("\n...comped!\n");
				$totalCompsGiven++;
			} else {
				$totalCompsFailed++;
				$this->output("\n...failed!\n");
			}
		}
		$report->save();

		$this->output("\n{$totalCompsGiven} comps given.\n");
		$this->output("{$totalCompsSkipped} comps skipped.\n");
		$this->output("{$totalPaidSkipped} users with paid subscriptions skipped.\n");
		$this->output("{$totalCompsFailed} comps failed.\n");
	}
}

$maintClass = "CompSubscriptions";
require_once(RUN_MAINTENANCE_IF_MAIN);
