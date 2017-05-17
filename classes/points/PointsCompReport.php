<?php
/**
 * Curse Inc.
 * Cheevos
 * Points Comp Report
 *
 * @author		Alexia E. Smith
 * @copyright	(c) 2017 Curse Inc.
 * @license		All Rights Reserved
 * @package		Cheevos
 * @link		http://www.curse.com/
 *
**/

namespace Cheevos\Points;

/**
 * Class containing some business and display logic for points blocks
 */
class PointsCompReport {
	/**
	 * Report ID
	 *
	 * @var		integer
	 */
	private $reportId;

	/**
	 * Point Threshold
	 *
	 * @var		integer
	 */
	private $pointThreshold = 0;

	/**
	 * Month Start
	 *
	 * @var		integer
	 */
	private $monthStart = 0;

	/**
	 * Month End
	 *
	 * @var		integer
	 */
	private $monthEnd = 0;

	/**
	 * Total new comps.
	 *
	 * @var		integer
	 */
	private $totalNew = 0;

	/**
	 * Total extended comps.
	 *
	 * @var		integer
	 */
	private $totalExtended = 0;

	/**
	 * Total failed comps.
	 *
	 * @var		integer
	 */
	private $totalFailed = 0;

	/**
	 * Total comps actually performed.
	 *
	 * @var		integer
	 */
	private $totalPerformed = 0;

	/**
	 * Total users emailed to inform them.
	 *
	 * @var		integer
	 */
	private $totalEmailed = 0;

	/**
	 * Report Data
	 * [$globalId => {database row}]
	 *
	 * @var		array
	 */
	private $reportData = [];

	/**
	 * Main Constructor
	 *
	 * @access	public
	 * @param	array	Report ID
	 * @return	void
	 */
	public function __construct($reportId = 0) {
		$this->reportId = intval($reportId);
	}

	/**
	 * Load a new report object from the report ID.
	 *
	 * @access	public
	 * @param	integer	Report ID
	 * @return	mixed	PointsCompReport object or null if it does not exist.
	 */
	public static function newFromId($id) {
		$report = new self($id);

		$success = $report->load();

		return ($success ? $report : null);
	}

	/**
	 * Load a new report object from a database row.
	 *
	 * @access	public
	 * @param	array	Report Row from the Database
	 * @return	object	PointsCompReport
	 */
	private static function newFromRow($row) {
		$report = new self($row['id']);

		$report->pointThreshold = $row['points'];
		$report->monthStart = $row['month_start'];
		$report->monthEnd = $row['month_end'];
		$report->totalNew = $row['comp_new'];
		$report->totalExtended = $row['comp_extended'];
		$report->totalFailed = $row['comp_failed'];
		$report->totalPerformed = $row['comp_performed'];
		$report->totalEmailed = $row['email_sent'];

		return $report;
	}

	/**
	 * Load information from the database.
	 *
	 * @access	private
	 * @return	boolean	Sucess
	 */
	private function load() {
		$db = wfGetDB(DB_MASTER);

		$result = $db->select(
			['points_comp_report'],
			['*'],
			['report_id' => $this->reportId],
			__METHOD__,
			[
				'ORDER BY'	=> 'global_id ASC'
			]
		);

		if (!empty($this->reportData)) {
			$this->reportData = [];
		}
		while ($row = $result->fetchRow()) {
			if ($row['global_id'] == 0 && $row['id'] == $row['report_id']) {
				$this->reportId = $row['report_id'];
				$this->pointThreshold = $row['points'];
				$this->monthStart = $row['month_start'];
				$this->monthEnd = $row['month_end'];
				$this->totalNew = $row['comp_new'];
				$this->totalExtended = $row['comp_extended'];
				$this->totalFailed = $row['comp_failed'];
				$this->totalPerformed = $row['comp_performed'];
				$this->totalEmailed = $row['email_sent'];
				continue;
			} elseif ($row['global_id'] == 0) {
				continue;
			}

			$this->reportData[$row['global_id']] = $row;
		}

		return boolval($this->reportId);
	}

	/**
	 * Save to database.
	 *
	 * @access	public
	 * @return	boolean	Success
	 */
	public function save() {
		$db = wfGetDB(DB_MASTER);

		if (!$this->reportId) {
			$success = $db->insert(
				'points_comp_report',
				[
					'points'			=> $this->pointThreshold,
					'month_start'		=> $this->monthStart,
					'month_end'			=> $this->monthEnd,
					'comp_new'			=> $this->totalNew,
					'comp_extended'		=> $this->totalExtended,
					'comp_failed'		=> $this->totalFailed,
					'comp_performed'	=> $this->totalPerformed,
					'email_sent'		=> $this->totalEmailed
				],
				__METHOD__
			);

			$reportId = $db->insertId();
			if ($success && $reportId > 0) {
				$db->update(
					'points_comp_report',
					['report_id' => $reportId],
					['id' => $reportId],
					__METHOD__
				);
				$this->reportId = $reportId;
			} else {
				throw new MWException(__METHOD__.': Could not get a new report ID.');
			}
		}

		foreach ($this->reportData as $globalId => $data) {
			$data['report_id'] = $this->reportId;
			$data['month_start'] = $this->monthStart;
			$data['month_end'] = $this->monthEnd;
			$db->insert(
				'points_comp_report',
				$data,
				__METHOD__
			);
		}
		return true;
	}

	/**
	 * Load a list of basic report information.
	 *
	 * @access	private
	 * @param	integer	Start Position
	 * @param	integer	Maximum Items to Return
	 * @return	array	Multidimensional array of ['total' => $total, $reportId => [{reportData}]]
	 */
	static public function getReportsList($start = 0, $itemsPerPage = 50) {
		$db = wfGetDB(DB_MASTER);

		$result = $db->select(
			['points_comp_report'],
			['*'],
			[
				'report_id = id',
				'global_id' => 0
			],
			__METHOD__,
			[
				'ORDER BY'	=> 'id DESC',
				'OFFSET'	=> $start,
				'LIMIT'		=> $itemsPerPage
			]
		);

		$reports = [];
		while ($row = $result->fetchRow()) {
			$reports[$row['id']] = self::newFromRow($row);
		}

		$result = $db->select(
			['points_comp_report'],
			['count(*) as total'],
			[
				'report_id = id',
				'global_id' => 0
			],
			__METHOD__,
			[
				'ORDER BY'	=> 'id DESC'
			]
		);
		$total = $result->fetchRow();
		$total = intval($total['total']);

		return ['total' => $total, 'reports' => $reports];
	}

	/**
	 * Get the report ID.
	 *
	 * @access	public
	 * @return	integer	This report ID.
	 */
	public function getReportId() {
		return $this->reportId;
	}

	/**
	 * Get the point threshold for this report.
	 *
	 * @access	public
	 * @return	integer	Unix timestamp for month beginning.
	 */
	public function getPointThreshold() {
		return $this->pointThreshold;
	}

	/**
	 * Set the point threshold for this report.
	 *
	 * @access	public
	 * @param	integer	Point threshold for this report.
	 * @return	void
	 */
	public function setPointThreshold($pointThreshold) {
		$this->pointThreshold = intval($pointThreshold);
	}

	/**
	 * Get the month start timestamp.
	 *
	 * @access	public
	 * @return	integer	Unix timestamp for month beginning.
	 */
	public function getMonthStart() {
		return $this->monthStart;
	}

	/**
	 * Set the month start timestamp.
	 *
	 * @access	public
	 * @param	integer	Unix timestamp for month beginning.
	 * @return	void
	 */
	public function setMonthStart($monthStart) {
		$this->monthStart = intval($monthStart);
	}

	/**
	 * Get the month end timestamp.
	 *
	 * @access	public
	 * @return	integer	Unix timestamp for month ending.
	 */
	public function getMonthEnd() {
		return $this->monthEnd;
	}

	/**
	 * Set the month end timestamp.
	 *
	 * @access	public
	 * @param	integer	Unix timestamp for month ending.
	 * @return	void
	 */
	public function setMonthEnd($monthEnd) {
		$this->monthEnd = intval($monthEnd);
	}

	/**
	 * Return the total new comps.
	 *
	 * @access	public
	 * @return	integer	Total new comps.
	 */
	public function getTotalNew() {
		return intval($this->totalNew);
	}

	/**
	 * Return the total extended comps.
	 *
	 * @access	public
	 * @return	integer	Total extended comps.
	 */
	public function getTotalExtended() {
		return intval($this->totalExtended);
	}

	/**
	 * Return the total failed comps.
	 *
	 * @access	public
	 * @return	integer	Total failed comps.
	 */
	public function getTotalFailed() {
		return intval($this->totalFailed);
	}

	/**
	 * Return the total comps actually performed.
	 *
	 * @access	public
	 * @return	integer	Total comps actually performed.
	 */
	public function getTotalPerformed() {
		return intval($this->totalPerformed);
	}

	/**
	 * Return the total users emailed.
	 *
	 * @access	public
	 * @return	integer	Total users emailed.
	 */
	public function getTotalEmailed() {
		return intval($this->totalEmailed);
	}

	/**
	 * Add new report row.
	 * Will overwrite existing rows with the same global ID.
	 *
	 * @access	public
	 * @param	integer	Global User ID
	 * @param	integer	Aggegrate Points for the month range.
	 * @param	boolean	Is this a new comp for this month range?(User did not have previously or consecutively.)
	 * @param	boolean	Is this an extended comp from a previous one?
	 * @param	boolean	Did the billing system fail to do the comp?(Or did we just not run it yet?)
	 * @param	integer	Unix timestamp for when the comp expires.
	 * @return	void
	 */
	public function addRow($globalId, $points, $compNew, $compExtended, $compFailed, $compExpires, $compPerformed = false, $emailSent = false) {
		$data = [
			'global_id'			=> intval($globalId),
			'points'			=> intval($points),
			'comp_new'			=> boolval($compNew),
			'comp_extended'		=> boolval($compExtended),
			'comp_failed'		=> boolval($compFailed),
			'comp_expires'		=> intval($compExpires),
			'comp_performed'	=> boolval($compPerformed),
			'email_sent'		=> boolval($emailSent)
		];

		if (empty($data['global_id'])) {
			throw new MWException(__METHOD__.': Invalid global user ID provided.');
		}

		$this->totalNew += $data['comp_new'];
		$this->totalExtended += $data['comp_extended'];
		$this->totalFailed += $data['comp_failed'];
		$this->totalPerformed += $data['comp_performed'];
		$this->totalEmailed += $data['email_sent'];

		$this->reportData[$globalId] = $data;
	}

	/**
	 * Get the next row in the report data.
	 *
	 * @access	public
	 * @return	mixed	Report row data or false for no more values.
	 */
	public function getNextRow() {
		$return = current($this->reportData);
		next($this->reportData);
		return $return;
	}

	/**
	 * Run the report.
	 *
	 * @access	public
	 * @param	integer	Point Threshold
	 * @param	integer	Unix timestamp of the start time.
	 * @param	integer	Unix timestamp of the end time.
	 * @param	integer	Actually run comps.
	 * @return	void
	 */
	public function run($threshold = null, $timeStart = 0, $timeEnd = 0, $final = false) {
		if (!\ExtensionRegistry::getInstance()->isLoaded('Subscription')) {
			throw new \MWException(__METHOD__.": Extension:Subscription must be loaded for this functionality.");
		}

		$db = wfGetDB(DB_MASTER);

		$config = \ConfigFactory::getDefaultInstance()->makeConfig('main');

		$compedSubscriptionThreshold = intval($config->get('CompedSubscriptionThreshold'));
		if ($threshold !== null) {
			$threshold = intval($threshold);
			if ($threshold > 0) {
				$compedSubscriptionThreshold = $threshold;
			} else {
				throw new \MWException(__METHOD__.': Invalid threshold provided.');
			}
		}

		//Number of complimentary months someone is given.
		$compedSubscriptionMonths = intval($config->get('CompedSubscriptionMonths'));

		$timeStart = intval($timeStart);
		$timeEnd = intval($timeEnd);
		if ($timeEnd <= $timeStart || $timeStart == 0 || $timeEnd == 0) {
			throw new \MWException(__METHOD__.': The time range is invalid.');
		}

		//$epochFirst = strtotime(date('Y-m-d', strtotime('first day of 0 month ago')).'T00:00:00+00:00');
		//$epochLast = strtotime(date('Y-m-d', strtotime('last day of +2 months')).'T23:59:59+00:00');
		$newExpires = strtotime(date('Y-m-d', strtotime('last day of +'.($compedSubscriptionMonths - 1).' months')).'T23:59:59+00:00'); //Get the last day of two months from now, fix the hours:minutes:seconds, and then get the corrected epoch.

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

		$this->setPointThreshold($compedSubscriptionThreshold);
		$this->setMonthStart($filters['start_time']);
		$this->setMonthEnd($filters['end_time']);

		foreach ($statProgress as $progress) {
			$isNew = false;
			$isExtended = false;
			$isFailed = false;

			if ($progress->getCount() < $compedSubscriptionThreshold) {
				continue;
			}

			$globalId = $progress->getUser_Id();
			if ($globalId < 1) {
				continue;
			}

			$success = false;

			$subscription = $gamepediaPro->getSubscription($globalId);
			$expires = false;
			if ($subscription !== false && is_array($subscription)) {
				if ($subscription['plan_id'] !== 'complimentary') {
					continue;
				}
				$expires = ($subscription['expires'] !== false ? $subscription['expires']->getTimestamp(TS_UNIX) : null);

				if ($newExpires > $expires) {
					$isExtended = true;
					$expires = $newExpires;
					if ($final) {
						$gamepediaPro->cancelCompedSubscription($globalId);
					}
				} else {
					continue;
				}
			}

			if (!$isExtended) {
				$isNew = true;
			}

			if ($final) {
				$comp = $gamepediaPro->createCompedSubscription($globalId, $compedSubscriptionMonths);

				if ($comp !== false) {
					$success = true;
					/*$body = [
						'text' => wfMessage('automatic_comp_email_body_text', $user->getName())->text(),
						'html' => wfMessage('automatic_comp_email_body', $user->getName())->text()
					];
					$user->sendMail(wfMessage('automatic_comp_email_subject')->parse(), $body);*/
				}
			}

			if (!$success) {
				$isFailed = true;
			}
			$this->addRow($globalId, $progress->getCount(), $isNew, $isExtended, $isFailed, $expires);
		}
		$this->save();
	}
}
