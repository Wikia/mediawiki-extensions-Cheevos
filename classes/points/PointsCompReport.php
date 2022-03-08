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

use Cheevos\Cheevos;
use Cheevos\CheevosStatMonthlyCount;
use DateInterval;
use DateTime;
use ExtensionRegistry;
use Hydra\SubscriptionProvider;
use Hydra\Subscription;
use MediaWiki\MediaWikiServices;
use MWException;
use Status;
use User;

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
	 * [$userId => {database row}]
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
		$db = wfGetDB(DB_PRIMARY);

		$result = $db->select(
			['points_comp_report'],
			['*'],
			['report_id' => $this->reportData['report_id']],
			__METHOD__
		);
		$report = $result->fetchObject();
		if (empty($report)) {
			return false;
		}
		$this->reportData = (array)$report;

		$result = $db->select(
			['points_comp_report_user'],
			['*'],
			['report_id' => $this->reportData['report_id']],
			__METHOD__,
			[
				'ORDER BY'	=> 'user_id ASC'
			]
		);

		if (!empty($this->reportUser)) {
			$this->reportUser = [];
		}
		while ($row = $result->fetchObject()) {
			if (empty($row) || $row->user_id == 0) {
				continue;
			}

			$this->reportUser[$row->user_id] = (array)$row;
		}

		return boolval($this->reportData['report_id']);
	}

	/**
	 * Save to database.
	 *
	 * @return boolean	Success
	 */
	public function save() {
		$db = wfGetDB(DB_PRIMARY);

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

		foreach ($this->reportUser as $userId => $data) {
			$data['report_id'] = $this->reportData['report_id'];
			$data['start_time'] = $this->reportData['start_time'];
			$data['end_time'] = $this->reportData['end_time'];
			$db->upsert(
				'points_comp_report_user',
				$data,
				['report_id_user_id'],
				[
					'comp_new'			=> $data['comp_new'],
					'comp_extended'		=> $data['comp_extended'],
					'comp_failed'		=> $data['comp_failed'],
					'comp_skipped'		=> isset($data['comp_skipped']) ? $data['comp_skipped'] : 0,
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
		$db = wfGetDB(DB_PRIMARY);

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
		$db = wfGetDB(DB_PRIMARY);

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
		while ($row = $result->fetchObject()) {
			$reports[$row->report_id] = self::newFromRow((array)$row);
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
	 * @param integer Minimum Point Threshold
	 * @param integer|null [Optional] Maximum Point Threshold
	 *
	 * @return Status
	 */
	public static function validatePointThresholds(int $minPointThreshold, ?int $maxPointThreshold = null) {
		$minPointThreshold = $minPointThreshold;

		if ($maxPointThreshold !== null) {
			$maxPointThreshold = $maxPointThreshold;
			if ($maxPointThreshold <= 0 || $maxPointThreshold < $minPointThreshold) {
				return Status::newFatal('invalid_maximum_threshold');
			}
		}

		if ($minPointThreshold < 0) {
			return Status::newFatal('invalid_minimum_threshold');
		}

		return Status::newGood();
	}

	/**
	 * Get the time period start timestamp.
	 *
	 * @return integer Unix timestamp for the time period start.
	 */
	public function getStartTime(): int {
		return intval($this->reportData['start_time']);
	}

	/**
	 * Set the time period start timestamp.
	 *
	 * @param integer $startTime Unix timestamp for the time period start.
	 *
	 * @return void
	 */
	public function setStartTime(int $startTime) {
		$this->reportData['start_time'] = $startTime;
	}

	/**
	 * Get the time period end timestamp.
	 *
	 * @return integer Unix timestamp for the time period end.
	 */
	public function getEndTime(): int {
		return intval($this->reportData['end_time']);
	}

	/**
	 * Set the time period end timestamp.
	 *
	 * @param integer $endTime Unix timestamp for the time period end.
	 *
	 * @return void
	 */
	public function setEndTime(int $endTime) {
		$this->reportData['end_time'] = $endTime;
	}

	/**
	 * Validate time range.
	 *
	 * @param integer $startTime Start Timestamp
	 * @param integer $endTime   End Timestamp
	 *
	 * @return Status
	 */
	public static function validateTimeRange(int $startTime, int $endTime) {
		$startTime = $startTime;
		$endTime = $endTime;

		if ($endTime <= 0 || $endTime < $startTime) {
			return Status::newFatal('invalid_end_time');
		}

		if ($startTime < 0) {
			// Yes, nothing before 1970 exists.
			return Status::newFatal('invalid_start_time');
		}

		if ($startTime == $endTime) {
			return Status::newFatal('invalid_start_end_time_equal');
		}

		return Status::newGood();
	}

	/**
	 * Return the total new comps.
	 *
	 * @return integer Total new comps.
	 */
	public function getTotalNew(): int {
		return intval($this->reportData['comp_new']);
	}

	/**
	 * Return the total extended comps.
	 *
	 * @return integer Total extended comps.
	 */
	public function getTotalExtended(): int {
		return intval($this->reportData['comp_extended']);
	}

	/**
	 * Return the total failed comps.
	 *
	 * @return integer Total failed comps.
	 */
	public function getTotalFailed(): int {
		return intval($this->reportData['comp_failed']);
	}

	/**
	 * Return the total skipped comps.
	 *
	 * @return integer Total skipped comps.
	 */
	public function getTotalSkipped(): int {
		return intval($this->reportData['comp_skipped']);
	}

	/**
	 * Return the total comps actually performed.
	 *
	 * @return integer Total comps actually performed.
	 */
	public function getTotalPerformed(): int {
		return intval($this->reportData['comp_performed']);
	}

	/**
	 * Return the total users emailed.
	 *
	 * @return integer Total users emailed.
	 */
	public function getTotalEmailed(): int {
		return intval($this->reportData['email_sent']);
	}

	/**
	 * Is this report finished running?
	 *
	 * @return boolean Report Finished
	 */
	public function isFinished(): int {
		return boolval($this->reportData['finished']);
	}

	/**
	 * Set if the report is finished running.
	 *
	 * @param boolean Report Finished
	 *
	 * @return void
	 */
	public function setFinished(bool $finished = false) {
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
	public function addRow(int $userId, int $points, bool $compNew, bool $compExtended, bool $compFailed, int $currentCompExpires, int $newCompExpires, bool $compPerformed = false, bool $emailSent = false) {
		$data = [
			'user_id'				=> $userId,
			'points'				=> $points,
			'comp_new'				=> $compNew,
			'comp_extended'			=> $compExtended,
			'comp_failed'			=> $compFailed,
			'current_comp_expires'	=> $currentCompExpires,
			'new_comp_expires'		=> $newCompExpires,
			'comp_performed'		=> $compPerformed,
			'email_sent'			=> $emailSent
		];

		if (empty($data['user_id'])) {
			throw new MWException(__METHOD__ . ': Invalid global user ID provided.');
		}

		if (isset($this->reportUser[$userId])) {
			$this->reportUser[$userId] = array_merge($this->reportUser[$userId], $data);
		} else {
			$this->reportUser[$userId] = $data;
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
	 * @param integer|null $minPointThreshold [Optional] Minimum Point Threshold
	 * @param integer|null $maxPointThreshold [Optional] Maximum Point Threshold
	 * @param integer      $timeStart         [Optional] Unix timestamp of the start time.
	 * @param integer      $timeEnd           [Optional] Unix timestamp of the end time.
	 * @param boolean      $final             [Optional] Actually run comps.
	 * @param boolean      $email             [Optional] Send email to affected users.
	 *
	 * @return void
	 */
	public function run(?int $minPointThreshold = null, ?int $maxPointThreshold = null, int $timeStart = 0, int $timeEnd = 0, bool $final = false, bool $email = false) {
		if (!ExtensionRegistry::getInstance()->isLoaded('Subscription')) {
			throw new MWException(__METHOD__ . ": Extension:Subscription must be loaded for this functionality.");
		}

		if ($this->reportData['report_id'] > 0) {
			$minPointThreshold = $this->getMinPointThreshold();
			$maxPointThreshold = $this->getMaxPointThreshold();
			$timeStart = $this->getStartTime();
			$timeEnd = $this->getEndTime();
		}

		$config = MediaWikiServices::getInstance()->getMainConfig();

		if ($minPointThreshold !== null) {
			$minPointThreshold = $minPointThreshold;
		} else {
			$minPointThreshold = intval($config->get('CompedSubscriptionThreshold'));
		}

		if ($maxPointThreshold !== null) {
			$maxPointThreshold = $maxPointThreshold;
		}
		$status = self::validatePointThresholds($minPointThreshold, $maxPointThreshold);
		if (!$status->isGood()) {
			throw new MWException(__METHOD__ . ': ' . $status->getMessage());
		}

		if ($timeEnd <= $timeStart || $timeStart == 0 || $timeEnd == 0) {
			throw new MWException(__METHOD__ . ': The time range is invalid.');
		}

		$this->setMinPointThreshold($minPointThreshold);
		$this->setMaxPointThreshold($maxPointThreshold);
		$this->setStartTime($timeStart);
		$this->setEndTime($timeEnd);

		// Number of complimentary months someone is given.
		$compedSubscriptionMonths = intval($config->get('CompedSubscriptionMonths'));
		$newExpiresDT = new DateTime('now');
		$newExpiresDT->add(new DateInterval('P' . $compedSubscriptionMonths . 'M'));
		$newExpires = $newExpiresDT->getTimestamp();

		$gamepediaPro = SubscriptionProvider::factory('GamepediaPro');
		Subscription::skipCache(true);

		$limit = 200;
		$filters = [
			'stat'		=> 'wiki_points',
			'limit'		=> $limit,
			'offset'	=> 0,
			'global'	=> true,
			'month'		=> $timeStart,
		];

		while (true) {
			$statMonthly = Cheevos::getStatMonthlyCount($filters);
			$finished = false;

			foreach ($statMonthly as $monthly) {
				$this->updateUser($gamepediaPro, $newExpires, $final, $email, $monthly);

				if ($monthly->getCount() < $this->getMinPointThreshold()) {
					$finished = true;
					break;
				}
			}

			if ($finished || count($statMonthly) < $limit) {
				break;
			}
			$filters['offset'] += $limit;
		}
		$this->setFinished(true);
		$this->save();
	}


	/**
	 * Handle an individual user's stat count.
	 */
	private function updateUser(SubscriptionProvider $gamepediaPro, int $newExpires, bool $final, bool $email, CheevosStatMonthlyCount $monthly) {
		$isExtended = false;

		if ($monthly->getCount() < $this->getMinPointThreshold()) {
			return;
		}

		$maxPointThreshold = $this->getMaxPointThreshold();
		if ($maxPointThreshold !== null && $monthly->getCount() > $maxPointThreshold) {
			return;
		}

		$user = Cheevos::getUserForServiceUserId($monthly->getUser_Id());
		if (!$user || $user->getId() < 1) {
			return;
		}

		$success = false;

		$subscription = $this->getSubscription($user, $gamepediaPro);
		if ($subscription['paid']) {
			// Do not mess with paid subscriptions.
			$this->addRow($user->getId(), $monthly->getCount(), false, false, false, $subscription['expires'], 0, false, false);
			return;
		} elseif ($subscription['hasSubscription'] && $newExpires > $subscription['expires']) {
			$isExtended = true;
		}

		$emailSent = false;
		if ($final) {
			if ($isExtended) {
				$gamepediaPro->cancelCompedSubscription($user->getId());
			}
			$config = MediaWikiServices::getInstance()->getMainConfig();
			$comp = $gamepediaPro->createCompedSubscription($user->getId(), intval($config->get('CompedSubscriptionMonths')));

			if ($comp !== false) {
				$success = true;
				if ($email) {
					$emailSent = $this->sendUserEmail($user);
				}
			}
		}

		$this->addRow($user->getId(), $monthly->getCount(), !$isExtended, $isExtended, !$success, intval($subscription['expires']), $newExpires, $success, $emailSent);
	}

	/**
	 * Get current subscription status.
	 *
	 * @param User                 $user     User
	 * @param SubscriptionProvider $provider Subscription Provider
	 *
	 * @return array Array of boolean status flags.
	 */
	public function getSubscription(User $user, SubscriptionProvider $provider): array {
		$hasSubscription = false;
		$paid = false;
		$expires = null;
		$subscription = $provider->getSubscription($user->getId());
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
		$config = MediaWikiServices::getInstance()->getMainConfig();
		$compedSubscriptionMonths = intval($config->get('CompedSubscriptionMonths'));
		$userFactory = MediaWikiServices::getInstance()->getUserFactory();
		foreach ($this->reportUser as $userId => $data) {
			$this->compSubscription($userFactory->newFromId($userId), $compedSubscriptionMonths);
		}
	}

	/**
	 * Create a subscription compensation in the billing service.
	 * Will fail if a valid paid or comped subscription already exists and is longer than the proposed new comp length.
	 *
	 * @param User    $user
	 * @param integer $numberOfMonths Number of months into the future to compensate.
	 *
	 * @return boolean Success
	 */
	public function compSubscription(User $user, int $numberOfMonths): bool {
		$gamepediaPro = SubscriptionProvider::factory('GamepediaPro');

		$newExpiresDT = new DateTime('now');
		$newExpiresDT->add(new DateInterval('P' . $numberOfMonths . 'M'));
		$newExpires = $newExpiresDT->getTimestamp();

		$subscription = $this->getSubscription($user, $gamepediaPro);
		if ($subscription['paid'] === true) {
			// Do not mess with paid subscriptions.
			return false;
		} elseif ($subscription['hasSubscription'] && $newExpires > $subscription['expires']) {
			$gamepediaPro->cancelCompedSubscription($user->getId());
		}

		$comp = $gamepediaPro->createCompedSubscription($user->getId(), $numberOfMonths);

		if ($comp !== false) {
			$db = wfGetDB(DB_PRIMARY);
			$success = $db->update(
				'points_comp_report_user',
				[
					'comp_failed'		=> 0,
					'comp_performed'	=> 1
				],
				[
					'report_id'	=> $this->reportData['report_id'],
					'user_id'	=> $user->getId()
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
		$userFactory = MediaWikiServices::getInstance()->getUserFactory();
		foreach ($this->reportUser as $userId => $data) {
			$this->sendUserEmail($userFactory->newFromId($userId));
		}
	}

	/**
	 * Send user comp email.
	 *
	 * @param User $user
	 *
	 * @return boolean Success
	 */
	public function sendUserEmail(User $user): bool {
		$success = false;

		$body = [
			'text' => wfMessage('automatic_comp_email_body_text', $user->getName())->text(),
			'html' => wfMessage('automatic_comp_email_body', $user->getName())->text()
		];
		$status = $user->sendMail(wfMessage('automatic_comp_email_subject')->parse(), $body);
		if ($status->isGood()) {
			$success = true;
		}

		if ($success) {
			$db = wfGetDB(DB_PRIMARY);
			$success = $db->update(
				'points_comp_report_user',
				['email_sent' => 1],
				[
					'report_id'	=> $this->reportData['report_id'],
					'user_id'	=> $user->getId()
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
	public static function getNumberOfActiveSubscriptions(): int {
		$db = wfGetDB(DB_PRIMARY);
		return $db->selectRowCount(
			['points_comp_report_user'],
			['user_id'],
			[
				'comp_performed' => 1,
				"current_comp_expires > " . time() . " OR new_comp_expires > " . time()
			],
			__METHOD__,
			[
				'GROUP BY'	=> 'user_id'
			]
		);
	}
}
