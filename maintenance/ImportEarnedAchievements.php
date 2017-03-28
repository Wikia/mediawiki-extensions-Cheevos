<?php
/**
 * Curse Inc.
 * Cheevos
 * Imports earned achievements.
 *
 * @author		Alexia E. Smith
 * @copyright	(c) 2017 Curse Inc.
 * @license		All Rights Reserved
 * @package		Cheevos
 * @link		https://www.gamepedia.com/
 *
**/
require_once(__DIR__.'/../../../maintenance/Maintenance.php');

class ImportEarnedAchievements extends Maintenance {
	/**
	 * Constructor
	 *
	 * @access	public
	 * @return	void
	 */
	public function __construct() {
		parent::__construct();
		$this->addDescription('Imports earned achievements.  Should only ever be run once.');
		$this->addOption('restart', 'Run again from the beginning even if run before.');
	}

	/**
	 * Main execution of the maintenance script
	 *
	 * @access	public
	 * @return	void
	 */
	public function execute() {
		global $dsSiteKey;

		$cache = wfGetCache(CACHE_MEMCACHED);
		if ($this->getOption('restart')) {
			$cache->set(__CLASS__, 0);
		}

		$db = wfGetDB(DB_MASTER);

		$this->output("Importing earned achievements...\n");

		$achievementIdMap = json_decode($cache->get('ImportAchievementsMap'), true);
		if (empty($achievementIdMap) || !is_array($achievementIdMap)) {
			$this->output("No mapping of old achievement IDs to new achievement IDs were found.  This may because there are none or ImportCustomAchievements was not run first.  Do you wish to continue?  [Y/N]\n");
			$handle = fopen('php://stdin', 'r');
			$response = fgets($handle);
			if (strtolower(trim($response)) !== 'y') {
				exit;
			}
			$achievementIdMap = [];
		}

		$achievements = \Cheevos\Cheevos::getAchievements($dsSiteKey);
		if (empty($achievements)) {
			$this->output("ERROR: Achievements could not be pulled down from the service.\n");
			exit;
		}

		$where = [];

		$result = $db->select(
			['achievement_earned'],
			['count(*) AS total'],
			$where,
			__METHOD__,
			[
				'ORDER BY'	=> 'aeid ASC'
			]
		);
		$total = $result->fetchRow();
		$total = intval($total['total']) - $cache->get(__CLASS__);
		$this->output("Importing earned achievements...\n");

		$userStats = [];
		for ($i = $start; $i <= $total; $i = $i + 1000) {
			$this->output("Iteration start {$i}\n");

			$result = $db->select(
				['achievement_earned'],
				['*'],
				$where,
				__METHOD__,
				[
					'OFFSET'	=> $i,
					'LIMIT'		=> 1000,
					'ORDER BY'	=> 'aeid ASC'
				]
			);

			while ($row = $result->fetchRow()) {
				$aid = intval($row['achievement_id']);
				$globalId = intval($row['curse_id']);

				if (array_key_exists($aid, $achievementIdMap)) {
					$aid = $achievementIdMap[$aid];
				}

				if (!isset($achievements[$aid])) {
					//¯\_(ツ)_/¯
					$this->output("Missing achievement ID {$aid}\n");
					continue;
				}
				$achievement = $achievements[$aid];

				if ($row['date'] > 0) {
					//Only fake manually award if they earned it.
					$progress = new \Cheevos\CheevosAchievementProgress([
						'achievement_id'	=> $aid,
						'user_id'			=> $globalId,
						'site_key'			=> $dsSiteKey,
						'earned'			=> ($row['date'] > 0 ? true : false),
						'manual_award'		=> false,
						'awarded_at'		=> intval($row['date']),
						'notified'			=> true
					]);
					try {
						$status = \Cheevos\Cheevos::putProgress($progress->toArray());
						if (isset($status['code']) && $status['code'] != 200) {
							$this->output("Exiting, encountered API error at {$row['aeid']} due to: {$status['code']}\n");
						}
					} catch (\Cheevos\CheevosException $e) {
						$this->output("Exiting, encountered API error at {$row['aeid']} due to: {$e->getMessage()}\n");
					}
				}


				$stats = $achievement->getCriteria()->getStats();
				$value = $achievement->getCriteria()->getValue();

				if (count($stats) == 1 && $row['increment'] > 0) {
					$stat = current($stats);
					$newValue = intval($row['increment']);

					if (!array_key_exists($globalId, $userStats)) {
						try {
							$userStats[$globalId] = \Cheevos\Cheevos::getStatProgress(
								[
									'user_id' => $globalId
								]
							);
						} catch (\Cheevos\CheevosException $e) {
							$this->output("Exiting, encountered API error at {$row['aeid']} due to: {$e->getMessage()}\n");
						}
					}

					$currentValue = 0;
					$statIndex = false;
					if (!empty($userStats)) {
						foreach ($userStats as $index => $userStat) {
							if ($userStat['stat'] == $stat) {
								$statIndex = $index;
								$currentValue = $userStat['count'];
							}
						}
					}
					$delta = abs($newValue - $currentValue);
					if ($newValue > $currentValue) {
						$data = [
							'user_id' => intval($globalId),
							'site_key' => $dsSiteKey,
							'deltas' => [
								[
									'stat' => current($stats),
									'delta' => $delta
								]
							]
						];
						try {
							$status = \Cheevos\Cheevos::increment($data);
							if (isset($status['code']) && $status['code'] != 200) {
								$this->output("Exiting, encountered API error at {$row['aeid']} due to: {$status['code']}\n");
							}
							if ($statIndex !== false) {
								$userStats[$globalId][$statIndex]['count'] += $delta;
							}
							$this->output("Processed G: {$globalId} - SK: {$dsSiteKey} - AID: {$row['achievement_id']}\n");
						} catch (\Cheevos\CheevosException $e) {
							$this->output("Exiting, encountered API error at {$globalId} - SK: {$dsSiteKey} - AID: {$row['achievement_id']} due to: {$e->getMessage()}\n");
							exit;
						}
					}
				}

				$cache->set(__CLASS__, $i);
			}
		}
	}
}

$maintClass = 'ImportEarnedAchievements';
if (defined('RUN_MAINTENANCE_IF_MAIN')) {
	require_once( RUN_MAINTENANCE_IF_MAIN );
} else {
	require_once( DO_MAINTENANCE );
}
