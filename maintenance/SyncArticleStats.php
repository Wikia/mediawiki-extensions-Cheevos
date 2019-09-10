<?php
/**
 * Curse Inc.
 * Cheevos
 * Synchronizes data on edits and creations to the Cheevos service.
 *
 * @package   Cheevos
 * @author    Alexia E. Smith
 * @copyright (c) 2017 Curse Inc.
 * @license   GPL-2.0-or-later
 * @link      https://www.gamepedia.com/
**/

require_once __DIR__ . '/../../../maintenance/Maintenance.php';

class SyncArticleStats extends Maintenance {
	/**
	 * Constructor
	 *
	 * @return void
	 */
	public function __construct() {
		parent::__construct();
		$this->addDescription('Synchronizes data on edits and creations to the Cheevos service.');
		$this->addOption('v', 'Verbose - Show debug information.');
	}

	/**
	 * Main execution of the maintenance script
	 *
	 * @return void
	 */
	public function execute() {
		global $dsSiteKey;

		$achievements = \Cheevos\Cheevos::getAchievements($dsSiteKey);

		$db = wfGetDB(DB_MASTER);

		$where = [
			'global_id > 0',
			'user_id > 0'
		];

		$result = $db->select(
			['user_global'],
			['count(*) AS total'],
			$where,
			__METHOD__,
			[
				'ORDER BY'	=> 'global_id ASC'
			]
		);
		$total = intval($result->fetchRow()['total']);
		$this->output("Updating article statistics for {$total} users in Cheevos...\n");

		for ($i = 0; $i <= $total; $i = $i + 1000) {
			if ($this->getOption('v')) {
				$this->output("Iteration start {$i}\n");
			}

			$result = $db->select(
				['user_global'],
				[
					'global_id',
					'user_id'
				],
				$where,
				__METHOD__,
				[
					'OFFSET'	=> $i,
					'LIMIT'		=> 1000,
					'ORDER BY'	=> 'global_id ASC'
				]
			);

			while ($row = $result->fetchRow()) {
				$userId = intval($row['user_id']);
				$globalId = intval($row['global_id']);
				if ($this->getOption('v')) {
					$this->output("Global ID: {$globalId}\n");
				}

				$local = [];

				// Creations
				$revisionResult = $db->select(
					['revision'],
					['count(*) AS total'],
					[
						'rev_user'		=> $userId,
						'rev_parent_id'	=> 0
					],
					__METHOD__
				);
				$local['article_create'] = intval($revisionResult->fetchRow()['total']);
				$revisionResult = $db->select(
					['archive'],
					['count(*) AS total'],
					[
						'ar_user'		=> $userId,
						'ar_parent_id'	=> 0
					],
					__METHOD__
				);
				$local['article_create'] += intval($revisionResult->fetchRow()['total']);

				// Edits
				$revisionResult = $db->select(
					['revision'],
					[
						'rev_text_id',
						'COUNT(rev_text_id) as total'
					],
					[
						'rev_user'	=> $userId
					],
					__METHOD__,
					[
						'GROUP BY'	=> 'rev_text_id'
					]
				);
				$local['article_edit'] = 0;
				while ($row = $revisionResult->fetchRow()) {
					$local['article_edit']++;
				}
				// Archived Edits
				$revisionResult = $db->select(
					['archive'],
					[
						'ar_text_id',
						'COUNT(ar_text_id) as total'
					],
					[
						'ar_user'	=> $userId
					],
					__METHOD__,
					[
						'GROUP BY'	=> 'ar_text_id'
					]
				);
				while ($row = $revisionResult->fetchRow()) {
					$local['article_edit']++;
				}

				// Deletes
				$revisionResult = $db->select(
					['logging'],
					['count(*) AS total'],
					[
						'log_type'		=> 'delete',
						'log_action'	=> 'delete',
						'log_user'		=> $userId
					],
					__METHOD__
				);
				$local['article_delete'] = intval($revisionResult->fetchRow()['total']);

				// Uploads
				$revisionResult = $db->select(
					['logging'],
					['count(*) AS total'],
					[
						'log_type'		=> 'upload',
						'log_user'		=> $userId
					],
					__METHOD__
				);
				$local['file_upload'] = intval($revisionResult->fetchRow()['total']);

				try {
					$statProgress = \Cheevos\Cheevos::getStatProgress(
						[
							'user_id'	=> $globalId,
							'site_key'	=> $dsSiteKey,
							'limit'		=> 0
						]
					);
				} catch (\Cheevos\CheevosException $e) {
					$this->output("Exiting, encountered API error at {$i} due to: {$e->getMessage()}\n");
					exit;
				}

				$cheevos = [
					'article_create' => 0,
					'article_edit' => 0,
					'article_delete' => 0,
					'file_upload' => 0
				];
				if (isset($statProgress) && !empty($statProgress)) {
					foreach ($statProgress as $index => $userStat) {
						if (in_array($userStat->getStat(), ['article_create', 'article_edit', 'article_delete', 'file_upload'])) {
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
					$this->output("\tLocal: " . json_encode($local) . "\n");
					$this->output("\tCheevos: " . json_encode($cheevos) . "\n");
					$this->output("\tDelta: " . json_encode($delta) . "\n");
				}

				$increment = [
					'user_id'		=> $globalId,
					'site_key'		=> $dsSiteKey,
					'timestamp'		=> time(),
					'request_uuid'	=> sha1($globalId . $dsSiteKey . time() . random_bytes(4))
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
								\CheevosHooks::broadcastAchievement($achievement, $increment['site_key'], $increment['user_id']);
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
}

$maintClass = 'SyncArticleStats';
require_once RUN_MAINTENANCE_IF_MAIN;
