<?php
/**
 * Cheevos
 * Cheevos Hooks
 *
 * @package   Cheevos
 * @author    Hydra Wiki Platform Team
 * @copyright (c) 2017 Curse Inc.
 * @license   GPL-2.0-or-later
 * @link      https://gitlab.com/hydrawiki/extensions/cheevos
 */

namespace Cheevos;

use Cheevos\Maintenance\ReplaceGlobalIdWithUserId;
use ManualLogEntry;
use MediaWiki\Auth\Hook\LocalUserCreatedHook;
use MediaWiki\Hook\ArticleMergeCompleteHook;
use MediaWiki\Hook\BeforeInitializeHook;
use MediaWiki\Hook\BeforePageDisplayHook;
use MediaWiki\Hook\BlockIpCompleteHook;
use MediaWiki\Hook\ContributionsToolLinksHook;
use MediaWiki\Hook\EmailUserCompleteHook;
use MediaWiki\Hook\GetMagicVariableIDsHook;
use MediaWiki\Hook\LoginFormValidErrorMessagesHook;
use MediaWiki\Hook\MarkPatrolledCompleteHook;
use MediaWiki\Hook\PageMoveCompleteHook;
use MediaWiki\Hook\ParserFirstCallInitHook;
use MediaWiki\Hook\ParserGetVariableValueSwitchHook;
use MediaWiki\Hook\SkinTemplateNavigation__UniversalHook;
use MediaWiki\Hook\UploadCompleteHook;
use MediaWiki\Hook\UserToolLinksEditHook;
use MediaWiki\Hook\WatchArticleCompleteHook;
use MediaWiki\Installer\Hook\LoadExtensionSchemaUpdatesHook;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\MediaWikiServices;
use MediaWiki\Page\Hook\ArticleProtectCompleteHook;
use MediaWiki\Page\Hook\PageDeleteCompleteHook;
use MediaWiki\Page\ProperPageIdentity;
use MediaWiki\Permissions\Authority;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Storage\EditResult;
use MediaWiki\User\UserIdentity;
use RedisCache;
use RequestContext;
use Skin;
use SpecialPage;
use Title;
use User;
use Wikimedia\Rdbms\ILoadBalancer;
use WikiPage;

class CheevosHooks implements
	LoadExtensionSchemaUpdatesHook,
	GetMagicVariableIDsHook,
	ParserFirstCallInitHook,
	LoginFormValidErrorMessagesHook,
	BeforePageDisplayHook,
	ContributionsToolLinksHook,
	UserToolLinksEditHook,
	SkinTemplateNavigation__UniversalHook,
	ParserGetVariableValueSwitchHook,
	BeforeInitializeHook,
	WatchArticleCompleteHook,
	LocalUserCreatedHook,
	UploadCompleteHook,
	MarkPatrolledCompleteHook,
	EmailUserCompleteHook,
	BlockIpCompleteHook,
	ArticleProtectCompleteHook,
	ArticleMergeCompleteHook,
	PageDeleteCompleteHook,
	PageMoveCompleteHook
{

	private const PROFILE_FIELD_TO_STAT_MAP = [
		'profile-favwiki' => 'curse_profile_edit_fav_wiki',
		'profile-link-facebook' => 'curse_profile_edit_link_facebook',
		'profile-link-psn' => 'curse_profile_edit_link_psn',
		'profile-link-steam' => 'curse_profile_edit_link_steam',
		'profile-link-reddit' => 'curse_profile_edit_link_reddit',
		'profile-link-twitch' => 'curse_profile_edit_link_twitch',
		'profile-link-twitter' => 'curse_profile_edit_link_twitter',
		'profile-link-vk' => 'curse_profile_edit_link_vk',
		'profile-link-xbl' => 'curse_profile_edit_link_xbl',
	];

	public function __construct(
		private LinkRenderer $linkRenderer,
		private RedisCache $redisCache,
		private ILoadBalancer $loadBalancer,
		private AchievementService $achievementService,
		private CheevosHelper $cheevosHelper
	) {
	}

	/**
	 * Increment for a statistic.
	 *
	 * @param string $stat Stat Name
	 * @param int $delta Stat Delta
	 * @param UserIdentity $user Local User object.
	 * @param array $edits Array of edit information for article_create or article_edit statistics.
	 *
	 * @return void Array of return status including earned achievements or false on error.
	 */
	public static function increment( string $stat, int $delta, UserIdentity $user, array $edits = [] ): void {
		MediaWikiServices::getInstance()->getService( CheevosHelper::class )
			->increment( $stat, $delta, $user, $edits );
	}

	/** @inheritDoc */
	public function onPageDeleteComplete(
		ProperPageIdentity $page,
		Authority $deleter,
		string $reason,
		int $pageID,
		RevisionRecord $deletedRev,
		ManualLogEntry $logEntry,
		int $archivedRevisionCount
	) {
		$user = MediaWikiServices::getInstance()->getUserFactory()->newFromAuthority( $deleter );
		$this->cheevosHelper->increment( 'article_delete', 1, $user );
	}

	/**
	 * Updates user's points after they've made an edit in a namespace that is listed in the
	 * $wgNamespacesForEditPoints array.
	 * This hook will not be called if a null revision is created.
	 *
	 * @param WikiPage $wikiPage Article
	 * @param RevisionRecord $revision Revision
	 * @param mixed $originalRevId (Unused)
	 * @param User $user User that performed the action.
	 * @param mixed &$tags (Unused)
	 *
	 * @return bool True
	 */
	public static function onRevisionFromEditComplete(
		WikiPage $wikiPage, RevisionRecord $revision, $originalRevId, User $user, &$tags
	) {
		global $wgNamespacesForEditPoints;

		$isBot = $user->isAllowed( 'bot' );
		$parentRevisionId = $revision->getParentId();

		if ( !$parentRevisionId ) {
			self::increment( 'article_create', 1, $user );
		}

		if ( $isBot ) {
			self::increment( 'article_edit_is_bot', 1, $user );
		}
		if ( $user->getId() ) {
			self::increment( 'article_edit_is_logged_in', 1, $user );
		} else {
			self::increment( 'article_edit_is_logged_out', 1, $user );
		}

		$isType = [];
		// Note: Reordering this code will cause differently named statistics.
		if ( class_exists( 'MobileContext' ) ) {
			$mobileContext = MediaWikiServices::getInstance()->getService( 'MobileFrontend.Context' );
			if ( $mobileContext->shouldDisplayMobileView() ) {
				$isType[] = 'is_mobile';
			} else {
				$isType[] = 'is_desktop';
			}
		}

		$context = RequestContext::getMain();
		if ( $context->getRequest()->getVal( 'veaction' ) === 'edit' ||
			 $context->getRequest()->getVal( 'action' ) === 'visualeditoredit' ) {
			$isType[] = 'is_visual';
		} else {
			$isType[] = 'is_source';
		}
		foreach ( $isType as $type ) {
			self::increment( 'article_edit_' . $type, 1, $user );
		}
		self::increment( 'article_edit_' . implode( '_', $isType ), 1, $user );

		$edits = [];
		if ( !$isBot && in_array( $wikiPage->getTitle()->getNamespace(), $wgNamespacesForEditPoints ) ) {
			$previousRevision = null;
			if ( $parentRevisionId ) {
				$revStore = MediaWikiServices::getInstance()->getRevisionStore();
				$previousRevision = $revStore->getRevisionById( $parentRevisionId );
			}
			$prevSize = $previousRevision ? $previousRevision->getSize() : 0;
			$sizeDiff = $revision->getSize() - $prevSize;
			$edits[] = [
				'size' => $revision->getSize(),
				'size_diff' => $sizeDiff,
				'page_id' => $wikiPage->getId(),
				'revision_id' => $revision->getId(),
			];
		}

		self::increment( 'article_edit', 1, $user, $edits );

		return true;
	}

	/**
	 * Check for an article rollback, which then revokes all points for revisions that were reverted
	 *
	 * @param WikiPage $wikiPage The article edited
	 * @param UserIdentity $user The user performing the change
	 * @param string $summary Edit summary
	 * @param int $flags Edit flags
	 * @param RevisionRecord $revision The new revision
	 * @param EditResult $editResult Contains information about a potential revert
	 *
	 * @return bool True
	 */
	public static function onPageSaveComplete(
		WikiPage $wikiPage, UserIdentity $user, string $summary, int $flags, RevisionRecord $revision,
		EditResult $editResult
	) {
		if ( !$editResult->isRevert() || $editResult->getRevertMethod() !== EditResult::REVERT_ROLLBACK ) {
			// Not a rollback so we don't care
			return true;
		}
		$siteKey = CheevosHelper::getSiteKey();
		if ( !$siteKey ) {
			return true;
		}

		$revisionStore = MediaWikiServices::getInstance()->getRevisionStore();
		$revertedRev = $revisionStore->getRevisionById( $editResult->getNewestRevertedRevisionId() );
		$oldestRevId = $editResult->getOldestRevertedRevisionId();
		$editsToRevoke = [];
		// Revoke every edit that was reverted as a result of this rollback
		while ( $revertedRev ) {
			$editsToRevoke[] = $revertedRev->getId();
			if ( $revertedRev->getId() == $oldestRevId ) {
				break;
			}
			$revertedRev = $revisionStore->getRevisionById( $revertedRev->getParentId() );
		}

		try {
			Cheevos::revokeEditPoints( $wikiPage->getId(), $editsToRevoke, $siteKey );
		}
		catch ( CheevosException $e ) {
			// Honey Badger
			wfLogWarning( "Cheevos Service is unavailable: " . $e->getMessage() );
		}

		return true;
	}

	/** @inheritDoc */
	public function onArticleMergeComplete( $targetTitle, $destTitle ) {
		$this->cheevosHelper->increment( 'article_merge', 1, RequestContext::getMain()->getUser() );
	}

	/** @inheritDoc */
	public function onArticleProtectComplete( $wikiPage, $user, $protect, $reason ) {
		$this->cheevosHelper->increment( 'article_protect', 1, $user );
	}

	/** @inheritDoc */
	public function onPageMoveComplete( $old, $new, $user, $pageid, $redirid, $reason, $revision ) {
		$this->cheevosHelper->increment( 'article_move', 1, $user );
	}

	/** @inheritDoc */
	public function onBlockIpComplete( $block, $user, $priorBlock ) {
		$this->cheevosHelper->increment( 'admin_block_ip', 1, $user );
	}

	public function onCurseProfileAddComment( User $fromUser, User $toUser, $inReplyTo, $commentText ) {
		$this->cheevosHelper->increment( 'curse_profile_comment', 1, $fromUser );
	}

	public function onCurseProfileAddCommentReply( User $fromUser, User $toUser, $inReplyTo, $commentText ) {
		$this->cheevosHelper->increment( 'curse_profile_comment_reply', 1, $fromUser );
	}

	public function onCurseProfileAddFriend( User $fromUser, User $toUser ) {
		$this->cheevosHelper->increment( 'curse_profile_add_friend', 1, $fromUser );
	}

	/**
	 * fixme: call 'CurseProfileAcceptFriend' hook when adding friend in CurseProfile
	 */
	public function onCurseProfileAcceptFriend( User $fromUser, User $toUser ) {
		$this->cheevosHelper->increment( 'curse_profile_accept_friend', 1, $fromUser );
	}

	/** @inheritDoc */
	public function onCurseProfileCanComment( User $fromUser, User $toUser, int $editsToComment ): bool {
		try {
			$stats = $this->achievementService->getStatProgress(
				[ 'global' => true, 'stat' => 'article_edit', ],
				$fromUser
			);
			$stats = CheevosHelper::makeNiceStatProgressArray( $stats );

			$editCount = (int)( $stats[$fromUser->getId()]['article_edit']['count'] ?? 0 );
			return $editCount >= $editsToComment;
		} catch ( CheevosException $e ) {
			wfDebug( "Encountered Cheevos API error getting article_edit count." );
			// TODO--is it a good idea to allow on error??
			return true;
		}
	}

	/** @inheritDoc */
	public function onCurseProfileEdited( User $user, $field, $value ): void {
		$this->cheevosHelper->increment( 'curse_profile_edit', 1, $user );

		if ( empty( $value ) || !in_array( $value, self::PROFILE_FIELD_TO_STAT_MAP ) ) {
			return;
		}
		$this->cheevosHelper->increment( self::PROFILE_FIELD_TO_STAT_MAP[ $value ], 1, $user );
	}

	/** @inheritDoc */
	public function onEmailUserComplete( $to, $from, $subject, $text ) {
		$this->cheevosHelper->increment( 'send_email', 1, RequestContext::getMain()->getUser() );
	}

	/** @inheritDoc */
	public function onMarkPatrolledComplete( $rcid, $user, $wcOnlySysopsCanPatrol, $auto ) {
		$this->cheevosHelper->increment( 'admin_patrol', 1, $user );
	}

	/** @inheritDoc */
	public function onUploadComplete( $uploadBase ) {
		$this->cheevosHelper->increment( 'file_upload', 1, RequestContext::getMain()->getUser() );
	}

	/** @inheritDoc */
	public function onWatchArticleComplete( $user, $page ) {
		$this->cheevosHelper->increment( 'article_watch', 1, $user );
	}

	/** @inheritDoc */
	public function onLocalUserCreated( $user, $autocreated ) {
		$this->cheevosHelper->increment( 'account_create', 1, $user );
	}

	/**
	 * fixme: call 'WikiPointsSave' hook when updating wiki points
	 */
	public function onWikiPointsSave(
		int $editId, int $userId, int $articleId, int $score, string $calculationInfo, string $reason = ''
	) {
		$user = RequestContext::getMain()->getUser();
		if ( $score !== 0 && $user->isRegistered() && $user->getId() === $userId ) {
			$this->cheevosHelper->increment( 'wiki_points', $score, $user );
		}
	}

	/**
	 * @inheritDoc
	 * Add styles for Reverb notifications to every page.
	 */
	public function onBeforePageDisplay( $out, $skin ): void {
		if ( $out->getUser()->isAnon() ) {
			return;
		}

		$out->addModuleStyles( 'ext.cheevos.notifications.styles' );
	}

	/** @inheritDoc */
	public function onBeforeInitialize( $title, $unused, $output, $user, $request, $mediaWiki ) {
		// Do not track anonymous users for visits. The Cheevos database can not handle it.
		if ( PHP_SAPI !== 'cli' && !defined( 'MW_API' ) && $user->isRegistered() ) {
			$this->cheevosHelper->increment( 'visit', 1, $user );
		}
	}

	/** @inheritDoc */
	public function onLoginFormValidErrorMessages( array &$messages ) {
		$messages[] = 'login_to_display_achievements';
	}

	/** @inheritDoc */
	public function onSkinTemplateNavigation__Universal( $sktemplate, &$links ): void {
		if ( $sktemplate->getUser()->isAnon() ) {
			return;
		}

		$achievementLink = [
			'achievements' => [
				'text' => wfMessage( 'achievements' )->text(),
				'href' => Skin::makeSpecialUrl( 'Achievements' ),
				'active' => true,
			],
		];

		// Add new link before 'mycontris'
		$userMenuLinks = $links['user-menu'];
		$insertPoint = array_search( 'mycontris', array_keys( $userMenuLinks ), true );
		$userMenuLinks = array_merge(
			array_slice( $userMenuLinks, 0, $insertPoint ),
			$achievementLink,
			array_slice( $userMenuLinks, $insertPoint )
		);
		$links['user-menu'] = $userMenuLinks;
	}

	/** @inheritDoc */
	public function onUserToolLinksEdit( $userId, $userText, &$items ) {
		$link = $this->getLinkToWikiPoints( $userId, $userText );
		if ( $link ) {
			$items[] = $link;
		}
	}

	/** @inheritDoc */
	public function onContributionsToolLinks( $id, Title $title, array &$tools, SpecialPage $specialPage ) {
		$link = $this->getLinkToWikiPoints( $id, $title->getText() );
		if ( $link ) {
			$tools[] = $link;
		}
	}

	/** Add a link to WikiPoints on contribution and edit tool links. */
	private function getLinkToWikiPoints( int $userId, string $username ): ?string {
		if ( !$userId || !RequestContext::getMain()->getUser()->isAllowed( 'wiki_points_admin' ) ) {
			return null;
		}

		return $this->linkRenderer->makeKnownLink(
			SpecialPage::getTitleFor( 'WikiPointsAdmin' ),
			wfMessage( 'sp_contributions_wikipoints_admin' )->escaped(),
			[ 'class' => 'mw-usertoollinks-wikipointsadmin' ],
			[ 'action' => 'lookup', 'user' => $username ]
		);
	}

	/** @inheritDoc */
	public function onParserFirstCallInit( $parser ) {
		$parser->setFunctionHook( 'wikipointsblock', 'Cheevos\Points\PointsDisplay::pointsBlock' );
	}

	/** @inheritDoc */
	public function onGetMagicVariableIDs( &$variableIDs ) {
		$variableIDs[] = 'numberofcontributors';
	}

	/**
	 * @inheritDoc
	 * Handles custom {{numberofcontributors}} magic word.
	 */
	public function onParserGetVariableValueSwitch( $parser, &$variableCache, $magicWordId, &$ret, $frame ) {
		if ( strtolower( $magicWordId ) !== 'numberofcontributors' ) {
			return;
		}

		$redis = $this->redisCache->getConnection( 'cache' );

		$redisKey = 'cheevos:contributors:' . CheevosHelper::getSiteKey();
		if ( $redis ) {
			$cache = $redis->get( $redisKey );
			if ( $cache !== false ) {
				$ret = (string)$cache;
				return;
			}
		}

		$contributorCount = $this->loadBalancer->getConnection( DB_REPLICA )
			->selectRowCount(
				'revision',
				'distinct(rev_actor)',
				[],
				__METHOD__
			);
		if ( $redis ) {
			$redis->setEx( $redisKey, 3600, $contributorCount );
		}

		$ret = (string)$contributorCount;
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
