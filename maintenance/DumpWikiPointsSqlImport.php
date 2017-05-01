<?php
/**
 * Curse Inc.
 * Cheevos
 * Dumps WikiPoints tables to SQL to import into Cheevos.
 *
 * @author		Alexia E. Smith
 * @copyright	(c) 2017 Curse Inc.
 * @license		All Rights Reserved
 * @package		Cheevos
 * @link		https://www.gamepedia.com/
 *
**/
require_once(__DIR__.'/../../../maintenance/Maintenance.php');

class DumpWikiPointsSqlImport extends Maintenance {
	/**
	 * Constructor
	 *
	 * @access	public
	 * @return	void
	 */
	public function __construct() {
		parent::__construct();
		$this->addDescription('Dumps WikiPoints tables to SQL to import into Cheevos.');
		$this->addOption('folder', 'Specify a folder to dump a file into instead of outputing to the terminal.', true, true);
	}

	/**
	 * Main execution of the maintenance script
	 *
	 * @access	public
	 * @return	void
	 */
	public function execute() {
		global $dsSiteKey, $wgDBname;

		$folder = false;
		if ($this->getOption('folder')) {
			$folder = $this->getOption('folder');
			$folder = realpath($folder);
			if ($folder === false) {
				$this->error("Selected folder is not valid.");
				exit;
			}
			if (!is_writable($folder)) {
				$this->error("Selected folder is not writable.");
				exit;
			}
		}

		$db = wfGetDB(DB_MASTER);

		$where = [
			'user_id > 0',
			'article_id > 0',
			'reason' => 1
		];

		$result = $db->select(
			['wiki_points'],
			['count(*) AS total'],
			$where,
			__METHOD__
		);
		$total = intval($result->fetchRow()['total']);

		$file = fopen($folder.'/'.$wgDBname.'_wiki_points.sql', 'w+');
		$sql = "INSERT INTO `point_log` (`user_id`, `site_id`, `revision_id`, `page_id`, `timestamp`, `size`, `size_diff`, `points`) VALUES\n";
		fwrite($file, $sql);
		$inserts = [];

		$userIdGlobalId = [];
		$lookup = \CentralIdLookup::factory();
		$insert = false;
		for ($i = 0; $i <= $total; $i = $i + 1000) {
			if ($insert !== false) {
				fwrite($file, $insert.",\n");
			}
			$result = $db->select(
				['wiki_points'],
				['*'],
				$where,
				__METHOD__,
				[
					'OFFSET'	=> $i,
					'LIMIT'		=> 1000,
					'ORDER BY'	=> 'wiki_points_id ASC'
				]
			);

			while ($row = $result->fetchRow()) {
				$userId = intval($row['user_id']);
				if (isset($userIdGlobalId[$userId])) {
					$globalId = $userIdGlobalId[$userId];
				} else {
					$globalId = HydraAuthUser::globalIdFromUserId($userId);
					$userIdGlobalId[$userId] = $globalId;
				}

				if (!$globalId) {
					continue;
				}

				$revision = Revision::newFromId($row['edit_id']);
				if (is_null($revision)) {
					continue;
				}
				$size = $revision->getSize();

				$calcInfo = json_decode($row['calculation_info'], true);
				$sizeDiff = $calcInfo['inputs']['z'];
				$insert = '('.$globalId.', (SELECT id FROM site_key WHERE `key` = \''.$dsSiteKey.'\'), '.$row['edit_id'].', '.$row['article_id'].', '.wfTimestamp(TS_UNIX, $row['created']).', '.$size.', '.$sizeDiff.', '.$row['score'].")";
			}
		}
		if ($insert !== false) {
			fwrite($file, $insert.";\n");
		}
		fclose($file);
	}
}

$maintClass = 'DumpWikiPointsSqlImport';
if (defined('RUN_MAINTENANCE_IF_MAIN')) {
	require_once( RUN_MAINTENANCE_IF_MAIN );
} else {
	require_once( DO_MAINTENANCE );
}
