<?php
/**
 * Curse Inc.
 * Cheevos
 * Dumps WikiPoints tables to SQL to import into Cheevos.
 *
 * @package   Cheevos
 * @author    Alexia E. Smith
 * @copyright (c) 2017 Curse Inc.
 * @license   GPL-2.0-or-later
 * @link      https://www.gamepedia.com/
**/

require_once __DIR__ . '/../../../maintenance/Maintenance.php';

class DumpWikiPointsSqlImport extends Maintenance {
	/**
	 * Constructor
	 *
	 * @return void
	 */
	public function __construct() {
		parent::__construct();
		$this->addDescription('Dumps WikiPoints tables to SQL to import into Cheevos.');
		$this->addOption('folder', 'Specify a folder to dump a file into instead of outputing to the terminal.', true, true);
	}

	/**
	 * Main execution of the maintenance script
	 *
	 * @return void
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
			'reason'	=> 1,
			'revoked'	=> 0
		];

		$result = $db->select(
			['wiki_points', 'revision', 'user_global'],
			['count(*) AS total', 'revision.rev_len', 'user_global.global_id'],
			$where,
			__METHOD__,
			[],
			[
				'revision' => [
					'LEFT JOIN', 'revision.rev_id = wiki_points.edit_id'
				],
				'user_global' => [
					'INNER JOIN', 'user_global.user_id = wiki_points.user_id'
				]
			]
		);
		$total = intval($result->fetchRow()['total']);

		$file = fopen($folder . '/' . $wgDBname . '_wiki_points.sql', 'w+');
		fwrite($file, "SET @site_id = (SELECT id FROM site_key WHERE `key` = '" . $dsSiteKey . "');\n");
		$sql = "INSERT INTO `point_log` (`user_id`, `site_id`, `revision_id`, `page_id`, `timestamp`, `size`, `size_diff`, `points`) VALUES\n";
		fwrite($file, $sql);
		$inserts = [];

		$userIdGlobalId = [];
		$insert = false;
		$maxLines = 0;
		for ($i = 0; $i <= $total; $i = $i + 1000) {
			$maxLines += 1000;
			$result = $db->select(
				['wiki_points', 'revision', 'user_global'],
				['wiki_points.*', 'revision.rev_len', 'user_global.global_id'],
				$where,
				__METHOD__,
				[
					'OFFSET'	=> $i,
					'LIMIT'		=> 1000,
					'ORDER BY'	=> 'wiki_points_id ASC'
				],
				[
					'revision' => [
						'LEFT JOIN', 'revision.rev_id = wiki_points.edit_id'
					],
					'user_global' => [
						'INNER JOIN', 'user_global.user_id = wiki_points.user_id'
					]
				]
			);

			while ($row = $result->fetchRow()) {
				if ($insert !== false) {
					fwrite($file, $insert . ",\n");
				}

				if ($row['user_id'] < 1 || $row['article_id'] < 1) {
					continue;
				}

				$globalId = intval($row['global_id']);
				if (!$globalId) {
					continue;
				}

				$size = intval($row['rev_len']);

				if (strpos($row['calculation_info'], '\"') !== false) {
					$row['calculation_info'] = str_replace('\"', '"', $row['calculation_info']);
				}
				$calcInfo = json_decode($row['calculation_info'], true);
				$sizeDiff = $calcInfo['inputs']['z'];
				$insert = '(' . $globalId . ', @site_id, ' . $row['edit_id'] . ', ' . $row['article_id'] . ', ' . wfTimestamp(TS_UNIX, $row['created']) . ', ' . $size . ', ' . $sizeDiff . ', ' . $row['score'] . ")";
			}
			if ($maxLines >= 30000) {
				$maxLines = 0;
				fwrite($file, $insert . ";\n");
				$insert = false;
				fwrite($file, $sql);
			}
		}
		if ($insert !== false) {
			fwrite($file, $insert);
		}
		fwrite($file, ";\n");
		fclose($file);
	}
}

$maintClass = 'DumpWikiPointsSqlImport';
require_once RUN_MAINTENANCE_IF_MAIN;
