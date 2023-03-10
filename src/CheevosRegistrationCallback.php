<?php

namespace Cheevos;

use Cheevos\Maintenance\ReplaceGlobalIdWithUserId;
use MediaWiki\Installer\Hook\LoadExtensionSchemaUpdatesHook;
use MediaWiki\MediaWikiServices;

class CheevosRegistrationCallback implements LoadExtensionSchemaUpdatesHook {

	/** Setup anything that needs to be configured before anything else runs. */
	public static function onRegistration(): void {
		global $wgDefaultUserOptions, $wgNamespacesForEditPoints, $wgReverbNotifications;

		$wgDefaultUserOptions['cheevos-popup-notification'] = 1;

		// Allowed namespaces.
		if ( empty( $wgNamespacesForEditPoints ) ) {
			$namespaceInfo = MediaWikiServices::getInstance()->getNamespaceInfo();
			$wgNamespacesForEditPoints = $namespaceInfo->getContentNamespaces();
		}

		$reverbNotifications = [
			'user-interest-achievement-earned' => [ 'importance' => 8 ],
		];
		$wgReverbNotifications = array_merge( $wgReverbNotifications, $reverbNotifications );
	}

	/** @inheritDoc */
	public function onLoadExtensionSchemaUpdates( $updater ) {
		$extDir = __DIR__;

		if ( CheevosHelper::isCentralWiki() ) {
			$updater->addExtensionTable(
				'points_comp_report',
				"$extDir/install/sql/table_points_comp_report.sql"
			);
			$updater->addExtensionTable(
				'points_comp_report_user',
				"$extDir/install/sql/table_points_comp_report_user.sql"
			);

			$updater->addExtensionField(
				'points_comp_report',
				'comp_skipped',
				"$extDir/upgrade/sql/points_comp_report/add_comp_skipped.sql"
			);
			$updater->modifyExtensionField(
				'points_comp_report',
				'comp_failed',
				"$extDir/upgrade/sql/points_comp_report/change_comp_failed_default_0.sql"
			);
			$updater->modifyExtensionField(
				'points_comp_report',
				'max_points',
				"$extDir/upgrade/sql/points_comp_report/change_max_points_null.sql"
			);
			$updater->addExtensionField(
				'points_comp_report_user',
				'comp_skipped',
				"$extDir/upgrade/sql/points_comp_report_user/add_comp_skipped.sql"
			);
			$updater->modifyExtensionField(
				'points_comp_report_user',
				'comp_failed',
				"$extDir/upgrade/sql/points_comp_report_user/change_comp_failed_default_0.sql"
			);
			$updater->addExtensionField(
				'points_comp_report_user',
				'user_id',
				"$extDir/upgrade/sql/points_comp_report_user/add_field_user_id.sql"
			);
			$updater->addExtensionIndex(
				'points_comp_report_user',
				'report_id_user_id',
				"$extDir/upgrade/sql/points_comp_report_user/add_index_report_id_user_id.sql"
			);
			$updater->dropExtensionIndex(
				'points_comp_report_user',
				'report_id_global_id',
				"$extDir/upgrade/sql/points_comp_report_user/drop_index_report_id_global_id.sql"
			);
			$updater->addPostDatabaseUpdateMaintenance( ReplaceGlobalIdWithUserId::class );

			// Point Levels
			$updater->addExtensionTable(
				'wiki_points_levels',
				"$extDir/install/sql/table_wiki_points_levels.sql"
			);
		}

		$updater->dropExtensionTable(
			'achievement',
			"$extDir/upgrade/sql/drop_table_achievement.sql"
		);
		$updater->dropExtensionTable(
			'achievement_category',
			"$extDir/upgrade/sql/drop_table_achievement_category.sql"
		);
		$updater->dropExtensionTable(
			'achievement_earned',
			"$extDir/upgrade/sql/drop_table_achievement_earned.sql"
		);
		$updater->dropExtensionTable(
			'achievement_hook',
			"$extDir/upgrade/sql/drop_table_achievement_hook.sql"
		);
		$updater->dropExtensionTable(
			'achievement_link',
			"$extDir/upgrade/sql/drop_table_achievement_link.sql"
		);
		$updater->dropExtensionTable(
			'achievement_site_mega',
			"$extDir/upgrade/sql/drop_table_achievement_site_mega.sql"
		);
		$updater->dropExtensionTable(
			'dataminer_user_global_totals',
			"$extDir/upgrade/sql/drop_table_dataminer_user_global_totals.sql"
		);
		$updater->dropExtensionTable(
			'dataminer_user_wiki_periodicals',
			"$extDir/upgrade/sql/drop_table_dataminer_user_wiki_periodicals.sql"
		);
		$updater->dropExtensionTable(
			'dataminer_user_wiki_totals',
			"$extDir/upgrade/sql/drop_table_dataminer_user_wiki_totals.sql"
		);
		$updater->dropExtensionTable(
			'display_names',
			"$extDir/upgrade/sql/drop_table_display_names.sql"
		);
		$updater->dropExtensionTable(
			'wiki_points',
			"$extDir/upgrade/sql/drop_table_wiki_points.sql"
		);
		$updater->dropExtensionTable(
			'wiki_points_monthly_totals',
			"$extDir/upgrade/sql/drop_table_wiki_points_monthly_totals.sql"
		);
		$updater->dropExtensionTable(
			'wiki_points_multipliers',
			"$extDir/upgrade/sql/drop_table_wiki_points_multipliers.sql"
		);
		$updater->dropExtensionTable(
			'wiki_points_multipliers_sites',
			"$extDir/upgrade/sql/drop_table_wiki_points_multipliers_sites.sql"
		);
		$updater->dropExtensionTable(
			'wiki_points_site_monthly_totals',
			"$extDir/upgrade/sql/drop_table_wiki_points_site_monthly_totals.sql"
		);
		$updater->dropExtensionTable(
			'wiki_points_site_totals',
			"$extDir/upgrade/sql/drop_table_wiki_points_site_totals.sql"
		);
		$updater->dropExtensionTable(
			'wiki_points_totals',
			"$extDir/upgrade/sql/drop_table_wiki_points_totals.sql"
		);

		return true;
	}
}
