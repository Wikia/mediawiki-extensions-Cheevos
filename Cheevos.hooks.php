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
 **/

use Cheevos\CheevosAchievement;
use DynamicSettings\Environment;
use Reverb\Notification\NotificationBroadcast;

class CheevosHooks {
	/**
	 * Shutdown Function Registered Already
	 *
	 * @var boolean
	 */
	static private $shutdownRegistered = false;

	/**
	 * Shutdown Function Ran Already
	 *
	 * @var boolean
	 */
	static private $shutdownRan = false;

	/**
	 * Data points to increment on shutdown.
	 *
	 * @var array
	 */
	static private $increments = [];

	/**
	 * Setup anything that needs to be configured before anything else runs.
	 *
	 * @return void
	 */
	public static function onRegistration() {
		global $wgDefaultUserOptions, $wgNamespacesForEditPoints;

		$wgDefaultUserOptions['cheevos-popup-notification'] = 1;

		// Allowed namespaces.
		if (!isset($wgNamespacesForEditPoints) || empty($wgNamespacesForEditPoints)) {
			$wgNamespacesForEditPoints = MWNamespace::getContentNamespaces();
		}
	}

	/**
	 * Undocumented function
	 *
	 * @return void
	 */
	public static function invalidateCache() {
		// this is here for future functionality.
		return \Cheevos\Cheevos::invalidateCache();
	}

	/**
	 * Get site key.
	 *
	 * @return mixed	Site key string or false if empty.
	 */
	private static function getSiteKey() {
		global $dsSiteKey;
		if (!$dsSiteKey || empty($dsSiteKey)) {
			return false;
		}

		return $dsSiteKey;
	}

	/**
	 * Do incrementing for a statistic.
	 *
	 * @param string  $stat  Stat Name
	 * @param integer $delta Stat Delta
	 * @param object  $user  Local User object.
	 * @param array   $edits Array of edit information for article_create or article_edit statistics.
	 *
	 * @return mixed	Array of return status including earned achievements or false on error.
	 */
	public static function increment(string $stat, int $delta, User $user, array $edits = []) {
		$siteKey = self::getSiteKey();
		if ($siteKey === false) {
			return false;
		}

		$lookup = CentralIdLookup::factory();
		$globalId = $lookup->centralIdFromLocalUser($user, CentralIdLookup::AUDIENCE_RAW);

		self::$increments[$globalId]['user_id'] = $globalId;
		self::$increments[$globalId]['user_name'] = $user->getName();
		self::$increments[$globalId]['site_key'] = $siteKey;
		self::$increments[$globalId]['deltas'][] = ['stat' => $stat, 'delta' => $delta];
		self::$increments[$globalId]['timestamp'] = time();
		self::$increments[$globalId]['request_uuid'] = sha1(self::$increments[$globalId]['user_id'] . self::$increments[$globalId]['site_key'] . self::$increments[$globalId]['timestamp'] . random_bytes(4));
		if (!empty($edits)) {
			if (!isset(self::$increments[$globalId]['edits']) || !is_array(self::$increments[$globalId]['edits'])) {
				self::$increments[$globalId]['edits'] = [];
			}
			self::$increments[$globalId]['edits'] = array_merge(self::$increments[$globalId]['edits'], $edits);
		}

		if (self::$shutdownRan) {
			self::doIncrements();
		}

		return true;
	}

	/**
	 * Handle article deletion increment.
	 *
	 * @param WikiPage $article  the article that was deleted.
	 * @param User     $user     the user that deleted the article
	 * @param string   $reason   the reason the article was deleted
	 * @param integer  $id       id of the article that was deleted (added in 1.13)
	 * @param Content  $content  the content of the deleted article, or null in case of an error (added in 1.21)
	 * @param LogEntry $logEntry the log entry used to record the deletion (added in 1.21)
	 *
	 * @return boolean True
	 */
	public static function onArticleDeleteComplete(WikiPage &$article, User &$user, $reason, $id, Content $content = null, LogEntry $logEntry) {
		self::increment('article_delete', 1, $user);
		return true;
	}

	/**
	 * Updates user's points after they've made an edit in a namespace that is listed in the $wgNamespacesForEditPoints array.
	 * This hook will not be called if a null revision is created.
	 *
	 * @param WikiPage $wikiPage  Article
	 * @param Revision $revision  Revision
	 * @param mixed    $baseRevId [Do Not Use, Unreliable] ID of revision this new edit started with.  May also be 0 or false for no previous revision.
	 * @param User     $user      User that performed the action.
	 *
	 * @return boolean True
	 */
	public static function onNewRevisionFromEditComplete(WikiPage $wikiPage, Revision $revision, $baseRevId, User $user) {
		global $wgNamespacesForEditPoints;

		$isBot = $user->isAllowed('bot');

		if (!$revision->getParentId()) {
			self::increment('article_create', 1, $user);
		}

		if ($isBot) {
			self::increment('article_edit_is_bot', 1, $user);
		}
		if ($user->getId()) {
			self::increment('article_edit_is_logged_in', 1, $user);
		} else {
			self::increment('article_edit_is_logged_out', 1, $user);
		}

		$isType = [];
		// Note: Reordering this code will cause differently named statistics.
		if (class_exists('MobileContext')) {
			$mobileContext = MobileContext::singleton();
			if ($mobileContext->shouldDisplayMobileView()) {
				$isType[] = 'is_mobile';
			} else {
				$isType[] = 'is_desktop';
			}
		}

		$context = RequestContext::getMain();
		if ($context->getRequest()->getVal('veaction') === 'edit' || $context->getRequest()->getVal('action') === 'visualeditoredit') {
			$isType[] = 'is_visual';
		} else {
			$isType[] = 'is_source';
		}
		foreach ($isType as $type) {
			self::increment('article_edit_' . $type, 1, $user);
		}
		self::increment('article_edit_' . implode('_', $isType), 1, $user);

		$edits = [];
		if (!$isBot && in_array($wikiPage->getTitle()->getNamespace(), $wgNamespacesForEditPoints)) {
			$parentRevisionId = $revision->getParentId();
			$previousRevision = $parentRevisionId ? Revision::newFromId($parentRevisionId) : null;
			$prevSize = $previousRevision ? $previousRevision->getSize() : 0;
			$sizeDiff = $revision->getSize() - $prevSize;
			$edits[] = [
				'size'			=> $revision->getSize(),
				'size_diff'		=> $sizeDiff,
				'page_id'		=> $wikiPage->getId(),
				'revision_id'	=> $revision->getId()
			];
		}

		self::increment('article_edit', 1, $user, $edits);

		return true;
	}

	/**
	 * Revokes all edits between $revision and $current
	 *
	 * @param WikiPage $wikiPage Article reference, the article edited
	 * @param User     $user     User reference, the user performing the rollback
	 * @param Revision $revision Revision reference, the old revision to become current after the rollback
	 * @param Revision $current  Revision reference, the revision that was current before the rollback
	 *
	 * @return boolean True
	 */
	public static function onArticleRollbackComplete(WikiPage $wikiPage, User $user, Revision $revision, Revision $current) {
		$siteKey = self::getSiteKey();
		if ($siteKey === false) {
			return true;
		}

		$editsToRevoke = [];
		while ($current && $current->getId() != $revision->getId()) {
			$editsToRevoke[] = $current->getId();
			$current = $current->getPrevious();
		}

		try {
			\Cheevos\Cheevos::revokeEditPoints($wikiPage->getId(), $editsToRevoke, $siteKey);
		} catch (\Cheevos\CheevosException $e) {
			// Honey Badger
			wfLogWarning("Cheevos Service is unavailable: " . $e->getMessage());
		}

		return true;
	}

	/**
	 * Handle article merge increment.
	 *
	 * @param Title $targetTitle Source Title
	 * @param Title $destTitle   Destination Title
	 *
	 * @return boolean True
	 */
	public static function onArticleMergeComplete(Title $targetTitle, Title $destTitle) {
		global $wgUser;

		self::increment('article_merge', 1, $wgUser);
		return true;
	}

	/**
	 * Handle article protect increment.
	 *
	 * @param WikiPage $wikiPage Article object that was protected
	 * @param User     $user     User object who did the protection.
	 * @param array    $limit    Protection limits being added.
	 * @param string   $reason   Reason for protect
	 *
	 * @return boolean True
	 */
	public static function onArticleProtectComplete(WikiPage &$wikiPage, User &$user, $limit, $reason) {
		self::increment('article_protect', 1, $user);
		return true;
	}

	/**
	 * Handle article move increment.
	 *
	 * @param Title    $title    Original Title
	 * @param Title    $newTitle New Title
	 * @param User     $user     The User object who did move.
	 * @param integer  $oldid    Old Page ID
	 * @param integer  $newid    New Page ID
	 * @param string   $reason   Reason for protect
	 * @param Revision $revision Revision created by the move.
	 *
	 * @return boolean True
	 */
	public static function onTitleMoveComplete(Title &$title, Title &$newTitle, User $user, $oldid, $newid, $reason, Revision $revision) {
		self::increment('article_move', 1, $user);
		return true;
	}

	/**
	 * Handle article protect increment.
	 *
	 * @param Block $block Block
	 * @param User  $user  User object of who performed the block.
	 *
	 * @return boolean True
	 */
	public static function onBlockIpComplete(Block $block, User $user) {
		self::increment('admin_block_ip', 1, $user);
		return true;
	}

	/**
	 * Handle CurseProfile comment increment.
	 *
	 * @param User    $fromUser    User making the comment.
	 * @param User    $toUser      User of the profile being commented on.
	 * @param integer $inReplyTo   Parent ID of the comment.
	 * @param string  $commentText The comment text.
	 *
	 * @return boolean True
	 */
	public static function onCurseProfileAddComment(User $fromUser, User $toUser, $inReplyTo, $commentText) {
		self::increment('curse_profile_comment', 1, $fromUser);
		return true;
	}

	/**
	 * Handle CurseProfile comment reply increment.
	 *
	 * @param User    $fromUser    User making the comment.
	 * @param User    $toUser      User of the profile being commented on.
	 * @param integer $inReplyTo   Parent ID of the comment.
	 * @param string  $commentText The comment text.
	 *
	 * @return boolean True
	 */
	public static function onCurseProfileAddCommentReply(User $fromUser, User $toUser, $inReplyTo, $commentText) {
		self::increment('curse_profile_comment_reply', 1, $fromUser);
		return true;
	}

	/**
	 * Handle CurseProfile friend addition increment.
	 *
	 * @param User $fromUser User object of the user requesting to add a friend.
	 * @param User $toUser   User object of the user being requested as a friend.
	 *
	 * @return boolean True
	 */
	public static function onCurseProfileAddFriend(User $fromUser, User $toUser) {
		self::increment('curse_profile_add_friend', 1, $fromUser);
		return true;
	}

	/**
	 * Handle CurseProfile friend accept increment.
	 *
	 * @param User $fromUser User object of the user accepting a friend request.
	 * @param User $toUser   User object of the user that initiated the friend request.
	 *
	 * @return boolean True
	 */
	public static function onCurseProfileAcceptFriend(User $fromUser, User $toUser) {
		self::increment('curse_profile_accept_friend', 1, $fromUser);
		return true;
	}

	/**
	 * Handle CurseProfile profile edited.
	 *
	 * @param User   $user  User profile edited.
	 * @param string $field Field being edited.
	 * @param string $value Field Value
	 *
	 * @return boolean True
	 */
	public static function onCurseProfileEdited(User $user, $field, $value) {
		self::increment('curse_profile_edit', 1, $user);
		if (!empty($value)) {
			switch ($field) {
				case 'profile-favwiki':
					self::increment('curse_profile_edit_fav_wiki', 1, $user);
					break;
				case 'profile-link-facebook':
					self::increment('curse_profile_edit_link_facebook', 1, $user);
					break;
				case 'profile-link-psn':
					self::increment('curse_profile_edit_link_psn', 1, $user);
					break;
				case 'profile-link-steam':
					self::increment('curse_profile_edit_link_steam', 1, $user);
					break;
				case 'profile-link-reddit':
					self::increment('curse_profile_edit_link_reddit', 1, $user);
					break;
				case 'profile-link-twitch':
					self::increment('curse_profile_edit_link_twitch', 1, $user);
					break;
				case 'profile-link-twitter':
					self::increment('curse_profile_edit_link_twitter', 1, $user);
					break;
				case 'profile-link-vk':
					self::increment('curse_profile_edit_link_vk', 1, $user);
					break;
				case 'profile-link-xbl':
					self::increment('curse_profile_edit_link_xbl', 1, $user);
					break;
			}
		}
		return true;
	}

	/**
	 * Handle email sent increment.
	 *
	 * @param MailAddress $address Address of receiving user
	 * @param MailAddress $from    Address of sending user
	 * @param string      $subject Subject of the mail
	 * @param string      $text    Text of the mail
	 *
	 * @return boolean True
	 */
	public static function onEmailUserComplete(MailAddress $address, MailAddress $from, $subject, $text) {
		global $wgUser;

		self::increment('send_email', 1, $wgUser);
		return true;
	}

	/**
	 * Handle mark patrolled increment.
	 *
	 * @param integer $rcid      Recent Change Primary ID that was marked as patrolled.
	 * @param User    $user      User that marked the change as patrolled.
	 * @param boolean $automatic Automatically Patrolled
	 *
	 * @return boolean True
	 */
	public static function onMarkPatrolledComplete(int $rcid, User $user, bool $automatic) {
		self::increment('admin_patrol', 1, $user);
		return true;
	}

	/**
	 * Handle upload increment.
	 *
	 * @param object $image UploadBase or child of UploadBase
	 *
	 * @return boolean True
	 */
	public static function onUploadComplete(&$image) {
		global $wgUser;

		self::increment('file_upload', 1, $wgUser);
		return true;
	}

	/**
	 * Handle watch article increment.
	 *
	 * @param User     $user    User watching the article.
	 * @param WikiPage $article	Article being watched by the user.
	 *
	 * @return boolean True
	 */
	public static function onWatchArticleComplete(User $user, WikiPage $article) {
		self::increment('article_watch', 1, $user);
		return true;
	}

	/**
	 * Handle when a local user is created in the database.
	 *
	 * @param User    $user        User created.
	 * @param boolean $autoCreated Automatic Creation
	 *
	 * @return boolean True
	 */
	public static function onLocalUserCreated(User $user, bool $autoCreated) {
		self::increment('account_create', 1, $user);
		return true;
	}

	/**
	 * Handles awarding WikiPoints achievements.
	 *
	 * @param integer $editId          Revision Edit ID
	 * @param integer $userId          Local User ID
	 * @param integer $articleId       Article ID
	 * @param integer $score           Score for the edit, not the overall score.
	 * @param string  $calculationInfo JSON of Calculation Information
	 * @param string  $reason          [Optional] Stated reason for these points.
	 *
	 * @return boolean True
	 */
	public static function onWikiPointsSave(int $editId, int $userId, int $articleId, int $score, string $calculationInfo, string $reason = '') {
		global $wgUser;

		if (($score > 0 || $score < 0) && $wgUser->getId() == $userId && $userId > 0) {
			self::increment('wiki_points', intval($score), $wgUser);
		}

		return true;
	}

	/**
	 * Registers shutdown function to do increments.
	 *
	 * @param ApiMain $processor ApiMain
	 *
	 * @return boolean True
	 */
	public static function onApiBeforeMain(ApiMain &$processor) {
		if ('MW_NO_SESSION' === 1 || 'MW_NO_SESSION' === 'warn' || PHP_SAPI === 'cli' || self::$shutdownRegistered) {
			return true;
		}

		register_shutdown_function('CheevosHooks::doIncrements');

		self::$shutdownRegistered = true;

		return true;
	}

	/**
	 * Registers shutdown function to do increments.
	 *
	 * @param Title      $title     Title
	 * @param Article    $article   Article
	 * @param OutputPage $output	Output
	 * @param User       $user      User
	 * @param WebRequest $request	WebRequest
	 * @param MediaWiki  $mediaWiki Mediawiki
	 *
	 * @return boolean True
	 */
	public static function onBeforeInitialize(Title &$title, &$article, OutputPage &$output, User &$user, WebRequest $request, MediaWiki $mediaWiki) {
		if ('MW_NO_SESSION' === 'warn' || PHP_SAPI === 'cli' || self::$shutdownRegistered) {
			return true;
		}

		global $wgUser;
		// Do not track anonymous users for visits.  The Cheevos database can not handle it.
		if (!defined('MW_API') && $wgUser->getId() > 0) {
			self::increment('visit', 1, $wgUser);
		}

		register_shutdown_function('CheevosHooks::doIncrements');

		self::$shutdownRegistered = true;

		return true;
	}

	/**
	 * Send all the tallied increments up to the service.
	 *
	 * @return void
	 */
	public static function doIncrements() {
		// Attempt to do it NOW. If we get an error, fall back to the SyncService job.
		try {
			self::$shutdownRan = true;
			foreach (self::$increments as $globalId => $increment) {
				$return = \Cheevos\Cheevos::increment($increment);
				unset(self::$increments[$globalId]);
				if (isset($return['earned'])) {
					foreach ($return['earned'] as $achievement) {
						$achievement = new \Cheevos\CheevosAchievement($achievement);
						self::broadcastAchievement($achievement, $increment['site_key'], $increment['user_id']);
						Hooks::run('AchievementAwarded', [$achievement, $globalId]);
					}
				}
			}
		} catch (\Cheevos\CheevosException $e) {
			foreach (self::$increments as $globalId => $increment) {
				\Cheevos\Job\CheevosIncrementJob::queue($increment);
				unset(self::$increments[$globalId]);
			}
		}
	}

	/**
	 * Adds achievement display HTML to page output.
	 *
	 * @param CheevosAchievement $achievement CheevosAchievement
	 * @param string             $siteKey     Site Key
	 * @param integer            $globalId    Global User ID
	 *
	 * @return boolean	Success
	 */
	public static function broadcastAchievement(CheevosAchievement $achievement, string $siteKey, int $globalId) {
		$globalId = intval($globalId);

		if (empty($siteKey) || $globalId < 0) {
			return false;
		}

		$lookup = CentralIdLookup::factory();
		$targetUser = $lookup->localUserFromCentralId($globalId);

		if (!$targetUser) {
			return false;
		}

		$html = TemplateAchievements::achievementBlockPopUp($achievement, $siteKey, $globalId);

		$broadcast = NotificationBroadcast::newSystemSingle(
			'user-interest-achievement-earned',
			$targetUser,
			[
				'url' => SpecialPage::getTitleFor('Special:Achievements')->getFullURL(),
				'message' => [
					[
						'user_note',
						$html
					]
				]
			]
		);

		if ($broadcast) {
			$broadcast->transmit();
		}
	}

	/**
	 * Add additional valid login form error messages.
	 *
	 * @param array	$messages Valid login form error messages.
	 *
	 * @return boolean True
	 */
	public static function onLoginFormValidErrorMessages(&$messages) {
		$messages[] = 'login_to_display_achievements';

		return true;
	}

	/**
	 * Add option to disable pop-up notifications.
	 *
	 * @param User  $user        User
	 * @param array $preferences Default user preferences.
	 *
	 * @return boolean True
	 */
	public static function onGetPreferences(User $user, array &$preferences) {
		$preferences['cheevos-popup-notification'] = [
			'type' => 'toggle',
			'label-message' => 'cheevos-popup-notification', // a system message
			'section' => 'reverb/cheevos-notification'
		];

		return true;
	}

	/**
	 * Insert achievement page link into the personal URLs.
	 *
	 * @param array        $personalUrls Peronsal URLs array.
	 * @param Title        $title        Title object for the current page.
	 * @param SkinTemplate $skin         SkinTemplate instance that is setting up personal urls.
	 *
	 * @return boolean True
	 */
	public static function onPersonalUrls(array &$personalUrls, Title $title, SkinTemplate $skin) {
		if (!$skin->getUser()->isAnon()) {
			$url = Skin::makeSpecialUrl('Achievements');
			$achievements = [
				'achievements'	=> [
					'text'		=> wfMessage('achievements')->text(),
					'href'		=> $url,
					'active'	=> true
				]
			];
			HydraCore::array_insert_before_key($personalUrls, 'mycontris', $achievements);
		}

		return true;
	}

	/**
	 * Add a link to WikiPoints on contribution and edit tool links.
	 *
	 * @param integer $userId        User ID
	 * @param object  $userPageTitle Title object for the user's page.
	 * @param array   $tools         Array of tools links.
	 *
	 * @return boolean True
	 */
	public static function onContributionsToolLinks($userId, $userPageTitle, &$tools) {
		global $wgUser;

		if (!$userId) {
			return true;
		}

		if (!$wgUser->isAllowed('wiki_points_admin')) {
			return true;
		}

		if ($userPageTitle instanceof Title) {
			$userName = $userPageTitle->getText();
		} elseif ($userPageTitle instanceof User) {
			$userName = $userPageTitle->getName();
		} elseif (is_string($userPageTitle)) {
			$userName = $userPageTitle;
		}

		$tools[] = Linker::linkKnown(
			SpecialPage::getTitleFor('WikiPointsAdmin'),
			wfMessage('sp_contributions_wikipoints_admin')->escaped(),
			['class' => 'mw-usertoollinks-wikipointsadmin'],
			[
				'action'	=> 'lookup',
				'user'		=> $userName
			]
		);

		return true;
	}

	/**
	 * Registers our function hooks for displaying blocks of user points
	 *
	 * @param Parser $parser Parser reference
	 *
	 * @return boolean True
	 */
	public static function onParserFirstCallInit(Parser &$parser) {
		$parser->setFunctionHook('wikipointsblock', 'Cheevos\Points\PointsDisplay::pointsBlock');
		return true;
	}

	/**
	 * Define custom magic word variables.
	 *
	 * @param array $customVariableIds Custom magic word variables.
	 *
	 * @return boolean True
	 */
	public static function onMagicWordwgVariableIDs(&$customVariableIds) {
		$customVariableIds[] = 'numberofcontributors';
		return true;
	}

	/**
	 * Handles custom MAGIC WORDS.
	 *
	 * @param object $parser    Parser reference
	 * @param array  $cache     Variable Cache
	 * @param string $magicWord Magic Word
	 * @param string $value     Return Value
	 * @param mixed  $frame     Boolean false or PPFrame object.
	 *
	 * @return boolean True
	 */
	public static function onParserGetVariableValueSwitch(&$parser, &$cache, &$magicWord, &$value, &$frame) {
		if (strtolower($magicWord) === 'numberofcontributors') {
			$value = self::getTotalContributors();
		}
		return true;
	}

	/**
	 * Get the total number of contributors on the wiki.
	 *
	 * @return integer	Total Contributors
	 */
	private static function getTotalContributors() {
		$redis = RedisCache::getClient('cache');

		$redisKey = 'cheevos:contributors:' . self::getSiteKey();
		if ($redis !== false) {
			$cache = $redis->get($redisKey);
			if ($cache !== false) {
				return $cache;
			}
		}

		$db = wfGetDB(DB_MASTER);
		$result = $db->select(
			['revision'],
			['count(*)'],
			[],
			__METHOD__,
			[
				'GROUP BY'	=> 'rev_user',
				'SQL_CALC_FOUND_ROWS'
			]
		);
		$calcRowsResult = $db->query('SELECT FOUND_ROWS() AS rowcount;');
		$total = $db->fetchRow($calcRowsResult);
		$total = intval($total['rowcount']);
		if ($redis !== false) {
			$redis->setEx($redisKey, 3600, $total);
		}
		return $total;
	}

	/**
	 * Setups and Modifies Database Information
	 *
	 * @param object $updater DatabaseUpdater Object
	 *
	 * @return boolean True
	 */
	public static function onLoadExtensionSchemaUpdates($updater) {
		$extDir = __DIR__;

		if (Environment::isMasterWiki()) {
			$updater->addExtensionUpdate(['addTable', 'points_comp_report', "{$extDir}/install/sql/table_points_comp_report.sql", true]);
			$updater->addExtensionUpdate(['addTable', 'points_comp_report_user', "{$extDir}/install/sql/table_points_comp_report_user.sql", true]);

			$updater->addExtensionUpdate(['addField', 'points_comp_report', 'comp_skipped', "{$extDir}/upgrade/sql/points_comp_report/add_comp_skipped.sql", true]);
			$updater->addExtensionUpdate(['modifyField', 'points_comp_report', 'comp_failed', "{$extDir}/upgrade/sql/points_comp_report/change_comp_failed_default_0.sql", true]);
			$updater->addExtensionUpdate(['modifyField', 'points_comp_report', 'max_points', "{$extDir}/upgrade/sql/points_comp_report/change_max_points_null.sql", true]);
			$updater->addExtensionUpdate(['addField', 'points_comp_report_user', 'comp_skipped', "{$extDir}/upgrade/sql/points_comp_report_user/add_comp_skipped.sql", true]);
			$updater->addExtensionUpdate(['modifyField', 'points_comp_report_user', 'comp_failed', "{$extDir}/upgrade/sql/points_comp_report_user/change_comp_failed_default_0.sql", true]);

			// Point Levels
			$updater->addExtensionUpdate(['addTable', 'wiki_points_levels', "{$extDir}/install/sql/table_wiki_points_levels.sql", true]);
		}

		$updater->addExtensionUpdate(['dropTable', 'achievement', $extDir . "/upgrade/sql/drop_table_achievement.sql", true]);
		$updater->addExtensionUpdate(['dropTable', 'achievement_category', $extDir . "/upgrade/sql/drop_table_achievement_category.sql", true]);
		$updater->addExtensionUpdate(['dropTable', 'achievement_earned', $extDir . "/upgrade/sql/drop_table_achievement_earned.sql", true]);
		$updater->addExtensionUpdate(['dropTable', 'achievement_hook', $extDir . "/upgrade/sql/drop_table_achievement_hook.sql", true]);
		$updater->addExtensionUpdate(['dropTable', 'achievement_link', $extDir . "/upgrade/sql/drop_table_achievement_link.sql", true]);
		$updater->addExtensionUpdate(['dropTable', 'achievement_site_mega', $extDir . "/upgrade/sql/drop_table_achievement_site_mega.sql", true]);
		$updater->addExtensionUpdate(['dropTable', 'dataminer_user_global_totals', $extDir . "/upgrade/sql/drop_table_dataminer_user_global_totals.sql", true]);
		$updater->addExtensionUpdate(['dropTable', 'dataminer_user_wiki_periodicals', $extDir . "/upgrade/sql/drop_table_dataminer_user_wiki_periodicals.sql", true]);
		$updater->addExtensionUpdate(['dropTable', 'dataminer_user_wiki_totals', $extDir . "/upgrade/sql/drop_table_dataminer_user_wiki_totals.sql", true]);
		$updater->addExtensionUpdate(['dropTable', 'display_names', $extDir . "/upgrade/sql/drop_table_display_names.sql", true]);
		$updater->addExtensionUpdate(['dropTable', 'wiki_points', $extDir . "/upgrade/sql/drop_table_wiki_points.sql", true]);
		$updater->addExtensionUpdate(['dropTable', 'wiki_points_monthly_totals', $extDir . "/upgrade/sql/drop_table_wiki_points_monthly_totals.sql", true]);
		$updater->addExtensionUpdate(['dropTable', 'wiki_points_multipliers', $extDir . "/upgrade/sql/drop_table_wiki_points_multipliers.sql", true]);
		$updater->addExtensionUpdate(['dropTable', 'wiki_points_multipliers_sites', $extDir . "/upgrade/sql/drop_table_wiki_points_multipliers_sites.sql", true]);
		$updater->addExtensionUpdate(['dropTable', 'wiki_points_site_monthly_totals', $extDir . "/upgrade/sql/drop_table_wiki_points_site_monthly_totals.sql", true]);
		$updater->addExtensionUpdate(['dropTable', 'wiki_points_site_totals', $extDir . "/upgrade/sql/drop_table_wiki_points_site_totals.sql", true]);
		$updater->addExtensionUpdate(['dropTable', 'wiki_points_totals', $extDir . "/upgrade/sql/drop_table_wiki_points_totals.sql", true]);

		return true;
	}
}
