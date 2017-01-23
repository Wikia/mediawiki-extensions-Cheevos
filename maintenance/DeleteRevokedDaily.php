<?php
/**
 * Cheevos
 * Deleted Revoked Daily
 *
 * @author		Wiki Platform Team
 * @copyright	(c) 2017 Curse Inc.
 * @license		All Rights Reserved
 * @package		Cheevos
 * @link		http://www.curse.com/
 *
 **/

require_once(dirname(dirname(dirname(__DIR__)))."/maintenance/Maintenance.php");

class DeleteRevokedDaily extends Maintenance {
	/**
	 * Constructor
	 *
	 * @access	public
	 * @return	void
	 */
	public function __construct() {
		parent::__construct();
	}

	/**
	 * Delete invalid revoked achievement points.
	 *
	 * @access	public
	 * @return	void
	 */
	public function execute() {
		if (!defined('ACHIEVEMENTS_MASTER') || ACHIEVEMENTS_MASTER !== true) {
			echo "This maintenance script must be ran from the master wiki.\n";
			exit;
		}

		$DB = wfGetDB(DB_MASTER);

		$success = $DB->delete(
			'wiki_points',
			[
				'reason'	=> 4,
				'score'		=> [
					'-2',
					'-50',
					'-150',
					'-500',
					'-1000'
				]
			],
			__METHOD__
		);
		$DB->commit();
	}
}

$maintClass = 'DeleteRevokedDaily';
require_once(RUN_MAINTENANCE_IF_MAIN);
