<?php
/**
 * Curse Inc.
 * Cheevos
 * Syncs data from WikiPoints tables to the Cheevos service.
 * Should be run regularly to account for out-of-band WikiPoints adjustments.
 *
 * @author		Robert Nix
 * @copyright	(c) 2017 Curse Inc.
 * @license		All Rights Reserved
 * @package		Cheevos
 * @link		https://www.gamepedia.com/
 *
**/
require_once(__DIR__.'/../../../maintenance/Maintenance.php');

class SyncWikiPoints extends Maintenance {
	/**
	 * Constructor
	 *
	 * @access	public
	 * @return	void
	 */
	public function __construct() {
		parent::__construct();
		$this->addDescription('Syncs data from WikiPoints tables to the Cheevos service.  Should be run regularly to account for out-of-band WikiPoints adjustments.');
	}

	/**
	 * Main execution of the maintenance script
	 *
	 * @access	public
	 * @return	void
	 */
	public function execute() {
		global $dsSiteKey;

		$db = wfGetDB(DB_MASTER);

		$where = [
			'user_id > 0'
		];

		$result = $db->select(
			['wiki_points_totals'],
			['count(*) AS total'],
			$where,
			__METHOD__
		);
		$total = intval($result->fetchRow()['total']);
		$this->output("Adjusting wiki points totals for ${total} users in Cheevos...\n");

		for ($i = 0; $i <= $total; $i = $i + 1000) {
			$this->output("Iteration start {$i}\n");

			$result = $db->select(
				['wiki_points_totals'],
				['*'],
				$where,
				__METHOD__,
				[
					'OFFSET'	=> $i,
					'LIMIT'		=> 1000,
					'ORDER BY'	=> 'user_id ASC'
				]
			);

			while ($row = $result->fetchRow()) {
				$userId = intval($row['user_id']);
				$user = User::newFromId($userId);
				$user->load();
				if ($user->isAnon()) {
					continue;
				}

				$lookup = \CentralIdLookup::factory();
				$globalId = $lookup->centralIdFromLocalUser($user, \CentralIdLookup::AUDIENCE_RAW);

				$currentScore = intval($row['score']);
				try {
					// Grab current score from the API so we can adjust as needed:
					$currentCheevosScore = 0;
					$filters = [
						'user_id' => $globalId,
						'site_key' => $dsSiteKey,
						'stat' => 'wiki_points',
						'limit' => 1
					];
					$stats = \Cheevos\Cheevos::getStatProgress($filters);
					if (count($stats) > 0) {
						$currentCheevosScore = current($stats)['count'];
					}

					$delta = $currentScore - $currentCheevosScore;
					if ($delta == 0) {
						continue;
					}

					$data = [
						'user_id' => $globalId,
						'site_key' => $dsSiteKey,
						'deltas' => [
							[
								'stat' => 'wiki_points',
								'delta' => intval($delta)
							]
						]
					];
					$status = \Cheevos\Cheevos::increment($data);
					$this->output("Adjusted user:{$userId} / global:{$globalId}: {$delta}\n");
				} catch (\Cheevos\CheevosException $e) {
					$this->output("Exiting, encountered API error at user:{$userId} / global:{$globalId} due to: {$e->getMessage()}\n");
					exit;
				}
			}
		}
	}
}

$maintClass = 'SyncWikiPoints';
if (defined('RUN_MAINTENANCE_IF_MAIN')) {
	require_once( RUN_MAINTENANCE_IF_MAIN );
} else {
	require_once( DO_MAINTENANCE );
}
