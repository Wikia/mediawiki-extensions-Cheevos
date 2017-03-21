<?php
/**
 * Curse Inc.
 * Cheevos
 * Imports customized achievements.
 *
 * @author		Alexia E. Smith
 * @copyright	(c) 2017 Curse Inc.
 * @license		All Rights Reserved
 * @package		Cheevos
 * @link		https://www.gamepedia.com/
 *
**/
require_once(__DIR__.'/../../../maintenance/Maintenance.php');

class ImportCustomAchievements extends Maintenance {
	/**
	 * Constructor
	 *
	 * @access	public
	 * @return	void
	 */
	public function __construct() {
		parent::__construct();
		$this->addDescription('Imports customized achievements.  Should only ever be run once.');
		$this->addOption('restart', 'Run again from the beginning even if run before.');
	}

	/**
	 * Main execution of the maintenance script
	 *
	 * @access	public
	 * @return	void
	 */
	public function execute() {
		$cache = wfGetCache(CACHE_MEMCACHED);
		if (MASTER_WIKI !== true) {
			throw new MWException('This script is intended to be ran from the master wiki and only once.');
		} elseif ($this->getOption('restart')) {
			$cache->set('ImportCustomAchievements', 0);
		}

		$start = intval($cache->get('ImportCustomAchievements'));

		$db = wfGetDB(DB_MASTER);

		$where = [
			'global_id > 0'
		];

		$result = $db->select(
			['dataminer_user_wiki_totals'],
			['count(*) AS total'],
			$where,
			__METHOD__,
			[
				'ORDER BY'	=> 'global_id ASC, site_key ASC'
			]
		);
		$total = $result->fetchRow();
		$total = intval($total['total']) - $cache->get('ImportCustomAchievements');
		$this->output("Import data miner wiki totals...\n");

		for ($i = $start; $i <= $total; $i = $i + 1000) {
			$this->output("Iteration start {$i}\n");

			$result = $db->select(
				['dataminer_user_wiki_totals'],
				['*'],
				$where,
				__METHOD__,
				[
					'OFFSET'	=> $i,
					'LIMIT'		=> 1000,
					'ORDER BY'	=> 'global_id ASC, site_key ASC'
				]
			);

			while ($row = $result->fetchRow()) {
				$data = [
					'user_id' => intval($row['global_id']),
					'site_key' => $row['site_key'],
					'deltas' => [
						[
							'stat' => 'article_edit',
							'delta' => intval($row['wiki_edits'])
						],
						[
							'stat' => 'article_delete',
							'delta' => intval($row['wiki_deletes'])
						],
						[
							'stat' => 'admin_patrol',
							'delta' => intval($row['wiki_patrols'])
						],
						[
							'stat' => 'admin_block_ip',
							'delta' => intval($row['wiki_blocks'])
						]
					]
				];
				try {
					$status = \Cheevos\Cheevos::increment($data);
					$this->output("Processed {$row['global_id']} - {$row['site_key']}\n");
				} catch (\Cheevos\CheevosException $e) {
					$this->output("Exiting, encountered API error at {$row['global_id']} - {$row['site_key']} due to: {$e->getMessage()}\n");
					exit;
				}
				$cache->set('ImportCustomAchievements', $i);
			}
		}
	}
}

$maintClass = 'ImportCustomAchievements';
if (defined('RUN_MAINTENANCE_IF_MAIN')) {
	require_once( RUN_MAINTENANCE_IF_MAIN );
} else {
	require_once( DO_MAINTENANCE );
}
