<?php
/**
 * Replace Global ID with User ID Maintenance Script
 *
 * @package   Cheevos
 * @copyright (c) 2020 Curse Inc.
 * @license   GPL-2.0-or-later
 * @link      https://gitlab.com/hydrawiki
 */

namespace Cheevos\Maintenance;

use LoggedUpdateMaintenance;

require_once dirname( __DIR__, 4 ) . '/maintenance/Maintenance.php';

/**
 * Maintenance script that cleans up tables that have orphaned users.
 * Currently Noop
 */
class ReplaceGlobalIdWithUserId extends LoggedUpdateMaintenance {

	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Replaces global ID with user ID in Cheevos tables.' );
		$this->setBatchSize( 100 );
	}

	/** @inheritDoc */
	protected function getUpdateKey() {
		return __CLASS__;
	}

	/** @inheritDoc */
	protected function doDBUpdates() {
		// Noop - required function `HydraAuthUser::userIdFromGlobalId` has been removed in previous migrations
		return true;
	}
}

$maintClass = ReplaceGlobalIdWithUserId::class;
require_once RUN_MAINTENANCE_IF_MAIN;
