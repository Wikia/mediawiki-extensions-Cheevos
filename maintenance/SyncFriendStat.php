<?php
/**
 * Curse Inc.
 * Cheevos
 * Synchronizes friend count to the Cheevos service.
 *
 * @author		Alexia E. Smith
 * @copyright	(c) 2017 Curse Inc.
 * @license		All Rights Reserved
 * @package		Cheevos
 * @link		https://www.gamepedia.com/
 *
**/
require_once(__DIR__.'/../../../maintenance/Maintenance.php');

class SyncFriendStat extends Maintenance {
	/**
	 * Constructor
	 *
	 * @access	public
	 * @return	void
	 */
	public function __construct() {
		parent::__construct();
		$this->addDescription('Synchronizes friend count to the Cheevos service.');
		$this->addOption('v', 'Verbose - Show debug information.');
	}

	/**
	 * Main execution of the maintenance script
	 *
	 * @access	public
	 * @return	void
	 */
	public function execute() {
		global $dsSiteKey;

		if (!defined('MASTER_WIKI') || MASTER_WIKI !== true) {
			throw new MWException('This script is intended to be ran from the master wiki.');
		}

		$achievements = \Cheevos\Cheevos::getAchievements($dsSiteKey);

		$db = wfGetDB(DB_MASTER);

		$redis = \RedisCache::getClient('cache');
		if ($redis !== false) {
			throw new Exception("Redis is required to be working to use this maintenance script.");
		}

		try {
			$relationships = $redis->keys('friendlist:*');
		} catch (\Throwable $e) {
			wfDebug(__METHOD__.": Caught RedisException - ".$e->getMessage());
		}

		$total = count($relationships);
		$this->output("Updating friendship for {$total} users in Cheevos...\n");

		foreach ($relationships as $friends) {
			$globalId = intval(array_pop(explode(':', $friends)));

			if ($globalId < 1) {
				continue;
			}
			if ($this->getOption('v')) {
				$this->output("Global ID: {$globalId}\n");
			}

			$local['curse_profile_add_friend'] = $redis->sCard('friendlist:'.$globalId);

			try {
				$statProgress = \Cheevos\Cheevos::getStatProgress(
					[
						'user_id'	=> $globalId,
						'site_key'	=> $dsSiteKey,
						'limit'		=> 0,
						'stat'		=> 'curse_profile_add_friend'
					]
				);
			} catch (\Cheevos\CheevosException $e) {
				$this->output("Exiting, encountered API error at {$i} due to: {$e->getMessage()}\n");
				exit;
			}

			$cheevos = [
				'curse_profile_add_friend' => 0
			];
			if (isset($statProgress) && !empty($statProgress)) {
				foreach ($statProgress as $index => $userStat) {
					if (in_array($userStat->getStat(), ['curse_profile_add_friend'])) {
						$cheevos[$userStat->getStat()] = $userStat->getCount();
					}
				}
			}
			$delta = $local;
			foreach ($local as $stat => $count) {
				if (isset($cheevos[$stat])) {
					$delta[$stat] = $local[$stat] - $cheevos[$stat];
				}
			}

			if ($this->getOption('v')) {
				$this->output("\tLocal: ".json_encode($local)."\n");
				$this->output("\tCheevos: ".json_encode($cheevos)."\n");
				$this->output("\tDelta: ".json_encode($delta)."\n");
			}

			$increment = [
				'user_id'		=> $globalId,
				'site_key'		=> $dsSiteKey,
				'timestamp'		=> time(),
				'request_uuid'	=> sha1($globalId.$dsSiteKey.time().random_bytes(4))
			];

			foreach ($delta as $stat => $delta) {
				if ($delta != 0) {
					$increment['deltas'][] = ['stat' => $stat, 'delta' => $delta];
				}
			}

			if (isset($increment['deltas'])) {
				if ($this->getOption('v')) {
					$this->output("\tSending delta(s)...\n");
				}
				try {
					$return = \Cheevos\Cheevos::increment($increment);
					if (isset($return['earned'])) {
						foreach ($return['earned'] as $achievement) {
							$achievement = new \Cheevos\CheevosAchievement($achievement);
							if ($this->getOption('v')) {
								$this->output("\tAwarding {$achievement->getId()} - {$achievement->getName()}...");
							}
							\CheevosHooks::displayAchievement($achievement, $increment['site_key'], $increment['user_id']);
							Hooks::run('AchievementAwarded', [$achievement, $globalId]);
							if ($this->getOption('v')) {
								$this->output("done.\n");
							}
						}
					}
					if (isset($return['unearned'])) {
						foreach ($return['unearned'] as $progress) {
							$progress = new \Cheevos\CheevosAchievementProgress($progress);
							$achievement = $achievements[$progress->getAchievement_Id()];
							if ($this->getOption('v')) {
								$this->output("\tUnawarding {$achievement->getId()} - {$achievement->getName()}...");
							}
							$deleted = Cheevos\Cheevos::deleteProgress($progress->getId(), $globalId);
							if ($deleted['code'] == 200) {
								Hooks::run('AchievementUnawarded', [$achievement, $globalId]);
								if ($this->getOption('v')) {
									$this->output("done.\n");
								}
							}
						}
					}
				} catch (\Cheevos\CheevosException $e) {
					$this->output("Exiting, encountered API error at {$i} due to: {$e->getMessage()}\n");
					exit;
				}
			}
		}
	}
}

$maintClass = 'SyncFriendStat';
if (defined('RUN_MAINTENANCE_IF_MAIN')) {
	require_once( RUN_MAINTENANCE_IF_MAIN );
} else {
	require_once( DO_MAINTENANCE );
}
