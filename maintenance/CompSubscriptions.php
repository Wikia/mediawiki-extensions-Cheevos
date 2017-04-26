<?php
/**
 * Wiki Points
 * Comp Subscriptions
 *
 * @license     All Rights Reserved
 * @package     DynamicSettings
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

		if ($this->hasOption('monthsAgo')) {
			$monthsAgo = intval($this->getOption('monthsAgo'));

			if (!$monthsAgo) {
				$this->error("Number of monthsAgo is invalid.");
				exit;
			}
		}

		$epochFirst = strtotime('first day of '.($monthsAgo > 0 ? $monthsAgo : 1).' month ago');
		$lastMonthYYYYMM = gmdate('Ym', $epochFirst);

		$where = [
			'yyyymm' => $lastMonthYYYYMM
		];

		$result = $db->select(
			['wiki_points_site_monthly_totals'],
			['count(*) AS total', 'SUM(score) as global_monthly_points'],
			$where,
			__METHOD__,
			[
				'GROUP BY'	=> 'user_id, yyyymm',
				'HAVING'	=> 'global_monthly_points >= '.intval($compedSubscriptionThreshold)
			]
		);
		$total = $result->fetchRow();
		$total = intval($total['total']);

		$totalCompsGiven = 0;
		$totalCompsSkipped = 0;
		$totalPaidSkipped = 0;
		$totalCompsFailed = 0;
		for ($i = 0; $i <= $total; $i = $i + 1000) {
			$result = $db->select(
				['wiki_points_site_monthly_totals'],
				['*', 'SUM(score) as global_monthly_points'],
				$where,
				__METHOD__,
				[
					'GROUP BY'	=> 'user_id, yyyymm',
					'OFFSET'	=> $i,
					'LIMIT'		=> 1000,
					'HAVING'	=> 'global_monthly_points >= '.intval($compedSubscriptionThreshold)
				]
			);

			foreach ($result as $pointData) {
				if ($pointData->global_monthly_points < $compedSubscriptionThreshold) {
					continue;
				}

				$user = User::newFromId($pointData->user_id);
				if (empty($user) || !$user->getId()) {
					continue;
				}

				$lookup = \CentralIdLookup::factory();
				$globalId = $lookup->centralIdFromLocalUser($user, \CentralIdLookup::AUDIENCE_RAW);

				if ($globalId < 1) {
					continue;
				}

				$this->output("{$user->getName()} ({$globalId}) has {$pointData->global_monthly_points} points for {$pointData->yyyymm}...");

				$success = false;

				$subscription = $gamepediaPro->getSubscription($globalId);
				if ($subscription !== false && is_array($subscription)) {
					if ($subscription['plan_id'] !== 'complimentary') {
						//TODO: Have this mark the person in a table for a future comped subscription when the paid subscription expires since the billing system does not support having a paid subscription and comped subscription at the same time.
						$this->output("\n...paid subscription expiring ".date('c', $subscription['expires']->getTimestamp(TS_UNIX)).".\n");
						$totalPaidSkipped++;
						continue;
					}
					$expires = ($subscription['expires'] !== false ? $subscription['expires']->getTimestamp(TS_UNIX) : null);

					if ($newExpires > $expires) {
						$this->output("\n...new comp expires later than old comp, extending from ".date('c', $expires)." to ".date('c', $newExpires));
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
					$this->output("\n...comped!\n");
					$totalCompsGiven++;
				} else {
					$totalCompsFailed++;
					$this->output("\n...failed!\n");
				}
			}
		}

		$this->output("\n{$totalCompsGiven} comps given.\n");
		$this->output("{$totalCompsSkipped} comps skipped.\n");
		$this->output("{$totalPaidSkipped} users with paid subscriptions skipped.\n");
		$this->output("{$totalCompsFailed} comps failed.\n");
	}
}

$maintClass = "CompSubscriptions";
require_once(RUN_MAINTENANCE_IF_MAIN);
