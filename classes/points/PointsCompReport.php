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
	 * @return	object	PointsCompReport
	 */
	public static function newFromId($id) {
		$report = new self($id);

		$report->load();

		return $report;
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

		return $report;
	}

	/**
	 * Load information from the database.
	 *
	 * @access	private
	 * @return	void
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
				$this->pointThreshold = $row['points'];
				$this->monthStart = $row['month_start'];
				$this->monthEnd = $row['month_end'];
				continue;
			} elseif ($row['global_id'] == 0) {
				continue;
			}

			$this->reportData[$row['global_id']] = $row;
		}
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
					'points'		=> $this->pointThreshold,
					'month_start'	=> $this->monthStart,
					'month_end'		=> $this->monthEnd
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
	 * @return	array	Multidimensional array of [$reportId => [{reportData}]
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
				'ORDER BY'	=> 'id DESC'
			]
		);

		$reports = [];
		while ($row = $result->fetchRow()) {
			$reports[$row['id']] = self::newFromRow($row);
		}
		return $reports;
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
	 * Add new report row.
	 * Will overwrite existing rows with the same global ID.
	 *
	 * @access	public
	 * @param	integer	Global User ID
	 * @param	integer	Aggegrate Points for the month range.
	 * @param	boolean	Is this a new comp for this month range?(User did not have previously or consecutively.)
	 * @param	boolean	Is this an extended comp from a previous one?
	 * @param	integer	Unix timestamp for when the comp expires.
	 * @return	void
	 */
	public function addRow($globalId, $points, $new, $extended, $compExpires) {
		$data = [
			'global_id'		=> intval($globalId),
			'points'		=> intval($points),
			'new'			=> boolval($new),
			'extended'		=> boolval($extended),
			'comp_expires'	=> intval($compExpires)
		];

		if (empty($data['global_id'])) {
			throw new MWException(__METHOD__.': Invalid global user ID provided.');
		}

		$this->reportData[$globalId] = $data;
	}
}
