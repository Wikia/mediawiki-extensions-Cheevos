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

use HydraAuthUser;
use LoggedUpdateMaintenance;
use MediaWiki\MediaWikiServices;
use stdClass;
use Wikimedia\Rdbms\IDatabase;

require_once dirname( dirname( dirname( dirname( __DIR__ ) ) ) ) . '/maintenance/Maintenance.php';

/**
 * Maintenance script that cleans up tables that have orphaned users.
 */
class ReplaceGlobalIdWithUserId extends LoggedUpdateMaintenance {
	private $prefix;

	private $table;

	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Replaces global ID with user ID in Cheevos tables.' );
		$this->setBatchSize( 100 );
	}

	/**
	 * Return an unique name to logged this maintenance as being done.
	 *
	 * @return string
	 */
	protected function getUpdateKey() {
		return __CLASS__;
	}

	/**
	 * Do database updates for all tables.
	 *
	 * @return bool True
	 */
	protected function doDBUpdates() {
		$this->cleanup(
			'points_comp_report_user',
			'ru_id',
			[ 'global_id' => 'user_id' ],
			[ 'ru_id' ]
		);

		return true;
	}

	/**
	 * Cleanup a table.
	 *
	 * @param string $table Table to migrate
	 * @param string $primaryKey Primary key of the table.
	 * @param array $globalIdFields Global ID field to User ID field as $key => $value relation.
	 * @param array $orderby Fields to order by
	 *
	 * @return void
	 */
	protected function cleanup(
		string $table, string $primaryKey, array $globalIdFields, array $orderby
	) {
		$dbw = $this->getDB( DB_PRIMARY );

		foreach ( $globalIdFields as $key => $value ) {
			if ( !$dbw->fieldExists( $table, $key ) ) {
				unset( $globalIdFields[$key] );
			}
		}
		if ( empty( $globalIdFields ) ) {
			$this->output( "Skipping due to global ID fields not being present.\n" );
			return;
		}

		$this->output(
			"Beginning cleanup of $table\n"
		);

		$next = '1=1';
		$count = 0;
		while ( true ) {
			// Fetch the rows needing update
			$res = $dbw->select(
				$table,
				array_merge( [ $primaryKey ], array_keys( $globalIdFields ) ),
				[ $next ],
				__METHOD__,
				[
					'ORDER BY' => $orderby,
					'LIMIT' => $this->mBatchSize,
				]
			);
			if ( !$res->numRows() ) {
				break;
			}

			// Update the existing rows
			foreach ( $res as $row ) {
				$update = [];
				foreach ( $globalIdFields as $globalIdField => $userIdField ) {
					if ( $row->$globalIdField ) {
						$userId = HydraAuthUser::userIdFromGlobalId( $row->$globalIdField );
						if ( $userId > 0 ) {
							$update[$userIdField] = $userId;
						}
					}
				}

				if ( !empty( $update ) ) {
					$dbw->update(
						$table,
						$update,
						[ $primaryKey => $row->$primaryKey ],
						__METHOD__
					);
					$count += $dbw->affectedRows();
				}
			}

			[ $next, $display ] = $this->makeNextCond( $dbw, $orderby, $row );
			$this->output( "... $display\n" );
			MediaWikiServices::getInstance()->getDBLoadBalancerFactory()->waitForReplication();
		}

		$this->output(
			"Cleanup complete: Replaced {$count} global IDs with user ID\n"
		);
	}

	/**
	 * Calculate a "next" condition and progress display string
	 *
	 * @param IDatabase $dbw
	 * @param string[] $indexFields Fields in the index being ordered by
	 * @param stdClass $row Database row
	 *
	 * @return string[] [ string $next, string $display ]
	 */
	private function makeNextCond( $dbw, array $indexFields, stdClass $row ) {
		$next = '';
		$display = [];
		for ( $i = count( $indexFields ) - 1; $i >= 0; $i-- ) {
			$field = $indexFields[$i];
			$display[] = $field . '=' . $row->$field;
			$value = $dbw->addQuotes( $row->$field );
			if ( $next === '' ) {
				$next = "$field > $value";
			} else {
				$next = "$field > $value OR $field = $value AND ($next)";
			}
		}
		$display = implode( ' ', array_reverse( $display ) );
		return [ $next, $display ];
	}
}

$maintClass = \Cheevos\Maintenance\ReplaceGlobalIdWithUserId::class;
require_once RUN_MAINTENANCE_IF_MAIN;
