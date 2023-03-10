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
 */

require_once __DIR__ . '/../../../maintenance/Maintenance.php';

use Cheevos\AchievementService;
use Cheevos\CheevosAchievement;
use Cheevos\CheevosAchievementProgress;
use Cheevos\CheevosException;
use Cheevos\CheevosHelper;
use MediaWiki\MediaWikiServices;
use Wikimedia\Rdbms\ILoadBalancer;

// TODO--update DB queries (join actor and replace dropped columns) - remove this script
// `user_global` table doesn't exist on minecraft, terraria, help gamepedia wikis
// This table is only used in SyncArticleStats and CopyMasterUsersToClusters scripts
class SyncArticleStats extends Maintenance {

	private AchievementService $achievementService;
	private ILoadBalancer $loadBalancer;
	/** @var CheevosAchievement[] */
	private array $achievements;
	private string $dsSiteKey;
	private bool $verbose;

	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Synchronizes data on edits and creations to the Cheevos service.' );
		$this->addOption( 'v', 'Verbose - Show debug information.' );
	}

	private function init(): void {
		$services = MediaWikiServices::getInstance();
		$this->achievementService = $services->getService( AchievementService::class );
		$this->loadBalancer = $services->getDBLoadBalancer();
		$this->achievements = $this->achievementService->getAchievements( $this->dsSiteKey );
		$this->dsSiteKey = CheevosHelper::getSiteKey();
		$this->verbose = $this->hasOption( 'v' );
	}

	/** @inheritDoc */
	public function execute() {
		$this->init();
		$where = [ 'global_id > 0', 'user_id > 0' ];
		$db = $this->loadBalancer->getConnection( DB_REPLICA );

		$total = $db->selectRowCount(
			'user_global',
			'*',
			$where,
			__METHOD__,
			[ 'ORDER BY' => 'global_id ASC' ]
		);
		$this->output( "Updating article statistics for $total users in Cheevos...\n" );

		for ( $i = 0; $i <= $total; $i += 1000 ) {
			if ( $this->verbose ) {
				$this->output( "Iteration start $i\n" );
			}

			$result = $db->select(
				'user_global',
				[ 'global_id', 'user_id' ],
				$where,
				__METHOD__,
				[
					'OFFSET' => $i,
					'LIMIT' => 1000,
					'ORDER BY' => 'global_id ASC'
				]
			);

			while ( $row = $result->fetchRow() ) {
				$globalId = (int)$row[ 'global_id' ];
				if ( $this->verbose ) {
					$this->output( "Global ID: $globalId\n" );
				}

				$local = $this->getUserData( (int)$row[ 'user_id' ] );
				$this->syncStats( $i, $globalId, $local );
			}
		}
	}

	private function getUserData( int $userId ): array {
		$db = $this->loadBalancer->getConnection( DB_REPLICA );
		$local = [];
		// Creations
		$local['article_create'] = $db->selectRowCount(
			'revision',
			'*',
			[ 'rev_user' => $userId, 'rev_parent_id' => 0 ],
			__METHOD__
		);
		$local['article_create'] += $db->selectRowCount(
			'archive',
			'*',
			[ 'ar_user' => $userId, 'ar_parent_id' => 0 ],
			__METHOD__
		);

		// Edits
		$revisionResult = $db->select(
			[ 'revision' ],
			[
				'rev_text_id',
				'COUNT(rev_text_id) as total'
			],
			[ 'rev_user' => $userId ],
			__METHOD__,
			[ 'GROUP BY' => 'rev_text_id' ]
		);
		$local['article_edit'] = 0;
		while ( $row = $revisionResult->fetchRow() ) {
			$local['article_edit']++;
		}
		// Archived Edits
		$revisionResult = $db->select(
			[ 'archive' ],
			[
				'ar_text_id',
				'COUNT(ar_text_id) as total'
			],
			[ 'ar_user' => $userId ],
			__METHOD__,
			[ 'GROUP BY' => 'ar_text_id' ]
		);
		while ( $row = $revisionResult->fetchRow() ) {
			$local['article_edit']++;
		}

		// Deletes
		$local['article_delete'] = $db->selectRowCount(
			'logging',
			'*',
			[
				'log_type' => 'delete',
				'log_action' => 'delete',
				'log_user' => $userId
			],
			__METHOD__
		);

		// Uploads
		$local['file_upload'] = $db->selectRowCount(
			'logging',
			'*',
			[ 'log_type' => 'upload', 'log_user' => $userId ],
			__METHOD__
		);
		return $local;
	}

	private function syncStats( int $i, int $globalId, array $local ): void {
		try {
			$statProgress = $this->achievementService->getStatProgress(
				[
					'user_id' => $globalId,
					'site_key' => $this->dsSiteKey,
					'limit' => 0
				]
			);
		} catch ( CheevosException $e ) {
			$this->output( "Exiting, encountered API error at $i due to: {$e->getMessage()}\n" );
			exit;
		}

		$cheevos = [
			'article_create' => 0,
			'article_edit' => 0,
			'article_delete' => 0,
			'file_upload' => 0
		];
		foreach ( $statProgress as $userStat ) {
			if ( in_array( $userStat->getStat(), [
				'article_create',
				'article_edit',
				'article_delete',
				'file_upload'
			] ) ) {
				$cheevos[$userStat->getStat()] = $userStat->getCount();
			}
		}

		$delta = $local;
		foreach ( $local as $stat => $count ) {
			if ( isset( $cheevos[$stat] ) ) {
				$delta[$stat] = $count - $cheevos[$stat];
			}
		}
		if ( $this->verbose ) {
			$this->output( "\tLocal: " . json_encode( $local ) . "\n" );
			$this->output( "\tCheevos: " . json_encode( $cheevos ) . "\n" );
			$this->output( "\tDelta: " . json_encode( $delta ) . "\n" );
		}

		$increment = [
			'user_id' => $globalId,
			'site_key' => $this->dsSiteKey,
			'timestamp' => time(),
			'request_uuid' => sha1( $globalId . $this->dsSiteKey . time() . random_bytes( 4 ) )
		];
		foreach ( $delta as $stat => $value ) {
			if ( $value != 0 ) {
				$increment['deltas'][] = [ 'stat' => $stat, 'delta' => $value ];
			}
		}
		if ( !isset( $increment['deltas'] ) ) {
			return;
		}

		if ( $this->verbose ) {
			$this->output( "\tSending delta(s)...\n" );
		}
		try {
			$return = $this->achievementService->increment( $increment );
			if ( isset( $return['earned'] ) ) {
				foreach ( $return['earned'] as $achievement ) {
					$achievement = new CheevosAchievement( $achievement );
					if ( $this->verbose ) {
						$this->output( "\tAwarding {$achievement->getId()} - {$achievement->getName()}..." );
					}
					$this->achievementService->broadcastAchievement(
						$achievement,
						$increment['site_key'] ?? '',
						(int)$increment['user_id']
					);
					if ( $this->verbose ) {
						$this->output( "done.\n" );
					}
				}
			}
			if ( isset( $return['unearned'] ) ) {
				foreach ( $return['unearned'] as $progress ) {
					$progress = new CheevosAchievementProgress( $progress );
					$achievement = $this->achievements[$progress->getAchievement_Id()];
					if ( $this->verbose ) {
						$this->output( "\tUnawarding {$achievement->getId()} - {$achievement->getName()}..." );
					}
					$this->achievementService->deleteProgress( $progress->getId() );
					if ( $this->verbose ) {
						$this->output( "done.\n" );
					}
				}
			}
		} catch ( CheevosException $e ) {
			$this->output( "Exiting, encountered API error at $i due to: {$e->getMessage()}\n" );
			exit;
		}
	}
}

$maintClass = SyncArticleStats::class;
require_once RUN_MAINTENANCE_IF_MAIN;
