<?php
/**
 * Curse Inc.
 * Cheevos
 * Points Comp Report
 *
 * @package   Cheevos
 * @author    Alexia E. Smith
 * @copyright (c) 2017 Curse Inc.
 * @license   GPL-2.0-or-later
 * @link      https://gitlab.com/hydrawiki/extensions/cheevos
**/

namespace Cheevos\Points;

/**
 * Class containing some business and display logic for points blocks
 */
class PointsCompReport {
	/**
	 * Report Data
	 * [{database row}]
	 *
	 * @var array
	 */
	private $reportData = [];

	/**
	 * Report User Data
	 * [$globalId => {database row}]
	 *
	 * @var array
	 */
	private $reportUser = [];

	/**
	 * Main Constructor
	 *
	 * @param array	Report ID
	 *
	 * @return void
	 */
	public function __construct($reportId = 0) {
		$this->reportData['report_id'] = intval($reportId);
	}

	/**
	 * Load a new report object from the report ID.
	 *
	 * @param integer	Report ID
	 *
	 * @return mixed	PointsCompReport object or null if it does not exist.
	 */
	public static function newFromId($id) {
		$report = new self($id);

		$success = $report->load();

		return ($success ? $report : null);
	}

	/**
	 * Load a new report object from a database row.
	 *
	 * @param array	Report Row from the Database
	 *
	 * @return object	PointsCompReport
	 */
	private static function newFromRow($row) {
		$report = new self($row['report_id']);
		$report->reportData = $row;

		return $report;
	}

	/**
	 * Load information from the database.
	 *
	 * @return boolean	Sucess
	 */
	private function load() {
		$db = wfGetDB(DB_MASTER);

		$result = $db->select(
			['points_comp_report'],
			['*'],
			['report_id' => $this->reportData['report_id']],
			__METHOD__
		);
		$report = $result->fetchRow();
		if (empty($report)) {
			return false;
		}
		$this->reportData = $report;

		$result = $db->select(
			['points_comp_report_user'],
			['*'],
			['report_id' => $this->reportData['report_id']],
			__METHOD__,
			[
				'ORDER BY'	=> 'global_id ASC'
			]
		);

		if (!empty($this->reportUser)) {
			$this->reportUser = [];
		}
		while ($row = $result->fetchRow()) {
			if ($row['global_id'] == 0) {
				continue;
			}

			$this->reportUser[$row['global_id']] = $row;
		}

		return boolval($this->reportData['report_id']);
	}

	/**
	 * Save to database.
	 *
	 * @return boolean	Success
	 */
	public function save() {
		$db = wfGetDB(DB_MASTER);

		$this->reportData['run_time'] = time();
		$reportData = $this->reportData;
		unset($reportData['report_id']);
		$db->startAtomic(__METHOD__);
		if ($this->reportData['report_id'] < 1) {
			$success = $db->insert(
				'points_comp_report',
				$reportData,
				__METHOD__
			);

			$this->reportData['report_id'] = intval($db->insertId());
			if (!$success || !$this->reportData['report_id']) {
				throw new MWException(__METHOD__ . ': Could not get a new report ID.');
			}
		} else {
			$success = $db->update(
				'points_comp_report',
				$reportData,
				['report_id' => $this->reportData['report_id']],
				__METHOD__
			);
		}

		foreach ($this->reportUser as $globalId => $data) {
			$data['report_id'] = $this->reportData['report_id'];
			$data['start_time'] = $this->reportData['start_time'];
			$data['end_time'] = $this->reportData['end_time'];
			$db->upsert(
				'points_comp_report_user',
				$data,
				['report_id_global_id'],
				[
					'comp_new'			=> $data['comp_new'],
					'comp_extended'		=> $data['comp_extended'],
					'comp_failed'		=> $data['comp_failed'],
					'comp_skipped'		=> $data['comp_skipped'],
					'comp_performed'	=> $data['comp_performed'],
					'email_sent'		=> $data['email_sent']
				],
				__METHOD__
			);
		}
		$db->endAtomic(__METHOD__);

		$this->updateStats();

		return true;
	}

	/**
	 * Update the report statistics into the database.
	 *
	 * @return void
	 */
	public function updateStats() {
		$db = wfGetDB(DB_MASTER);

		foreach (['comp_new', 'comp_extended', 'comp_failed', 'comp_skipped', 'comp_performed', 'email_sent'] as $stat) {
			$result = $db->select(
				['points_comp_report_user'],
				['count(' . $stat . ') as total'],
				[
					$stat => 1,
					'report_id' => $this->reportData['report_id']
				],
				__METHOD__
			);
			$total = $result->fetchRow();
			$data[$stat] = intval($total['total']);
		}
		$db->update(
			'points_comp_report',
			$data,
			['report_id' => $this->reportData['report_id']],
			__METHOD__
		);
	}

	/**
	 * Load a list of basic report information.
	 *
	 * @param integer	Start Position
	 * @param integer	Maximum Items to Return
	 *
	 * @return array	Multidimensional array of ['total' => $total, $reportId => [{reportUser}]]
	 */
	public static function getReportsList($start = 0, $itemsPerPage = 50) {
		$db = wfGetDB(DB_MASTER);

		$result = $db->select(
			['points_comp_report'],
			['*'],
			[],
			__METHOD__,
			[
				'ORDER BY'	=> 'report_id DESC',
				'OFFSET'	=> $start,
				'LIMIT'		=> $itemsPerPage
			]
		);

		$reports = [];
		while ($row = $result->fetchRow()) {
			$reports[$row['report_id']] = self::newFromRow($row);
		}

		$result = $db->select(
			['points_comp_report'],
			['count(*) as total'],
			[],
			__METHOD__,
			[
				'ORDER BY'	=> 'report_id DESC'
			]
		);
		$total = $result->fetchRow();
		$total = intval($total['total']);

		return ['total' => $total, 'reports' => $reports];
	}

	/**
	 * Get the report ID.
	 *
	 * @return integer	This report ID.
	 */
	public function getReportId() {
		return $this->reportData['report_id'];
	}

	/**
	 * Get when this reported was generated.
	 *
	 * @return integer	Run time Unix timestamp.
	 */
	public function getRunTime() {
		return $this->reportData['run_time'];
	}

	/**
	 * Get the minimum point threshold for this report.
	 *
	 * @return integer	Minimum point threshold.
	 */
	public function getMinPointThreshold() {
		return intval($this->reportData['min_points']);
	}

	/**
	 * Set the minimum point threshold for this report.
	 *
	 * @param integer	Minimum point threshold for this report.
	 *
	 * @return void
	 */
	public function setMinPointThreshold($minPointThreshold) {
		$this->reportData['min_points'] = intval($minPointThreshold);
	}

	/**
	 * Get the maximum point threshold for this report.
	 *
	 * @return integer	Maximum point threshold.
	 */
	public function getMaxPointThreshold() {
		return ($this->reportData['max_points'] === null ? null : intval($this->reportData['max_points']));
	}

	/**
	 * Set the maximum point threshold for this report.
	 *
	 * @param mixed	Maximum point threshold for this report or null for no maximum.
	 *
	 * @return void
	 */
	public function setMaxPointThreshold($maxPointThreshold = null) {
		$this->reportData['max_points'] = ($maxPointThreshold === null ? null : intval($maxPointThreshold));
	}

	/**
	 * Validate point thresholds.
	 *
	 * @param integer	Minimum Point Threshold
	 * @param integer	[Optional] Maximum Point Threshold
	 *
	 * @return object	Status
	 */
	public static function validatePointThresholds($minPointThreshold, $maxPointThreshold = null) {
		$minPointThreshold = intval($minPointThreshold);

		if ($maxPointThreshold !== null) {
			$maxPointThreshold = intval($maxPointThreshold);
			if ($maxPointThreshold <= 0 || $maxPointThreshold < $minPointThreshold) {
				return \Status::newFatal('invalid_maximum_threshold');
			}
		}

		if ($minPointThreshold < 0) {
			return \Status::newFatal('invalid_minimum_threshold');
		}

		return \Status::newGood();
	}

	/**
	 * Get the time period start timestamp.
	 *
	 * @return integer	Unix timestamp for the time period start.
	 */
	public function getStartTime() {
		return intval($this->reportData['start_time']);
	}

	/**
	 * Set the time period start timestamp.
	 *
	 * @param integer	Unix timestamp for the time period start.
	 *
	 * @return void
	 */
	public function setStartTime($startTime) {
		$this->reportData['start_time'] = intval($startTime);
	}

	/**
	 * Get the time period end timestamp.
	 *
	 * @return integer	Unix timestamp for the time period end.
	 */
	public function getEndTime() {
		return intval($this->reportData['end_time']);
	}

	/**
	 * Set the time period end timestamp.
	 *
	 * @param integer	Unix timestamp for the time period end.
	 *
	 * @return void
	 */
	public function setEndTime($endTime) {
		$this->reportData['end_time'] = intval($endTime);
	}

	/**
	 * Validate time range.
	 *
	 * @param integer	Start Timestamp
	 * @param integer	End Timestamp
	 *
	 * @return object	Status
	 */
	public static function validateTimeRange($startTime, $endTime) {
		$startTime = intval($startTime);
		$endTime = intval($endTime);

		if ($endTime <= 0 || $endTime < $startTime) {
			return \Status::newFatal('invalid_end_time');
		}

		if ($startTime < 0) {
			// Yes, nothing before 1970 exists.
			return \Status::newFatal('invalid_start_time');
		}

		if ($startTime == $endTime) {
			return \Status::newFatal('invalid_start_end_time_equal');
		}

		return \Status::newGood();
	}

	/**
	 * Return the total new comps.
	 *
	 * @return integer	Total new comps.
	 */
	public function getTotalNew() {
		return intval($this->reportData['comp_new']);
	}

	/**
	 * Return the total extended comps.
	 *
	 * @return integer	Total extended comps.
	 */
	public function getTotalExtended() {
		return intval($this->reportData['comp_extended']);
	}

	/**
	 * Return the total failed comps.
	 *
	 * @return integer	Total failed comps.
	 */
	public function getTotalFailed() {
		return intval($this->reportData['comp_failed']);
	}

	/**
	 * Return the total skipped comps.
	 *
	 * @return integer	Total skipped comps.
	 */
	public function getTotalSkipped() {
		return intval($this->reportData['comp_skipped']);
	}

	/**
	 * Return the total comps actually performed.
	 *
	 * @return integer	Total comps actually performed.
	 */
	public function getTotalPerformed() {
		return intval($this->reportData['comp_performed']);
	}

	/**
	 * Return the total users emailed.
	 *
	 * @return integer	Total users emailed.
	 */
	public function getTotalEmailed() {
		return intval($this->reportData['email_sent']);
	}

	/**
	 * Is this report finished running?
	 *
	 * @return boolean	Report Finished
	 */
	public function isFinished() {
		return boolval($this->reportData['finished']);
	}

	/**
	 * Set if the report is finished running.
	 *
	 * @param boolean	Report Finished
	 *
	 * @return void
	 */
	public function setFinished($finished = false) {
		$this->reportData['finished'] = intval(boolval($finished));
	}

	/**
	 * Add new report row.
	 * Will overwrite existing rows with the same global ID.
	 *
	 * @param integer	Global User ID
	 * @param integer	Aggegrate Points for the month range.
	 * @param boolean	Is this a new comp for this month range?(User did not have previously or consecutively.)
	 * @param boolean	Is this an extended comp from a previous one?
	 * @param boolean	Did the billing system fail to do the comp?(Or did we just not run it yet?)
	 * @param integer	Unix timestamp for when the current comp expires.
	 * @param integer	Unix timestamp for when the new comp expires.(If applicable.)
	 * @param boolean	Was the new comp actually performed?
	 * @param boolean	User emailed to let them know about their comp?
	 *
	 * @return void
	 */
	public function addRow($globalId, $points, $compNew, $compExtended, $compFailed, $currentCompExpires, $newCompExpires, $compPerformed = false, $emailSent = false) {
		$data = [
			'global_id'				=> intval($globalId),
			'points'				=> intval($points),
			'comp_new'				=> boolval($compNew),
			'comp_extended'			=> boolval($compExtended),
			'comp_failed'			=> boolval($compFailed),
			'current_comp_expires'	=> intval($currentCompExpires),
			'new_comp_expires'		=> intval($newCompExpires),
			'comp_performed'		=> boolval($compPerformed),
			'email_sent'			=> boolval($emailSent)
		];

		if (empty($data['global_id'])) {
			throw new MWException(__METHOD__ . ': Invalid global user ID provided.');
		}

		if (isset($this->reportUser[$globalId])) {
			$this->reportUser[$globalId] = array_merge($this->reportUser[$globalId], $data);
		} else {
			$this->reportUser[$globalId] = $data;
		}
	}

	/**
	 * Get the next row in the report data.
	 *
	 * @return mixed	Report row data or false for no more values.
	 */
	public function getNextRow() {
		$return = current($this->reportUser);
		next($this->reportUser);
		return $return;
	}

	/**
	 * Run the report.
	 * Threshold, Start Time, and End Time are ignored if the report was already run previously.  Their previous values will be used.
	 *
	 * @param integer	[Optional] Point Threshold
	 * @param integer	[Optional] Unix timestamp of the start time.
	 * @param integer	[Optional] Unix timestamp of the end time.
	 * @param integer	[Optional] Actually run comps.
	 * @param integer	[Optional] Send email to affected users.
	 *
	 * @return void
	 */
	public function run($minPointThreshold = null, $maxPointThreshold = null, $timeStart = 0, $timeEnd = 0, $final = false, $email = false) {
		if (!\ExtensionRegistry::getInstance()->isLoaded('Subscription')) {
			throw new \MWException(__METHOD__ . ": Extension:Subscription must be loaded for this functionality.");
		}

		if ($this->reportData['report_id'] > 0) {
			$minPointThreshold = $this->getMinPointThreshold();
			$maxPointThreshold = $this->getMaxPointThreshold();
			$timeStart = $this->getStartTime();
			$timeEnd = $this->getEndTime();
		}

		$db = wfGetDB(DB_MASTER);

		$config = \ConfigFactory::getDefaultInstance()->makeConfig('main');

		if ($minPointThreshold !== null) {
			$minPointThreshold = intval($minPointThreshold);
		} else {
			$minPointThreshold = intval($config->get('CompedSubscriptionThreshold'));
		}

		if ($maxPointThreshold !== null) {
			$maxPointThreshold = intval($maxPointThreshold);
		}
		$status = self::validatePointThresholds($minPointThreshold, $maxPointThreshold);
		if (!$status->isGood()) {
			throw new \MWException(__METHOD__ . ': ' . $status->getMessage());
		}

		// Number of complimentary months someone is given.
		$compedSubscriptionMonths = intval($config->get('CompedSubscriptionMonths'));

		$timeStart = intval($timeStart);
		$timeEnd = intval($timeEnd);
		if ($timeEnd <= $timeStart || $timeStart == 0 || $timeEnd == 0) {
			throw new \MWException(__METHOD__ . ': The time range is invalid.');
		}

		$newExpiresDT = new \DateTime('now');
		$newExpiresDT->add(new \DateInterval('P' . $compedSubscriptionMonths . 'M'));
		$newExpires = $newExpiresDT->getTimestamp();

		$gamepediaPro = \Hydra\SubscriptionProvider::factory('GamepediaPro');
		\Hydra\Subscription::skipCache(true);

		$filters = [
			'stat'				=> 'wiki_points',
			'limit'				=> 0,
			'sort_direction'	=> 'desc',
			'global'			=> true,
			'start_time'		=> $timeStart,
			'end_time'			=> $timeEnd
		];

		try {
			$statProgress = \Cheevos\Cheevos::getStatProgress($filters);
		} catch (\Cheevos\CheevosException $e) {
			throw new \MWException($e->getMessage());
		}

		$this->setMinPointThreshold($minPointThreshold);
		$this->setMaxPointThreshold($maxPointThreshold);
		$this->setStartTime($filters['start_time']);
		$this->setEndTime($filters['end_time']);

		foreach ($statProgress as $progress) {
			$isExtended = false;
			$currentExpires = 0; // $newExpires is set outside of the loop up above.

			if ($progress->getCount() < $minPointThreshold) {
				continue;
			}
			if ($maxPointThreshold !== null && $progress->getCount() > $maxPointThreshold) {
				continue;
			}

			$globalId = $progress->getUser_Id();
			if ($globalId < 1) {
				continue;
			}

			$success = false;

			$subscription = $this->getSubscription($globalId, $gamepediaPro);
			if ($subscription['paid']) {
				// Do not mess with paid subscriptions.
				$this->addRow($globalId, $progress->getCount(), false, false, false, $subscription['expires'], 0, false, false);
				continue;
			} elseif ($subscription['hasSubscription'] && $newExpires > $subscription['expires']) {
				$isExtended = true;
			}

			if ($final) {
				if ($isExtended) {
					$gamepediaPro->cancelCompedSubscription($globalId);
				}
				$comp = $gamepediaPro->createCompedSubscription($globalId, $compedSubscriptionMonths);

				if ($comp !== false) {
					$success = true;
					if ($email) {
						$emailSent = self::sendUserEmail($user);
					}
				}
			}

			$this->addRow($globalId, $progress->getCount(), !$isExtended, $isExtended, !$success, $subscription['expires'], $newExpires, $success, $emailSent);
		}
		$this->setFinished(true);
		$this->save();
	}

	/**
	 * Get current subscription status.
	 *
	 * @param integer	User Global Id
	 * @param object	Subscription Provider
	 *
	 * @return array	Array of boolean status flags.
	 */
	public function getSubscription($globalId, \Hydra\SubscriptionProvider $provider) {
		$hasSubscription = false;
		$paid = false;
		$expires = null;
		$subscription = $provider->getSubscription($globalId);
		if ($subscription !== false && is_array($subscription)) {
			$hasSubscription = true;
			$expires = intval($subscription['expires'] !== false ? $subscription['expires']->getTimestamp(TS_UNIX) : null);
			if ($subscription['plan_id'] !== 'complimentary') {
				$paid = true;
			}
		}
		return [
			'hasSubscription'	=> $hasSubscription,
			'paid'				=> $paid,
			'expires'			=> $expires
		];
	}

	/**
	 * Run through all users and comp subscriptions.
	 *
	 * @return void
	 */
	public function compAllSubscriptions() {
		$config = \ConfigFactory::getDefaultInstance()->makeConfig('main');
		$compedSubscriptionMonths = intval($config->get('CompedSubscriptionMonths'));
		foreach ($this->reportUser as $globalId => $data) {
			$this->compSubscription($globalId, $compedSubscriptionMonths);
		}
	}

	/**
	 * Create a subscription compensation in the billing service.
	 * Will fail if a valid paid or comped subscription already exists and is longer than the proposed new comp length.
	 *
	 * @param integer	Global User ID
	 * @param integer	Number of months into the future to compensate.
	 *
	 * @return boolean	Success
	 */
	public function compSubscription($globalId, $numberOfMonths) {
		$gamepediaPro = \Hydra\SubscriptionProvider::factory('GamepediaPro');

		$newExpiresDT = new \DateTime('now');
		$newExpiresDT->add(new \DateInterval('P' . $numberOfMonths . 'M'));
		$newExpires = $newExpiresDT->getTimestamp();

		$subscription = $this->getSubscription($globalId, $gamepediaPro);
		if ($subscription['paid'] === true) {
			// Do not mess with paid subscriptions.
			return false;
		} elseif ($subscription['hasSubscription'] && $newExpires > $subscription['expires']) {
			$gamepediaPro->cancelCompedSubscription($globalId);
		}

		$comp = $gamepediaPro->createCompedSubscription($globalId, $numberOfMonths);

		if ($comp !== false) {
			$db = wfGetDB(DB_MASTER);
			$success = $db->update(
				'points_comp_report_user',
				[
					'comp_failed'		=> 0,
					'comp_performed'	=> 1
				],
				[
					'report_id'	=> $this->reportData['report_id'],
					'global_id'	=> $globalId
				],
				__METHOD__
			);
			$this->updateStats();
			return true;
		}
		return false;
	}

	/**
	 * Run through all users and send emails.
	 *
	 * @return void
	 */
	public function sendAllEmails() {
		foreach ($this->reportUser as $globalId => $data) {
			$this->sendUserEmail($globalId);
		}
	}

	/**
	 * Send user comp email.
	 *
	 * @param integer	Global ID
	 *
	 * @return boolean	Success
	 */
	public function sendUserEmail($globalId) {
		$success = false;

		$lookup = \CentralIdLookup::factory();
		$user = $lookup->localUserFromCentralId($globalId);
		if (!$user) {
			$success = false;
			return false;
		}

		$body = [
			'text' => wfMessage('automatic_comp_email_body_text', $user->getName())->text(),
			'html' => wfMessage('automatic_comp_email_body', $user->getName())->text()
		];
		$status = $user->sendMail(wfMessage('automatic_comp_email_subject')->parse(), $body);
		if ($status->isGood()) {
			$success = true;
		}

		if ($success) {
			$db = wfGetDB(DB_MASTER);
			$success = $db->update(
				'points_comp_report_user',
				['email_sent' => 1],
				[
					'report_id'	=> $this->reportData['report_id'],
					'global_id'	=> $globalId
				],
				__METHOD__
			);
			$this->updateStats();
		}

		return $success;
	}

	/**
	 * Get the number of active subscriptions.
	 *
	 * @return integer	Number of active subscriptions.
	 */
	public static function getNumberOfActiveSubscriptions() {
		$db = wfGetDB(DB_MASTER);
		$result = $db->select(
			['points_comp_report_user'],
			['global_id'],
			[
				'comp_performed' => 1,
				"current_comp_expires > " . time() . " OR new_comp_expires > " . time()
			],
			__METHOD__,
			[
				'GROUP BY'	=> 'global_id',
				'SQL_CALC_FOUND_ROWS'
			]
		);

		$calcRowsResult = $db->query('SELECT FOUND_ROWS() AS rowcount;');
		$total = $db->fetchRow($calcRowsResult);
		return intval($total['rowcount']);
	}
}
