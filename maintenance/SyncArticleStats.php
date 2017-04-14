<?php
/**
 * Curse Inc.
 * Cheevos
 * Synchronizes data on edits and creations to the Cheevos service.
 *
 * @author		Alexia E. Smith
 * @copyright	(c) 2017 Curse Inc.
 * @license		All Rights Reserved
 * @package		Cheevos
 * @link		https://www.gamepedia.com/
 *
**/
require_once(__DIR__.'/../../../maintenance/Maintenance.php');

class SyncArticleStats extends Maintenance {
	/**
	 * Constructor
	 *
	 * @access	public
	 * @return	void
	 */
	public function __construct() {
		parent::__construct();
		$this->addDescription('Synchronizes data on edits and creations to the Cheevos service.');
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
			$this->output("Iteration start {$i}\n");

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
				$this->output("Global ID: {$row['global_id']} - ");


				//Creations
				$revisionResult = $db->select(
					['revision'],
					['count(*) AS total'],
					[
						'rev_user'		=> $userId,
						'rev_parent_id'	=> 0
					],
					__METHOD__
				);
				$totalCreations = intval($revisionResult->fetchRow()['total']);
				$this->output("Creations: {$totalCreations} - ");

				//Edits
				$revisionResult = $db->select(
					['revision'],
					['count(*) AS total'],
					[
						'rev_user'	=> $userId
					],
					__METHOD__
				);
				$totalEdits = intval($revisionResult->fetchRow()['total']);
				$this->output("Edits: {$totalEdits} - ");

				//Deletes
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
				$totalDeletes = intval($revisionResult->fetchRow()['total']);
				$this->output("Deletes: {$totalDeletes}\n");
			}
		}
	}
}

$maintClass = 'SyncArticleStats';
if (defined('RUN_MAINTENANCE_IF_MAIN')) {
	require_once( RUN_MAINTENANCE_IF_MAIN );
} else {
	require_once( DO_MAINTENANCE );
}
