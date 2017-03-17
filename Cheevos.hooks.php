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
	 * Setup anything that needs to be configured before anything else runs.
	 *
	 * @access	public
	 * @return	void
	 */
	static public function onRegistration() {
		//	Cheevos\dataMiner::getUserGlobalStats([20,30,40,50]);
	}

	/**
	 * Get site key and handle master key.
	 */

	private static function getSiteKey() {
		global $dsSiteKey;
		if (!$dsSiteKey || empty($dsSiteKey)) {
			return false;
		}
		if ($dsSiteKey == "master") {
			return null;
		}
		return $dsSiteKey;
	}

	private static function increment($stat, $delta, $user = null) {
		$site_key = self::getSiteKey();
		if ($site_key == null) return;

		if (!$user) {
			global $wgUser;
			$user = $wgUser;
		}

		if (is_int($user)) {
			$user_id = $user;
		} else {
			$lookup = CentralIdLookup::factory();
			$user_id = $lookup->centralIdFromLocalUser($user, CentralIdLookup::AUDIENCE_RAW);
		}

		return Cheevos\Cheevos::increment([
			'user_id' => $user_id,
			'site_key' => $site_key,
			'deltas'   => [
				['stat' => $stat,'delta' => $delta]
			]
		]);
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
		self::increment('article_delete',1,$user);
		return true;
	}

	/**
	 * Handle article insertion increment.
	 *
	 * @access	public
	 * @param	object	$wikiPage: WikiPage created
	 * @param	object	$user: User creating the article
	 * @param	object	$content: New content as a Content object
	 * @param	string	$summary: Edit summary/comment
	 * @param	boolean	$isMinor: Whether or not the edit was marked as minor (new pages can not currently be marked as minor)
	 * @param	boolean	$isWatch: (No longer used)
	 * @param	string	$section: (No longer used)
	 * @param	integer	$flags: Flags passed to WikiPage::doEditContent()
	 * @param	object	$revision: New Revision of the article
	 * @return	True
	 */
	static public function onPageContentInsertComplete(WikiPage $wikiPage, User $user, $content, $summary, $isMinor, $isWatch, $section, $flags, Revision $revision) {
		self::increment('article_create',1,$user);
		return true;
	}

	/**
	 * Handle article save(edit) increment.
	 *
	 * @access	public
	 * @param	object	$wikiPage: WikiPage created
	 * @param	object	$user: User creating the article
	 * @param	object	$content: New content as a Content object
	 * @param	string	$summary: Edit summary/comment
	 * @param	boolean	$isMinor: Whether or not the edit was marked as minor (new pages can not currently be marked as minor)
	 * @param	boolean	$isWatch: (No longer used)
	 * @param	string	$section: (No longer used)
	 * @param	integer	$flags: Flags passed to WikiPage::doEditContent()
	 * @param	object	$revision: New Revision of the article
	 * @param	object	$status: Status object about to be returned by doEditContent()
	 * @param	integer	$baseRevId: the rev ID (or false) this edit was based on
	 * @return	True
	 */
	static public function onPageContentSaveComplete(WikiPage $wikiPage, User $user, $content, $summary, $isMinor, $isWatch, $section, $flags, $revision, $status, $baseRevId) {
		//Do not increment if Revision is null due to a null edit.
		if ($revision === null || is_null($status->getValue()['revision'])) {
			return true;
		}
		self::increment('article_edit',1,$user);
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
		self::increment('article_merge',1);
		return true;
	}

	/**
	 * Handle article protect increment.
	 *
	 * @access	public
	 * @param	object	$article: the article object that was protected
	 * @param	object	$user: the user object who did the protection
	 * @param	boolean	$protect*: boolean whether it was a protect or an unprotect
	 * @param	string	$reason: Reason for protect
	 * @param	bolean	$moveonly: boolean whether it was for move only or not
	 * @return	True
	 */
	static public function onArticleProtectComplete(&$article, &$user, $protect, $reason, $moveonly) {
		self::increment('article_protect',1,$user);
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
		self::increment('admin_block_ip',1,$user);
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
		self::increment('curse_profile_comment',1,$fromUser);
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
		self::increment('curse_profile_add_friend',1,$fromGlobalId);
		return true;
	}

	/**
	 * Handle CurseProfile profile edited.
	 *
	 * @access	public
	 * @param	object	User making the comment.
	 * @param	object	User of the profile being commented on.
	 * @param	integer	Parent ID of the comment.
	 * @param	string	The comment text.
	 * @return	True
	 */
	static public function onCurseProfileEdited($fromUser, $userId, $inReplyTo, $commentText) {
		self::increment('curse_profile_edit',1,$fromUser);
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
		self::increment('send_email',1);
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
		self::increment('admin_patrol',1,$user);
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
		self::increment('file_upload',1);
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
		self::increment('article_watch',1,$user);
		return true;
	}
}
