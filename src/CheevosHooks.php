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

use ApiMain;
use Article;
use Cheevos\Job\CheevosIncrementJob;
use Cheevos\Maintenance\ReplaceGlobalIdWithUserId;
use Content;
use HydraCore;
use LogEntry;
use MailAddress;
use MediaWiki;
use MediaWiki\Block\DatabaseBlock;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Storage\EditResult;
use MediaWiki\User\UserIdentity;
use OutputPage;
use Parser;
use RedisCache;
use RequestContext;
use Reverb\Notification\NotificationBroadcast;
use Skin;
use SkinTemplate;
use SpecialPage;
use TemplateAchievements;
use Title;
use UploadBase;
use User;
use WebRequest;
use WikiPage;

class CheevosHooks {
	/**
	 * Shutdown Function Registered Already
	 *
	 * @var bool
	 */
	private static bool $shutdownRegistered = false;

	/**
	 * Shutdown Function Ran Already
	 *
	 * @var bool
	 */
	private static bool $shutdownRan = false;

	/**
	 * Data points to increment on shutdown.
	 *
	 * @var array
	 */
	private static array $increments = [];

	/**
	 * Setup anything that needs to be configured before anything else runs.
	 *
	 * @return void
	 */
	public static function onRegistration(): void {
		global $wgDefaultUserOptions, $wgNamespacesForEditPoints, $wgReverbNotifications;

		$wgDefaultUserOptions['cheevos-popup-notification'] = 1;

		// Allowed namespaces.
		if ( empty( $wgNamespacesForEditPoints ) ) {
			$namespaceInfo = MediaWikiServices::getInstance()->getNamespaceInfo();
			$wgNamespacesForEditPoints = $namespaceInfo->getContentNamespaces();
		}

		$reverbNotifications = [
			"user-interest-achievement-earned" => [
				"importance" => 8,
			],
		];
		$wgReverbNotifications = array_merge( (array)$wgReverbNotifications, $reverbNotifications );
	}

	/**
	 * Undocumented function
	 *
	 * @return bool
	 */
	public static function invalidateCache(): bool {
		// this is here for future functionality.
		return Cheevos::invalidateCache();
	}

	/**
	 * Increment for a statistic.
	 *
	 * @param string $stat Stat Name
	 * @param int $delta Stat Delta
	 * @param UserIdentity $user Local User object.
	 * @param array $edits Array of edit information for article_create or article_edit statistics.
	 *
	 * @return bool Array of return status including earned achievements or false on error.
	 */
	public static function increment( string $stat, int $delta, UserIdentity $user, array $edits = [] ): bool {
		$siteKey = CheevosHelper::getSiteKey();
		if ( !$siteKey ) {
			return false;
		}

		$globalId = Cheevos::getUserIdForService( $user );
		if ( $globalId <= 0 ) {
			return true;
		}

		self::$increments[$globalId]['user_id'] = $globalId;
		self::$increments[$globalId]['user_name'] = $user->getName();
		self::$increments[$globalId]['site_key'] = $siteKey;
		self::$increments[$globalId]['deltas'][] = [ 'stat' => $stat, 'delta' => $delta ];
		self::$increments[$globalId]['timestamp'] = time();
		self::$increments[$globalId]['request_uuid'] =
			sha1( self::$increments[$globalId]['user_id'] . self::$increments[$globalId]['site_key'] .
				  self::$increments[$globalId]['timestamp'] . random_bytes( 4 ) );
		if ( !empty( $edits ) ) {
			if ( !isset( self::$increments[$globalId]['edits'] ) ||
				 !is_array( self::$increments[$globalId]['edits'] ) ) {
				self::$increments[$globalId]['edits'] = [];
			}
			self::$increments[$globalId]['edits'] = array_merge( self::$increments[$globalId]['edits'], $edits );
		}

		if ( self::$shutdownRan ) {
			self::doIncrements();
		}

		return true;
	}

	/**
	 * Handle article deletion increment.
	 *
	 * @param WikiPage &$article the article that was deleted.
	 * @param User &$user the user that deleted the article
	 * @param string $reason the reason the article was deleted
	 * @param int $id id of the article that was deleted (added in 1.13)
	 * @param Content|null $content the content of the deleted article, or null in case of an error (added in 1.21)
	 * @param LogEntry $logEntry the log entry used to record the deletion (added in 1.21)
	 *
	 * @return bool True
	 */
	public static function onArticleDeleteComplete(
		WikiPage &$article, User &$user, $reason, $id, Content $content, LogEntry $logEntry
	): bool {
		self::increment( 'article_delete', 1, $user );

		return true;
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

	/**
	 * Handle article merge increment.
	 *
	 * @param Title $targetTitle Source Title
	 * @param Title $destTitle Destination Title
	 *
	 * @return bool True
	 */
	public static function onArticleMergeComplete( Title $targetTitle, Title $destTitle ) {
		$user = RequestContext::getMain()->getUser();
		self::increment( 'article_merge', 1, $user );

		return true;
	}

	/**
	 * Handle article protect increment.
	 *
	 * @param WikiPage &$wikiPage Article object that was protected
	 * @param User &$user User object who did the protection.
	 * @param array $limit Protection limits being added.
	 * @param string $reason Reason for protect
	 *
	 * @return bool True
	 */
	public static function onArticleProtectComplete( WikiPage &$wikiPage, User &$user, $limit, $reason ) {
		self::increment( 'article_protect', 1, $user );

		return true;
	}

	/**
	 * Handle article move increment.
	 *
	 * @param LinkTarget $old Original Title
	 * @param LinkTarget $new New Title
	 * @param UserIdentity $user The User object who did move.
	 * @param int $pageId
	 * @param int $redirId
	 * @param string $reason
	 * @param RevisionRecord $revision
	 *
	 * @return bool True
	 */
	public static function onPageMoveComplete(
		LinkTarget $old, LinkTarget $new, UserIdentity $user, int $pageId, int $redirId, string $reason,
		RevisionRecord $revision
	) {
		self::increment( 'article_move', 1, $user );

		return true;
	}

	/**
	 * Handle article protect increment.
	 *
	 * @param DatabaseBlock $block Block
	 * @param User $user User object of who performed the block.
	 *
	 * @return bool True
	 */
	public static function onBlockIpComplete( DatabaseBlock $block, User $user ) {
		self::increment( 'admin_block_ip', 1, $user );

		return true;
	}

	/**
	 * Handle CurseProfile comment increment.
	 *
	 * @param User $fromUser User making the comment.
	 * @param User $toUser User of the profile being commented on.
	 * @param int $inReplyTo Parent ID of the comment.
	 * @param string $commentText The comment text.
	 *
	 * @return bool True
	 */
	public static function onCurseProfileAddComment( User $fromUser, User $toUser, $inReplyTo, $commentText ) {
		self::increment( 'curse_profile_comment', 1, $fromUser );

		return true;
	}

	/**
	 * Handle CurseProfile comment reply increment.
	 *
	 * @param User $fromUser User making the comment.
	 * @param User $toUser User of the profile being commented on.
	 * @param int $inReplyTo Parent ID of the comment.
	 * @param string $commentText The comment text.
	 *
	 * @return bool True
	 */
	public static function onCurseProfileAddCommentReply( User $fromUser, User $toUser, $inReplyTo, $commentText ) {
		self::increment( 'curse_profile_comment_reply', 1, $fromUser );

		return true;
	}

	/**
	 * Handle CurseProfile friend addition increment.
	 *
	 * @param User $fromUser User object of the user requesting to add a friend.
	 * @param User $toUser User object of the user being requested as a friend.
	 *
	 * @return bool True
	 */
	public static function onCurseProfileAddFriend( User $fromUser, User $toUser ) {
		self::increment( 'curse_profile_add_friend', 1, $fromUser );

		return true;
	}

	/**
	 * Handle CurseProfile friend accept increment.
	 *
	 * @param User $fromUser User object of the user accepting a friend request.
	 * @param User $toUser User object of the user that initiated the friend request.
	 *
	 * @return bool True
	 */
	public static function onCurseProfileAcceptFriend( User $fromUser, User $toUser ) {
		self::increment( 'curse_profile_accept_friend', 1, $fromUser );

		return true;
	}

	/**
	 * Handle when CurseProfile is checking if an user can comment.
	 *
	 * @param User $fromUser User object of the user attempting to comment.
	 * @param User $toUser User object of the user that owns the comment board.
	 * @param int $editsToComment The number of edits required to comment.
	 *
	 * @return bool
	 */
	public static function onCurseProfileCanComment( User $fromUser, User $toUser, int $editsToComment ): bool {
		$editCount = 0;
		try {
			$stats = Cheevos::getStatProgress( [
				'global' => true,
				'stat' => 'article_edit',
			], $fromUser );
			$stats = CheevosHelper::makeNiceStatProgressArray( $stats );
			$editCount =
				( isset( $stats[$fromUser->getId()]['article_edit']['count'] ) &&
				  $stats[$fromUser->getId()]['article_edit']['count'] > $editCount
					? $stats[$fromUser->getId()]['article_edit']['count'] : $editCount );
		}
		catch ( CheevosException $e ) {
			wfDebug( "Encountered Cheevos API error getting article_edit count." );
		}

		return $editCount >= $editsToComment;
	}

	/**
	 * Handle CurseProfile profile edited.
	 *
	 * @param User $user User profile edited.
	 * @param string $field Field being edited.
	 * @param string $value Field Value
	 *
	 * @return bool True
	 */
	public static function onCurseProfileEdited( User $user, $field, $value ) {
		self::increment( 'curse_profile_edit', 1, $user );
		if ( !empty( $value ) ) {
			switch ( $field ) {
				case 'profile-favwiki':
					self::increment( 'curse_profile_edit_fav_wiki', 1, $user );
					break;
				case 'profile-link-facebook':
					self::increment( 'curse_profile_edit_link_facebook', 1, $user );
					break;
				case 'profile-link-psn':
					self::increment( 'curse_profile_edit_link_psn', 1, $user );
					break;
				case 'profile-link-steam':
					self::increment( 'curse_profile_edit_link_steam', 1, $user );
					break;
				case 'profile-link-reddit':
					self::increment( 'curse_profile_edit_link_reddit', 1, $user );
					break;
				case 'profile-link-twitch':
					self::increment( 'curse_profile_edit_link_twitch', 1, $user );
					break;
				case 'profile-link-twitter':
					self::increment( 'curse_profile_edit_link_twitter', 1, $user );
					break;
				case 'profile-link-vk':
					self::increment( 'curse_profile_edit_link_vk', 1, $user );
					break;
				case 'profile-link-xbl':
					self::increment( 'curse_profile_edit_link_xbl', 1, $user );
					break;
			}
		}

		return true;
	}

	/**
	 * Handle email sent increment.
	 *
	 * @param MailAddress $address Address of receiving user
	 * @param MailAddress $from Address of sending user
	 * @param string $subject Subject of the mail
	 * @param string $text Text of the mail
	 *
	 * @return bool True
	 */
	public static function onEmailUserComplete( MailAddress $address, MailAddress $from, $subject, $text ) {
		$user = RequestContext::getMain()->getUser();
		self::increment( 'send_email', 1, $user );

		return true;
	}

	/**
	 * Handle mark patrolled increment.
	 *
	 * @param int $rcid Recent Change Primary ID that was marked as patrolled.
	 * @param User $user User that marked the change as patrolled.
	 * @param bool $automatic Automatically Patrolled
	 *
	 * @return bool True
	 */
	public static function onMarkPatrolledComplete( int $rcid, User $user, bool $automatic ) {
		self::increment( 'admin_patrol', 1, $user );

		return true;
	}

	/**
	 * Handle upload increment.
	 *
	 * @param UploadBase &$image
	 *
	 * @return bool True
	 */
	public static function onUploadComplete( &$image ) {
		$user = RequestContext::getMain()->getUser();
		self::increment( 'file_upload', 1, $user );

		return true;
	}

	/**
	 * Handle watch article increment.
	 *
	 * @param User $user User watching the article.
	 * @param WikiPage $article Article being watched by the user.
	 *
	 * @return bool True
	 */
	public static function onWatchArticleComplete( User $user, WikiPage $article ) {
		self::increment( 'article_watch', 1, $user );

		return true;
	}

	/**
	 * Handle when a local user is created in the database.
	 *
	 * @param User $user User created.
	 * @param bool $autoCreated Automatic Creation
	 *
	 * @return bool True
	 */
	public static function onLocalUserCreated( User $user, bool $autoCreated ) {
		self::increment( 'account_create', 1, $user );

		return true;
	}

	/**
	 * Handles awarding WikiPoints achievements.
	 *
	 * @param int $editId Revision Edit ID
	 * @param int $userId Local User ID
	 * @param int $articleId Article ID
	 * @param int $score Score for the edit, not the overall score.
	 * @param string $calculationInfo JSON of Calculation Information
	 * @param string $reason [Optional] Stated reason for these points.
	 *
	 * @return bool True
	 */
	public static function onWikiPointsSave(
		int $editId, int $userId, int $articleId, int $score, string $calculationInfo, string $reason = ''
	) {
		$user = RequestContext::getMain()->getUser();
		if ( ( $score > 0 || $score < 0 ) && $user->getId() == $userId && $userId > 0 ) {
			self::increment( 'wiki_points', intval( $score ), $user );
		}

		return true;
	}

	/**
	 * Registers shutdown function to do increments.
	 *
	 * @param ApiMain &$processor ApiMain
	 *
	 * @return bool True
	 */
	public static function onApiBeforeMain( ApiMain &$processor ) {
		if ( PHP_SAPI === 'cli' || self::$shutdownRegistered ) {
			return true;
		}

		register_shutdown_function( 'Cheevos\CheevosHooks' );

		self::$shutdownRegistered = true;

		return true;
	}

	/**
	 * Add styles for Reverb notifications to every page.
	 *
	 * @param OutputPage &$output Mediawiki Output Object
	 * @param SkinTemplate &$skin Mediawiki Skin Object
	 *
	 * @return bool True
	 */
	public static function onBeforePageDisplay( OutputPage &$output, SkinTemplate &$skin ) {
		if ( $output->getUser()->isAnon() ) {
			return true;
		}

		$output->addModuleStyles( 'ext.cheevos.notifications.styles' );

		return true;
	}

	/**
	 * Registers shutdown function to do increments.
	 *
	 * @param Title &$title Title
	 * @param Article &$article Article
	 * @param OutputPage &$output Output
	 * @param User &$user User
	 * @param WebRequest $request WebRequest
	 * @param MediaWiki $mediaWiki Mediawiki
	 *
	 * @return bool True
	 */
	public static function onBeforeInitialize(
		Title &$title, &$article, OutputPage &$output, User &$user, WebRequest $request, MediaWiki $mediaWiki
	) {
		if ( PHP_SAPI === 'cli' || self::$shutdownRegistered ) {
			return true;
		}

		// Do not track anonymous users for visits.  The Cheevos database can not handle it.
		if ( !defined( 'MW_API' ) && $user->getId() > 0 ) {
			self::increment( 'visit', 1, $user );
		}

		register_shutdown_function( 'Cheevos\CheevosHooks' );

		self::$shutdownRegistered = true;

		return true;
	}

	/**
	 * Send all the tallied increments up to the service.
	 *
	 * @return void
	 */
	public static function doIncrements() {
		$hookContainer = MediaWikiServices::getInstance()->getHookContainer();
		// Attempt to do it NOW. If we get an error, fall back to the SyncService job.
		try {
			self::$shutdownRan = true;
			foreach ( self::$increments as $globalId => $increment ) {
				$return = Cheevos::increment( $increment );
				unset( self::$increments[$globalId] );
				if ( isset( $return['earned'] ) ) {
					foreach ( $return['earned'] as $achievement ) {
						$achievement = new CheevosAchievement( $achievement );
						self::broadcastAchievement( $achievement, $increment['site_key'], $increment['user_id'] );
						$hookContainer->run( 'AchievementAwarded', [ $achievement, $globalId ] );
					}
				}
			}
		}
		catch ( CheevosException $e ) {
			foreach ( self::$increments as $globalId => $increment ) {
				CheevosIncrementJob::queue( $increment );
				unset( self::$increments[$globalId] );
			}
		}
	}

	/**
	 * Adds achievement display HTML to page output.
	 *
	 * @param CheevosAchievement $achievement CheevosAchievement
	 * @param string $siteKey Site Key
	 * @param int $globalId Global User ID
	 *
	 * @return bool Success
	 */
	public static function broadcastAchievement( CheevosAchievement $achievement, string $siteKey, int $globalId ) {
		$globalId = intval( $globalId );

		if ( empty( $siteKey ) || $globalId < 0 ) {
			return false;
		}

		$targetUser = Cheevos::getUserForServiceUserId( $globalId );

		if ( !$targetUser ) {
			return false;
		}

		$html = TemplateAchievements::achievementBlockPopUp( $achievement, $siteKey, $globalId );

		$broadcast = NotificationBroadcast::newSystemSingle( 'user-interest-achievement-earned', $targetUser, [
			'url' => SpecialPage::getTitleFor( 'Achievements' )->getFullURL(),
			'message' => [
				[
					'user_note',
					$html,
				],
			],
		] );

		if ( $broadcast ) {
			$broadcast->transmit();
		}
	}

	/**
	 * Add additional valid login form error messages.
	 *
	 * @param array &$messages Valid login form error messages.
	 *
	 * @return bool True
	 */
	public static function onLoginFormValidErrorMessages( &$messages ) {
		$messages[] = 'login_to_display_achievements';

		return true;
	}

	/**
	 * Insert achievement page link into the personal URLs.
	 *
	 * @param array &$personalUrls Peronsal URLs array.
	 * @param Title $title Title object for the current page.
	 * @param SkinTemplate $skin SkinTemplate instance that is setting up personal urls.
	 *
	 * @return bool True
	 */
	public static function onPersonalUrls( array &$personalUrls, Title $title, SkinTemplate $skin ) {
		if ( !$skin->getUser()->isAnon() ) {
			$url = Skin::makeSpecialUrl( 'Achievements' );
			$achievements = [
				'achievements' => [
					'text' => wfMessage( 'achievements' )->text(),
					'href' => $url,
					'active' => true,
				],
			];
			HydraCore::array_insert_before_key( $personalUrls, 'mycontris', $achievements );
		}

		return true;
	}

	/**
	 * Add a link to WikiPoints on contribution and edit tool links.
	 *
	 * @param int $userId User ID
	 * @param mixed $userPageTitle Title object for the user's page.
	 * @param array &$tools Array of tools links.
	 *
	 * @return bool True
	 */
	public static function onContributionsToolLinks( $userId, $userPageTitle, &$tools ) {
		if ( !$userId ) {
			return true;
		}

		$user = RequestContext::getMain()->getUser();
		if ( !$user->isAllowed( 'wiki_points_admin' ) ) {
			return true;
		}

		if ( $userPageTitle instanceof Title ) {
			$userName = $userPageTitle->getText();
		} elseif ( $userPageTitle instanceof User ) {
			$userName = $userPageTitle->getName();
		} elseif ( is_string( $userPageTitle ) ) {
			$userName = $userPageTitle;
		}

		$tools[] =
			MediaWikiServices::getInstance()
				->getLinkRenderer()
				->makeKnownLink( SpecialPage::getTitleFor( 'WikiPointsAdmin' ),
					wfMessage( 'sp_contributions_wikipoints_admin' )->escaped(),
					[ 'class' => 'mw-usertoollinks-wikipointsadmin' ], [
						'action' => 'lookup',
						'user' => $userName,
					] );

		return true;
	}

	/**
	 * Registers our function hooks for displaying blocks of user points
	 *
	 * @param Parser &$parser Parser reference
	 *
	 * @return bool True
	 */
	public static function onParserFirstCallInit( Parser &$parser ) {
		$parser->setFunctionHook( 'wikipointsblock', 'Cheevos\Points\PointsDisplay::pointsBlock' );

		return true;
	}

	/**
	 * Define custom magic word variables.
	 *
	 * @param array &$customVariableIds Custom magic word variables.
	 *
	 * @return bool True
	 */
	public static function onMagicWordwgVariableIDs( &$customVariableIds ) {
		$customVariableIds[] = 'numberofcontributors';

		return true;
	}

	/**
	 * Handles custom MAGIC WORDS.
	 *
	 * @param Parser &$parser Parser reference
	 * @param array &$cache Variable Cache
	 * @param string &$magicWord Magic Word
	 * @param string &$value Return Value
	 * @param mixed &$frame Boolean false or PPFrame object.
	 *
	 * @return bool True
	 */
	public static function onParserGetVariableValueSwitch( &$parser, &$cache, &$magicWord, &$value, &$frame ) {
		if ( strtolower( $magicWord ) === 'numberofcontributors' ) {
			$value = self::getTotalContributors();
		}

		return true;
	}

	/**
	 * Get the total number of contributors on the wiki.
	 *
	 * @return int Total Contributors
	 */
	private static function getTotalContributors() {
		$redis = MediaWikiServices::getInstance()->getService( RedisCache::class )->getConnection( 'cache' );

		$redisKey = 'cheevos:contributors:' . CheevosHelper::getSiteKey();
		if ( $redis !== false ) {
			$cache = $redis->get( $redisKey );
			if ( $cache !== false ) {
				return $cache;
			}
		}

		$db = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_PRIMARY );
		$actorQuery = [ 'tables' => [], 'joins' => [] ];
		$userField = 'rev_user';

		$db->select( [ 'revision' ] + $actorQuery['tables'], [ 'count(*)' ], [], __METHOD__, [
			'GROUP BY' => $userField,
			'SQL_CALC_FOUND_ROWS',
		], $actorQuery['joins'] );
		$calcRowsResult = $db->query( 'SELECT FOUND_ROWS() AS rowcount;' );
		$total = $calcRowsResult->fetchRow();
		$total = intval( $total['rowcount'] );
		if ( $redis !== false ) {
			$redis->setEx( $redisKey, 3600, $total );
		}

		return $total;
	}

	/**
	 * Setups and Modifies Database Information
	 *
	 * @return bool True
	 */
	public static function onLoadExtensionSchemaUpdates( $updater ) {
		$extDir = __DIR__;

		if ( CheevosHelper::isCentralWiki() ) {
			$updater->addExtensionUpdate( [
				'addTable',
				'points_comp_report',
				"{$extDir}/install/sql/table_points_comp_report.sql",
				true,
			] );
			$updater->addExtensionUpdate( [
				'addTable',
				'points_comp_report_user',
				"{$extDir}/install/sql/table_points_comp_report_user.sql",
				true,
			] );

			$updater->addExtensionUpdate( [
				'addField',
				'points_comp_report',
				'comp_skipped',
				"{$extDir}/upgrade/sql/points_comp_report/add_comp_skipped.sql",
				true,
			] );
			$updater->addExtensionUpdate( [
				'modifyField',
				'points_comp_report',
				'comp_failed',
				"{$extDir}/upgrade/sql/points_comp_report/change_comp_failed_default_0.sql",
				true,
			] );
			$updater->addExtensionUpdate( [
				'modifyField',
				'points_comp_report',
				'max_points',
				"{$extDir}/upgrade/sql/points_comp_report/change_max_points_null.sql",
				true,
			] );
			$updater->addExtensionUpdate( [
				'addField',
				'points_comp_report_user',
				'comp_skipped',
				"{$extDir}/upgrade/sql/points_comp_report_user/add_comp_skipped.sql",
				true,
			] );
			$updater->addExtensionUpdate( [
				'modifyField',
				'points_comp_report_user',
				'comp_failed',
				"{$extDir}/upgrade/sql/points_comp_report_user/change_comp_failed_default_0.sql",
				true,
			] );
			$updater->addExtensionUpdate( [
				'addField',
				'points_comp_report_user',
				'user_id',
				"{$extDir}/upgrade/sql/points_comp_report_user/add_field_user_id.sql",
				true,
			] );
			$updater->addExtensionUpdate( [
				'addIndex',
				'points_comp_report_user',
				'report_id_user_id',
				"{$extDir}/upgrade/sql/points_comp_report_user/add_index_report_id_user_id.sql",
				true,
			] );
			$updater->addExtensionUpdate( [
				'dropIndex',
				'points_comp_report_user',
				'report_id_global_id',
				"{$extDir}/upgrade/sql/points_comp_report_user/drop_index_report_id_global_id.sql",
				true,
			] );
			$updater->addPostDatabaseUpdateMaintenance( ReplaceGlobalIdWithUserId::class );

			// Point Levels
			$updater->addExtensionUpdate( [
				'addTable',
				'wiki_points_levels',
				"{$extDir}/install/sql/table_wiki_points_levels.sql",
				true,
			] );
		}

		$updater->addExtensionUpdate( [
			'dropTable',
			'achievement',
			$extDir . "/upgrade/sql/drop_table_achievement.sql",
			true,
		] );
		$updater->addExtensionUpdate( [
			'dropTable',
			'achievement_category',
			$extDir . "/upgrade/sql/drop_table_achievement_category.sql",
			true,
		] );
		$updater->addExtensionUpdate( [
			'dropTable',
			'achievement_earned',
			$extDir . "/upgrade/sql/drop_table_achievement_earned.sql",
			true,
		] );
		$updater->addExtensionUpdate( [
			'dropTable',
			'achievement_hook',
			$extDir . "/upgrade/sql/drop_table_achievement_hook.sql",
			true,
		] );
		$updater->addExtensionUpdate( [
			'dropTable',
			'achievement_link',
			$extDir . "/upgrade/sql/drop_table_achievement_link.sql",
			true,
		] );
		$updater->addExtensionUpdate( [
			'dropTable',
			'achievement_site_mega',
			$extDir . "/upgrade/sql/drop_table_achievement_site_mega.sql",
			true,
		] );
		$updater->addExtensionUpdate( [
			'dropTable',
			'dataminer_user_global_totals',
			$extDir . "/upgrade/sql/drop_table_dataminer_user_global_totals.sql",
			true,
		] );
		$updater->addExtensionUpdate( [
			'dropTable',
			'dataminer_user_wiki_periodicals',
			$extDir . "/upgrade/sql/drop_table_dataminer_user_wiki_periodicals.sql",
			true,
		] );
		$updater->addExtensionUpdate( [
			'dropTable',
			'dataminer_user_wiki_totals',
			$extDir . "/upgrade/sql/drop_table_dataminer_user_wiki_totals.sql",
			true,
		] );
		$updater->addExtensionUpdate( [
			'dropTable',
			'display_names',
			$extDir . "/upgrade/sql/drop_table_display_names.sql",
			true,
		] );
		$updater->addExtensionUpdate( [
			'dropTable',
			'wiki_points',
			$extDir . "/upgrade/sql/drop_table_wiki_points.sql",
			true,
		] );
		$updater->addExtensionUpdate( [
			'dropTable',
			'wiki_points_monthly_totals',
			$extDir . "/upgrade/sql/drop_table_wiki_points_monthly_totals.sql",
			true,
		] );
		$updater->addExtensionUpdate( [
			'dropTable',
			'wiki_points_multipliers',
			$extDir . "/upgrade/sql/drop_table_wiki_points_multipliers.sql",
			true,
		] );
		$updater->addExtensionUpdate( [
			'dropTable',
			'wiki_points_multipliers_sites',
			$extDir . "/upgrade/sql/drop_table_wiki_points_multipliers_sites.sql",
			true,
		] );
		$updater->addExtensionUpdate( [
			'dropTable',
			'wiki_points_site_monthly_totals',
			$extDir . "/upgrade/sql/drop_table_wiki_points_site_monthly_totals.sql",
			true,
		] );
		$updater->addExtensionUpdate( [
			'dropTable',
			'wiki_points_site_totals',
			$extDir . "/upgrade/sql/drop_table_wiki_points_site_totals.sql",
			true,
		] );
		$updater->addExtensionUpdate( [
			'dropTable',
			'wiki_points_totals',
			$extDir . "/upgrade/sql/drop_table_wiki_points_totals.sql",
			true,
		] );

		return true;
	}
}
