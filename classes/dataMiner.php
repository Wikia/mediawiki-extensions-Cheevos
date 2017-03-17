<?php
/**
 * Curse Inc.
 * Data Miner
 * Data Miner Class
 *
 * @author		Alexia E. Smith, Cameron Chunn
 * @copyright	(c) 2017 Curse Inc.
 * @license		All Rights Reserved
 * @package		Data Miner
 * @link		http://www.curse.com/
 *
**/

namespace Cheevos;

class dataMiner {
	/**
	 * Various statistic types tracked.
	 *
	 * @var		array
	 */
	public static $statTypes = ['actions', 'edits', 'deletes', 'patrols', 'blocks'];

	/**
	 * Cut Off Timestamps(Filled by init() function.)
	 *
	 * @var		array
	 */
	public static $cutoffs = [];

	/**
	 * Threshold Values
	 *
	 * @var		array
	 */
	static private $thresholds;

	/**
	 * Class Initialized
	 *
	 * @var		boolean
	 */
	static private $initialized = false;

	/**
	 * Main Initializer
	 *
	 * @access	public
	 * @return	boolean	Successfully initialized.
	 */
	static public function init() {
		if (!self::$initialized) {
			self::$cutoffs = [
				'30'	=> strtotime('-30 days'),
				'60'	=> strtotime('-60 days'),
				'90'	=> strtotime('-90 days')
			];
			self::$initialized = true;
		}
		return self::$initialized;
	}

	/**
	 * Generates and returns an array of information on the provided Global Ids.
	 *
	 * @access	public
	 * @param	array	Array of Global Ids to look up.
	 * @param	boolean	[Optional] Include periodical statistics.  Defaults to true.
	 * @param	array	[Optional] Array of query options to use.  Valid keys are 'start', 'limit', and 'order'.
	 * @return	mixed	Array user global statistic information with keys being the Global Ids.  Returns false on an error.
	 */
	static public function getUserGlobalStats($globalIds, $includePeriodical = true, $queryOptions = []) {
		if (!is_array($globalIds)) {
			return false;
		}

		if (!self::init()) {
			return false;
		}

		//Process Global Ids for validation.
		foreach ($globalIds as $key => $globalId) {
			if (intval($globalId) > 0) {

				$data = Cheevos::stats([
					'global' => true,
					'user_id' => $globalId
				]);
				echo "<pre>";
				var_dump($globalId);
				var_dump($data);
				die();

			}
		}



		//Assemble order and limit.
		$extra = [];
		if (isset($queryOptions['order'])) {
			$extra['ORDER BY']	= 'global_'.$queryOptions['order'];
		}
		if (isset($queryOptions['limit'])) {
			$extra['OFFSET']	= intval($queryOptions['start']);
			$extra['LIMIT']		= intval($queryOptions['limit']);
		}

		$results = self::$DB->select(
			['dataminer_user_global_totals'],
			['*'],
			$where,
			__METHOD__,
			$extra
		);

		$users = [];
		$foundGlobalIds = [];
		while ($row = $results->fetchRow()) {
			$foundGlobalIds[] = $row['global_id'];
			foreach (self::$statTypes as $type) {
				$users[$row['global_id']]['global']['total'][$type] = intval($row['global_'.$type]);
			}
		}
		$foundGlobalIds = array_unique($foundGlobalIds);

		if ($includePeriodical === true && count($foundGlobalIds)) {
			$results = self::$DB->select(
				['dataminer_user_wiki_periodicals'],
				['*'],
				['global_id' => $foundGlobalIds],
				__METHOD__
			);

			while ($row = $results->fetchRow()) {
				if (array_key_exists($row['global_id'], $users)) {
					foreach (self::$statTypes as $type) {
						$datestamp = strtotime($row['date']);
						foreach (self::$cutoffs as $days => $cutoffTimestamp) {
							if ($datestamp >= $cutoffTimestamp) {
								$users[$row['global_id']]['global'][$days][$type] += $row[$type];
							}
						}
					}
				}
			}
		}

		$totalResults = self::$DB->select(
			['dataminer_user_wiki_totals'],
			['count(*) as total', 'global_id'],
			$where,
			__METHOD__,
			['GROUP BY' => 'global_id']
		);

		while ($row = $totalResults->fetchRow()) {
			if (array_key_exists($row['global_id'], $users)) {
				$users[$row['global_id']]['other']['wikis_contributed'] = $row['total'];
			}
		}

		return $users;
	}

	/**
	 * Generates and returns an array of statistic information for individual sites based on the provided Global Ids.
	 *
	 * @access	public
	 * @param	array	Array of Global Ids to look up.
	 * @param	array	[Optional] Array of site keys to limit by.
	 * @param	boolean	[Optional] Include periodical statistics.  Defaults to true.
	 * @param	array	[Optional] Array of query options to use.  Valid keys are 'start', 'limit', and 'order'.
	 * @return	mixed	Array user wiki statistic information with keys being the Global Ids.  Returns false on an error.
	 */
	static public function getUserSiteStats($globalIds = [], $siteKeys = [], $includePeriodical = true, $queryOptions = []) {
		if (!is_array($globalIds) || !is_array($siteKeys)) {
			return false;
		}

		if (!self::init()) {
			return false;
		}

		//Assemble Global Ids for where clause.
		foreach ($globalIds as $key => $globalId) {
			if (intval($globalId) < 1) {
				unset($globalIds[$key]);
			} else {
				$globalIds[$key] = intval($globalId);
			}
		}
		$where = [];
		if (count($globalIds)) {
			$where['global_id'] = $globalIds;
		}

		if (count($siteKeys)) {
			$where['site_key'] = $siteKeys;
		}

		//Assemble order and limit.
		$extra = [];
		if ($queryOptions['order']) {
			$extra['ORDER BY']	= 'wiki_'.$queryOptions['order'];
		}
		if ($queryOptions['limit']) {
			$extra['OFFSET']	= intval($queryOptions['start']);
			$extra['LIMIT']		= intval($queryOptions['limit']);
		}

		$results = self::$DB->select(
			['dataminer_user_wiki_totals'],
			['*'],
			$where,
			__METHOD__,
			$extra
		);

		$users = [];
		while ($row = $results->fetchRow()) {
			foreach (self::$statTypes as $type) {
				$users[$row['global_id']]['wikis'][$row['site_key']]['total'][$type] = intval($row['wiki_'.$type]);
			}
		}

		if ($includePeriodical === true) {
			$results = self::$DB->select(
				['dataminer_user_wiki_periodicals'],
				['*'],
				$where,
				__METHOD__
			);

			while ($row = $results->fetchRow()) {
				if (array_key_exists($row['global_id'], $users)) {
					foreach (self::$statTypes as $type) {
						$datestamp = strtotime($row['date']);
						foreach (self::$cutoffs as $days => $cutoffTimestamp) {
							if ($datestamp >= $cutoffTimestamp) {
								$users[$row['global_id']]['wikis'][$row['site_key']][$days][$type] += $row[$type];
							}
						}
					}
				}
			}
		}

		return $users;
	}
}