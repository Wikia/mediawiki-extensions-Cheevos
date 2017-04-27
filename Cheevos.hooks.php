<?php
/**
 * Cheevos
 * Cheevos Hooks
 *
 * @author		Wiki Platform Team
 * @copyright	(c) 2017 Curse Inc.
 * @license		All Rights Reserved
 * @package		Cheevos
 * @link		http://www.curse.com/
 *
 **/

class CheevosHooks {
	/**
	 * Shutdown Function Registered Already
	 *
	 * @var		boolean
	 */
	static private $shutdownRegistered = false;

	/**
	 * Shutdown Function Ran Already
	 *
	 * @var		boolean
	 */
	static private $shutdownRan = false;

	/**
	 * Data points to increment on shutdown.
	 *
	 * @var		array
	 */
	static private $increments = [];

	/**
	 * Setup anything that needs to be configured before anything else runs.
	 *
	 * @access	public
	 * @return	void
	 */
	static public function onRegistration() {
		global $wgDefaultUserOptions, $extSyncServices;

		$wgDefaultUserOptions['cheevos-popup-notification'] = 1;

		if (defined('MASTER_WIKI') && MASTER_WIKI === true) {
			$extSyncServices[] = 'CheevosIncrementJob';
		}
	}

	/**
	 * Undocumented function
	 *
	 * @return void
	 */
	static public function invalidateCache() {
		// this is here for future functionality.
		return \Cheevos\Cheevos::invalidateCache();
	}

	/**
	 * Get site key.
	 *
	 * @access	private
	 * @return	mixed	Site key string or false if empty.
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
	 * @access	public
	 * @param	string	Stat Name
	 * @param	integer	Stat Delta
	 * @param	mixed	Local User object or global ID having a stat incremented.
	 * @param	array	Array of edit information for article_create or article_edit statistics.
	 * @return	mixed	Array of return status including earned achievements or false on error.
	 */
	public static function increment($stat, $delta, $user = null, $edits = []) {
		$siteKey = self::getSiteKey();
		if ($siteKey === false) {
			return false;
		}

		if (!$user) {
			global $wgUser;
			$user = $wgUser;
		}

		if (is_numeric($user)) {
			$globalId = intval($user);
		} else {
			$lookup = CentralIdLookup::factory();
			$globalId = $lookup->centralIdFromLocalUser($user, CentralIdLookup::AUDIENCE_RAW);
		}

		if (!$globalId) {
			return false;
		}

		self::$increments[$globalId]['user_id'] = $globalId;
		self::$increments[$globalId]['site_key'] = $siteKey;
		self::$increments[$globalId]['deltas'][] = ['stat' => $stat, 'delta' => $delta];
		self::$increments[$globalId]['timestamp'] = time();
		self::$increments[$globalId]['request_uuid'] = sha1(self::$increments[$globalId]['user_id'].self::$increments[$globalId]['site_key'].self::$increments[$globalId]['timestamp'].random_bytes(4));
		if (!empty($edits)) {
			if (!is_array(self::$increments[$globalId]['edits'])) {
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
	 * @access	public
	 * @param	object	$article: the article that was deleted.
	 * @param	object	$user: the user that deleted the article
	 * @param	string	$reason: the reason the article was deleted
	 * @param	integer	$id: id of the article that was deleted (added in 1.13)
	 * @param	object	$content: the content of the deleted article, or null in case of an error (added in 1.21)
	 * @param	object	$logEntry: the log entry used to record the deletion (added in 1.21)
	 * @return	True
	 */
	static public function onArticleDeleteComplete(WikiPage &$article, User &$user, $reason, $id, Content $content = null, LogEntry $logEntry) {
		self::increment('article_delete', 1, $user);
		return true;
	}

	/**
	 * Updates user's points after they've made an edit in a namespace that is listed in the $wgNamespacesForEditPoints array.
	 * This hook will not be called if a null revision is created.
	 *
	 * @param	object	Article
	 * @param	object	Revision
	 * @param	mixed	ID of revision this new edit started with.  May also be 0 or false for no prevision revision.
	 * @param	object	User that performed the action.
	 * @return	boolean	true
	 */
	static public function onNewRevisionFromEditComplete(WikiPage $wikiPage, Revision $revision, $baseRevId, User $user) {
		global $wgNamespacesForEditPoints;

		if (!$user->getId()) {
			//We do not gather statistics for logged out users.
			return true;
		}

		$isBot = $user->isAllowed('bot');

		if (!$baseRevId) {
			self::increment('article_create', 1, $user);
		}

		$edits = [];
		if (!$isBot && in_array($wikiPage->getTitle()->getNamespace(), $wgNamespacesForEditPoints)) {
			$prevSize = $revision->getPrevious() ? $revision->getPrevious()->getSize() : 0;
			$sizeDiff = $revision->getSize() - $prevSize;
			$edits[] = [
				'size'			=> $revision->getSize(),
				'size_diff'		=> $sizeDiff,
				'page_id'		=> $wikiPage->getId(),
				'revision_id'	=> $revision->getId(),
				'page_name'		=> $wikiPage->getTitle()->getFullText()
			];
		}

		self::increment('article_edit', 1, $user, $edits);

		return true;
	}

	/**
	 * Revokes all edits between $revision and $current
	 *
	 * @param	object	Article reference, the article edited
	 * @param	object	User reference, the user performing the rollback
	 * @param	object	Revision reference, the old revision to become current after the rollback
	 * @param	object	Revision reference, the revision that was current before the rollback
	 * @return	boolean	true
	 */
	static public function onArticleRollbackComplete(WikiPage $wikiPage, $user, Revision $revision, Revision $current) {
		$editsToRevoke = [];
		while ($current && $current->getId() != $revision->getId()) {
			$editsToRevoke[] = $current->getId();
			$current = $current->getPrevious();
		}
		$edits[] = [
			'page_id'		=> $wikiPage->getId(),
			'revision_id'	=> $editsToRevoke
		];
		EditPoints::revoke($editsToRevoke);
		return true;
	}

	/**
	 * Handle article merge increment.
	 *
	 * @access	public
	 * @param	object	Source Title
	 * @param	object	Destination Title
	 * @return	True
	 */
	static public function onArticleMergeComplete(Article $targetTitle, Article $destTitle) {
		self::increment('article_merge', 1);
		return true;
	}

	/**
	 * Handle article protect increment.
	 *
	 * @access	public
	 * @param	object	Article object that was protected
	 * @param	object	User object who did the protection.
	 * @param	array	Protection limits being added.
	 * @param	string	$reason: Reason for protect
	 * @return	True
	 */
	static public function onArticleProtectComplete(WikiPage &$wikiPage, User &$user, $limit, $reason) {
		self::increment('article_protect', 1, $user);
		return true;
	}

	/**
	 * Handle article move increment.
	 *
	 * @access	public
	 * @param	object	Original Title
	 * @param	object	New Title
	 * @param	object	The User object who did move.
	 * @param	integer	Old Page ID
	 * @param	integer	New Page ID
	 * @param	string	$reason: Reason for protect
	 * @param	object	Revision created by the move.
	 * @return	True
	 */
	static public function onTitleMoveComplete(Title &$title, Title &$newTitle, User $user, $oldid, $newid, $reason, Revision $revision) {
		self::increment('article_move', 1, $user);
		return true;
	}

	/**
	 * Handle article protect increment.
	 *
	 * @access	public
	 * @param	object	Block
	 * @param	object	User object of who performed the block.
	 * @return	True
	 */
	static public function onBlockIpComplete(Block $block, User $user) {
		self::increment('admin_block_ip', 1, $user);
		return true;
	}

	/**
	 * Handle CurseProfile comment increment.
	 *
	 * @access	public
	 * @param	object	User making the comment.
	 * @param	object	User of the profile being commented on.
	 * @param	integer	Parent ID of the comment.
	 * @param	string	The comment text.
	 * @return	True
	 */
	static public function onCurseProfileAddComment($fromUser, $userId, $inReplyTo, $commentText) {
		self::increment('curse_profile_comment', 1, $fromUser);
		return true;
	}

	/**
	 * Handle CurseProfile friend addition increment.
	 *
	 * @access	public
	 * @param	integer	Global ID of the user adding a friend.
	 * @param	integer	Global ID of the friend being added.
	 * @return	True
	 */
	static public function onCurseProfileAddFriend($fromGlobalId, $toGlobalId) {
		self::increment('curse_profile_add_friend', 1, $fromGlobalId);
		return true;
	}

	/**
	 * Handle CurseProfile friend addition increment.
	 *
	 * @access	public
	 * @param	integer	Global ID of the user adding a friend.
	 * @param	integer	Global ID of the friend being added.
	 * @return	True
	 */
	static public function onCurseProfileAcceptFriend($fromGlobalId, $toGlobalId) {
		self::increment('curse_profile_accept_friend', 1, $fromGlobalId);
		return true;
	}

	/**
	 * Handle CurseProfile profile edited.
	 *
	 * @access	public
	 * @param	object	User profile edited.
	 * @param	string	Field being edited.
	 * @param	string	Field Value
	 * @param	string	The comment text.
	 * @return	True
	 */
	static public function onCurseProfileEdited($user, $field, $value) {
		self::increment('curse_profile_edit', 1, $user);
		if (!empty($value)) {
			switch ($field) {
				case 'profile-favwiki':
					self::increment('curse_profile_edit_fav_wiki', 1, $user);
					break;
				case 'profile-link-xbl':
					self::increment('curse_profile_edit_link_xbl', 1, $user);
					break;
				case 'profile-link-psn':
					self::increment('curse_profile_edit_link_psn', 1, $user);
					break;
				case 'profile-link-steam':
					self::increment('curse_profile_edit_link_steam', 1, $user);
					break;
				case 'profile-link-facebook':
					self::increment('curse_profile_edit_link_facebook', 1, $user);
					break;
				case 'profile-link-twitter':
					self::increment('curse_profile_edit_link_twitter', 1, $user);
					break;
				case 'profile-link-reddit':
					self::increment('curse_profile_edit_link_reddit', 1, $user);
					break;
			}
		}
		return true;
	}

	/**
	 * Handle email sent increment.
	 *
	 * @access	public
	 * @param	object	MailAddress $to: address of receiving user
	 * @param	object	MailAddress $from: address of sending user
	 * @param	string	$subject: subject of the mail
	 * @param	string	$text: text of the mail
	 * @return	True
	 */
	static public function onEmailUserComplete(&$address, &$from, &$subject, &$text) {
		self::increment('send_email', 1);
		return true;
	}

	/**
	 * Handle mark patrolled increment.
	 *
	 * @access	public
	 * @param	integer	Recent Change Primary ID that was marked as patrolled.
	 * @param	object	User that marked the change as patrolled.
	 * @param	boolean	Automatically Patrolled
	 * @return	True
	 */
	static public function onMarkPatrolledComplete($rcid, $user, $automatic) {
		self::increment('admin_patrol', 1, $user);
		return true;
	}

	/**
	 * Handle upload increment.
	 *
	 * @access	public
	 * @param	object	UploadBase or child of UploadBase
	 * @return	True
	 */
	static public function onUploadComplete(&$image) {
		self::increment('file_upload', 1);
		return true;
	}

	/**
	 * Handle watch article increment.
	 *
	 * @access	public
	 * @param	object	User watching the article.
	 * @param	object	Article being watched by the user.
	 * @return	True
	 */
	static public function onWatchArticleComplete($user, $article) {
		self::increment('article_watch', 1, $user);
		return true;
	}

	/**
	 * Handle when a local user is created in the database.
	 *
	 * @access	public
	 * @param	object	User created.
	 * @param	boolean	Automatic Creation
	 * @return	True
	 */
	static public function onLocalUserCreated($user, $autoCreated) {
		self::increment('account_create', 1, $user);
		return true;
	}

	/**
	 * Handles awarding WikiPoints achievements.
	 *
	 * @access	public
	 * @param	integer	Revision Edit ID
	 * @param	integer	Local User ID
	 * @param	integer	Article ID
	 * @param	integer	Score for the edit, not the overall score.
	 * @param	string	JSON of Calculation Information
	 * @param	string	[Optional] Stated reason for these points.
	 * @return	boolean	True
	 */
	static public function onWikiPointsSave($editId, $userId, $articleId, $score, $calculationInfo, $reason = '') {
		global $wgUser;

		if (($score > 0 || $score < 0) && $wgUser->getId() == $userId && $userId > 0) {
			self::increment('wiki_points', intval($score), $wgUser);
		}

		return true;
	}

	/**
	 * Registers shutdown function to do increments.
	 *
	 * @access	public
	 * @param	object	ApiMain
	 * @return	boolean	True
	 */
	static public function onApiBeforeMain(&$processor) {
		if ('MW_NO_SESSION' === 'warn' || PHP_SAPI === 'cli' || self::$shutdownRegistered) {
			return true;
		}

		register_shutdown_function('CheevosHooks::doIncrements');

		self::$shutdownRegistered = true;

		return true;
	}

	/**
	 * Registers shutdown function to do increments.
	 *
	 * @access	public
	 * @param	object	Title
	 * @param	object	Article
	 * @param	object	Output
	 * @param	object	User
	 * @param	object	WebRequest
	 * @param	object	Mediawiki
	 * @return	boolean	True
	 */
	static public function onBeforeInitialize(&$title, &$article, &$output, &$user, $request, $mediaWiki) {
		if ('MW_NO_SESSION' === 'warn' || PHP_SAPI === 'cli' || self::$shutdownRegistered) {
			return true;
		}

		global $wgUser;
		if (!defined('MW_API')) {
			self::increment('visit', 1, $wgUser);
		}

		register_shutdown_function('CheevosHooks::doIncrements');

		self::$shutdownRegistered = true;

		return true;
	}

	/**
	 * Send all the tallied increments up to the service.
	 *
	 * @access	public
	 * @return	void
	 */
	static public function doIncrements() {
		//Attempt to do it NOW. If we get an error, fall back to the SyncService job.
		try {
			self::$shutdownRan = true;
			foreach (self::$increments as $globalId => $increment) {
				$return = \Cheevos\Cheevos::increment($increment);
				unset(self::$increments[$globalId]);
				if (isset($return['earned'])) {
					foreach ($return['earned'] as $achievement) {
						$achievement = new \Cheevos\CheevosAchievement($achievement);
						\CheevosHooks::displayAchievement($achievement, $increment['site_key'], $increment['user_id']);
						Hooks::run('AchievementAwarded', [$achievement, $globalId]);
					}
				}
			}
		} catch (\Cheevos\CheevosException $e) {
			foreach (self::$increments as $globalId => $increment) {
				\Cheevos\CheevosIncrementJob::queue($increment);
				unset(self::$increments[$globalId]);
			}
		}
	}

	/**
	 * Handle actions when an achievement is awarded.
	 *
	 * @access	public
	 * @param	object	\Cheevos\CheevosAchievement
	 * @param	object	Global User ID
	 * @return	boolean	True
	 */
	static public function onAchievementAwarded($achievement, $globalId) {
		global $dsSiteKey;

		if ($achievement === false || $globalId < 1) {
			return true;
		}

		if (class_exists('\EditPoints') && $achievement->getPoints() > 0) {
			$points = EditPoints::achievementEarned($globalId, $achievement->getPoints());
			if ($points->save()) {
				$points->updatePointTotals();
			}
		}

		return true;
	}

	/**
	 * Handle actions when an achievement or a mega achievement is unawarded.
	 *
	 * @access	public
	 * @param	object	\Cheevos\CheevosAchievement
	 * @param	object	Global User ID
	 * @return	boolean	True
	 */
	static public function onAchievementUnawarded($achievement, $globalId) {
		if ($achievement === false || $globalId < 1) {
			return true;
		}

		if (class_exists('\EditPoints') && $achievement->getPoints() > 0) {
			$points = EditPoints::achievementRevoked($globalId, ($achievement->getPoints() * -1));
			if ($points->save()) {
				$points->updatePointTotals();
			}
		}

		return true;
	}

	/**
	 * Adds achievement display HTML to page output.
	 *
	 * @access	public
	 * @param	object	Achievement
	 * @param	string	Site Key
	 * @param	integer	Global User ID
	 * @return	boolean	Success
	 */
	static public function displayAchievement($achievement, $siteKey, $globalId) {
		$globalId = intval($globalId);

		if (empty($siteKey) || $globalId < 0) {
			return false;
		}

		$templates = new TemplateAchievements;
		$redis = RedisCache::getClient('cache');

		if ($redis === false) {
			return false;
		}

		$html = $templates->achievementBlockPopUp($achievement, $siteKey, $globalId);

		try {
			//Using a global key.
			$redisKey = 'cheevos:display:'.$globalId;
			$redis->hSet($redisKey, $siteKey."-".$achievement->getId(), $html);
			$redis->expire($redisKey, 3600);
			return true;
		} catch (RedisException $e) {
			wfDebug(__METHOD__.": Caught RedisException - ".$e->getMessage());
			return false;
		}
	}

	/**
	 * Used to shoved displayed achievements into the page for Javascript to handle.
	 *
	 * @access	public
	 * @param	object	Skin Object
	 * @param	string	Text to change as a reference
	 * @return	boolean True
	 */
	static public function onSkinAfterBottomScripts($skin, &$text) {
		global $wgUser;

		$templates = new TemplateAchievements;
		$redis = RedisCache::getClient('cache');

		$lookup = CentralIdLookup::factory();
		$globalId = $lookup->centralIdFromLocalUser($wgUser, CentralIdLookup::AUDIENCE_RAW);

		if (!$globalId) {
			return true;
		}

		try {
			//Using a global key.
			$redisKey = 'cheevos:display:'.$globalId;
			$displays = $redis->hGetAll($redisKey);
		} catch (RedisException $e) {
			wfDebug(__METHOD__.": Caught RedisException - ".$e->getMessage());
			return true;
		}

		if (is_array($displays) && count($displays)) {
			if ($wgUser->getOption('cheevos-popup-notification')) {
				if ($displays > 3) {
					// Per HYD-784. Only show 3 at a time for a better user experience.
					$displays = array_slice($displays, 0, 3);
				}
				// If use wants to recieve these notifications, lets place them on screen
				$skin->getOutput()->addModules(['ext.cheevos.styles', 'ext.cheevos.notice.js']);
				$skin->getOutput()->enableClientCache(false);
				$text .= $templates->achievementDisplay(implode("\n", $displays));
			} else {
				// If not, lets delete them so that they don't sit around and flood in if this setting ever changes.'
				foreach ($displays as $key => $value) {
					$redis->hDel($redisKey, $key);
				}
			}
		}

		return true;
	}

	/**
	 * Add additional valid login form error messages.
	 *
	 * @access	public
	 * @param	array	Valid login form error messages.
	 * @return	boolean True
	 */
	static public function onLoginFormValidErrorMessages(&$messages) {
		$messages[] = 'login_to_display_achievements';

		return true;
	}

	/**
	 * Add option to disable pop-up notifications.
	 *
	 * @access	public
	 * @param	object	User
	 * @param	array	Default user preferences.
	 * @return	boolean	True
	 */
	static public function onGetPreferences($user, &$preferences) {
		$preferences['cheevos-popup-notification'] = [
			'type' => 'toggle',
			'label-message' => 'cheevos-popup-notification', // a system message
			'section' => 'echo/cheevos-notification'
		];

		return true;
	}

	/**
	 * Insert achievement page link into the personal URLs.
	 *
	 * @access	public
	 * @param	array	Peronsal URLs array.
	 * @param	object	Title object for the current page.
	 * @param	object	SkinTemplate instance that is setting up personal urls.
	 * @return	boolean	True
	 */
	static public function onPersonalUrls(array &$personalUrls, Title $title, SkinTemplate $skin) {
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
	 * Add a link to WikiPoints on the contributions special page.
	 *
	 * @access	public
	 * @param	integer	User ID
	 * @param	object	Title object for the user's page.
	 * @param	array	Array of tools links.
	 * @return	boolean	true
	 */
	static public function onContributionsToolLinks($userId, $userPageTitle, &$tools) {
		global $wgUser;

		if (!$wgUser->isAllowed('wiki_points_admin')) {
			return true;
		}

		$tools[] = Linker::linkKnown(
			SpecialPage::getTitleFor('WikiPointsAdmin'),
			wfMessage('sp_contributions_wikipoints_admin')->escaped(),
			[],
			[
				'action'	=> 'lookup',
				'user_name'	=> $userPageTitle->getText()
			]
		);

		return true;
	}

	/**
	 * Registers our function hooks for displaying blocks of user points
	 *
	 * @access	public
	 * @param	object	Parser reference
	 * @return	boolean	true
	 */
	public static function onParserFirstCallInit(Parser &$parser) {
		$parser->setFunctionHook('wikipointsblock', 'Cheevos\Points\PointsDisplay::pointsBlock');
		return true;
	}
}
