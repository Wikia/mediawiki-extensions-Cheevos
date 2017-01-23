<?php
/**
 * Cheevos
 * Update All Site Megas
 *
 * @author		Wiki Platform Team
 * @copyright	(c) 2017 Curse Inc.
 * @license		All Rights Reserved
 * @package		Cheevos
 * @link		http://www.curse.com/
 *
 **/

require_once(dirname(dirname(dirname(__DIR__)))."/maintenance/Maintenance.php");

class UpdateAllSiteMegas extends Maintenance {
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
	 * Processes achievements that may need to be awarded to people.
	 *
	 * @access	public
	 * @return	void
	 */
	public function execute() {
		if (!defined('ACHIEVEMENTS_MASTER') || ACHIEVEMENTS_MASTER !== true) {
			echo "This maintenance script must be ran from the master wiki.\n";
			exit;
		}

		$this->DB = wfGetDB(DB_MASTER);

		$result = $this->DB->select(
			['wiki_sites'],
			['md5_key'],
			null,
			__METHOD__
		);

		while ($row = $result->fetchRow()) {
			\Cheevos\SiteMegaUpdate::queue(['site_key' => $row['md5_key']]);
		}
	}
}

$maintClass = 'UpdateAllSiteMegas';
require_once(RUN_MAINTENANCE_IF_MAIN);
