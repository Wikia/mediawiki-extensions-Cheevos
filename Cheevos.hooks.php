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
	 * Hooks Initialized
	 *
	 * @var		boolean
	 */
	private static $initialized = false;

	/**
	 * Registration Completed in the register() function.
	 *
	 * @var		boolean
	 */
	private static $registrationComplete = false;

	/**
	 * Mediawiki Database Object
	 *
	 * @var		object
	 */
	static private $DB;

	/**
	 * Redis Storage
	 *
	 * @var		object
	 */
	static private $redis = false;

	/**
	 * Achievement Objects
	 *
	 * @var		array
	 */
	static private $achievements = [];

	/**
	 * Mega(Global) Achievements
	 *
	 * @var		array
	 */
	static private $megaAchievements = [];

	/**
	 * Achievement hook triggers.
	 *
	 * @var		array
	 */
	static private $hookTriggers = [];

	/**
	 * Templates
	 *
	 * @var		object
	 */
	private static $templates = null;

	/**
	 * Array of hashes for daily achievements.
	 *
	 * @var		array
	 */
	static private $dailyAchHashs = [
		'dd3f4b1f3f896eeb557f808ec4d33db1',
		'39660a1bf953eb03d4af7ec911ca2bdd',
		'428fe8ad48d773862c85fd3e81b37992',
		'101adee83112cc91625083d5299c9ae7',
		'b94d2d47a07e3f0c5ad3b0c4c3a9f5cc',
		'816490d025abf7e0ed45a5dbb5b2985d',
		'aa686a359703713c5a5456395b7fff47'
	];

	/**
	 * Array of hashes for WikiPoints achievements.
	 *
	 * @var		array
	 */
	static private $wpAchHashs = [
		'c722bcde56a731bf5a428cd002017944',
		'ff6e2c2a5bfc6adb9b19834078b60bcc',
		'0e9b8997ea70842c765e924e3eaccfd2',
		'760fda84c4bf32ddc64be9ebfb617cdb',
		'73583f8ccf81a83bc3c90ad9ac74bf4e',
		'df3fc983590d6c77f6aaacad10fe1c4f',
		'1f42c336e8b47a7545445a622ea79919',
		'45c670f9d925434a0106c9359cc4d01f',
		'a77bc45ca06e3ecf72c87c3e7ab185ef'
	];

	/**
	 * Array of hashes for WikiPoints achievements.
	 *
	 * @var		array
	 */
	static private $caches = [
		'hookTriggers'
	];

	/**
	 * Debugging Mode
	 *
	 * @var		boolean
	 */
	static private $debugging = false;

	/**
	 * Shutdown Function Registered Already
	 *
	 * @var		boolean
	 */
	static private $shutdownRegistered = false;

	/**
	 * Catch all for achievement hooks that do not require extra code to process.
	 *
	 * @access	public
	 * @param	string	Function name
	 * @param	array	Arguments passed through
	 * @return	void
	 */
	static public function __callStatic($function, $arguments) {
		global $wgUser;

		$lookup = CentralIdLookup::factory();
		$globalId = $lookup->centralIdFromLocalUser($wgUser, CentralIdLookup::AUDIENCE_RAW);
		if ($wgUser->isAllowed('bot') || !$globalId || self::isUserInMaintenance($wgUser)) {
			return true;
		}

		if (array_key_exists($function, self::$hookTriggers)) {
			foreach (self::$hookTriggers[$function] as $achievementHash => $rules) {
				self::debugLog("Magic called {$function} for achievement hash {$achievementHash}...", __CLASS__."::{$function}");

				$passes = true;

				if (!empty($rules['function'])) {
					$passes = call_user_func_array([self, $rules['function']], $arguments);
				}
				if ($passes && count($rules['conditions'])) {
					$passes = false;
					$_conditionPasses = [];
					foreach ($rules['conditions'] as $index => $test) {
						if (!is_numeric($index)) {
							//Prevent non-numeric indexes from being tested as they evaluate to 0.
							continue;
						}
						$_conditionPasses[] = intval(\Cheevos\Achievement::testCondition($arguments[$index], $test));
						self::debugLog("Condition argument index #{$index} ".($_conditionPasses ? 'passed.' : 'failed.'), __CLASS__."::{$function}");
					}
					if (count($_conditionPasses) == array_sum($_conditionPasses)) {
						$passes = true;
					}
					self::debugLog("Conditions for achievement hash {$achievementHash} ".($passes ? 'passed.' : 'failed.'), __CLASS__."::{$function}");
				}

				if ($passes) {
					$achievement = \Cheevos\Achievement::newFromHash($achievementHash);
					if ($achievement !== false) {
						$achievement->award($wgUser);
					} else {
						self::debugLog("Could not load achievement for hash {$achievementHash} after verifying it should be awarded.", __CLASS__."::{$function}");
					}
				}
			}
		} else {
			throw new MWException("[".__METHOD__."] Called function \"{$function}\" does not exist.");
		}
		return true;
	}

	/**
	 * Initiates some needed classes.
	 *
	 * @access	public
	 * @return	void
	 */
	static public function init() {
		if (!self::$initialized) {
			global $achServiceConfig;

			self::$DB = wfGetDB(DB_MASTER);

			self::$redis = RedisCache::getClient('cache');
			self::$templates = new TemplateAchievements;

			self::$initialized = true;

			self::loadFromCache();
		}
	}



	/**
	 * Registers Achievement Hooks
	 *
	 * @access	public
	 * @return	boolean	True
	 */
	static public function register() {
		if (self::$registrationComplete === true) {
			return true;
		}

		self::init();

		if (!count(self::$hookTriggers)) {
			$rules = \Cheevos\Achievement::getAllRules();

			if (count($rules)) {
				foreach ($rules as $hash => $rules) {
					if (count($rules['triggers'])) {
						foreach ($rules['triggers'] as $trigger => $rule) {
							self::$hookTriggers[$trigger][$hash] = $rule;
						}
					}
				}
			}

			self::saveToCache();
		}

		if (count(self::$hookTriggers)) {
			foreach (self::$hookTriggers as $trigger => $achievements) {
				if (!Hooks::isRegistered('CheevosHooks::'.$trigger)) {
					Hooks::register($trigger, 'CheevosHooks::'.$trigger);
				}
			}
		}

		self::$registrationComplete = true;

		return true;
	}

	/**
	 * Loads object variables from cache to avoid populating from the database and reassembling.
	 *
	 * @access	private
	 * @return	boolean	Success
	 */
	static private function loadFromCache() {
		global $wgMetaNamespace;

		if (self::$redis === false) {
			return false;
		}

		try {
			$cacheControl = intval(self::$redis->get($wgMetaNamespace.':achievement:cache:control'));
		} catch (RedisException $e) {
			wfDebug(__METHOD__.": Caught RedisException - ".$e->getMessage());
			return false;
		}
		$outOfDate = time() - 3600;
		if ($cacheControl <= $outOfDate) {
			return false;
		}

		foreach (self::$caches as $cache) {
			$key = $wgMetaNamespace.':achievement:cache:$'.$cache;
			try {
				if (self::$redis->exists($key)) {
					$temp[$cache] = unserialize(self::$redis->get($key));
				} else {
					//If one key is missing from Redis we have to invalidate the cache and abort.
					self::invalidateCache();
					return false;
				}
			} catch (RedisException $e) {
				wfDebug(__METHOD__.": Caught RedisException - ".$e->getMessage());
				return false;
			}
		}
		foreach ($temp as $cache => $data) {
			self::$$cache = $data;
		}

		return true;
	}

	/**
	 * Save object variables to cache.
	 *
	 * @access	private
	 * @return	boolean	Success
	 */
	static private function saveToCache() {
		global $wgMetaNamespace;

		if (self::$redis === false) {
			return false;
		}

		$success = true;
		try {
			foreach (self::$caches as $cache) {
				$key = $wgMetaNamespace.':achievement:cache:$'.$cache;
				if (!self::$redis->set($key, serialize(self::$$cache))) {
					$success = false;
				}
			}
			if ($success === true) {
				self::$redis->set($wgMetaNamespace.':achievement:cache:control', time());
			}
		} catch (RedisException $e) {
			wfDebug(__METHOD__.": Caught RedisException - ".$e->getMessage());
			return false;
		}
		return $success;
	}

	/**
	 * Invalidates the achievement cache.
	 *
	 * @access	public
	 * @return	void
	 */
	static public function invalidateCache() {
		global $wgMetaNamespace;

		try {
			foreach (self::$caches as $cache) {
				$key = $wgMetaNamespace.':achievement:cache:$'.$cache;
				self::$redis->del($key, self::$$cache);
			}
			self::$redis->del($wgMetaNamespace.':achievement:cache:control');
		} catch (RedisException $e) {
			wfDebug(__METHOD__.": Caught RedisException - ".$e->getMessage());
			return false;
		}
	}

	static public function onRegistration() {
		// load the Cheevo's Client code.
		require_once(__DIR__.'/cheevos-client/autoload.php');
	}

	/**
	 * Handle actions when an achievement or a mega achievement is awarded.
	 *
	 * @access	public
	 * @param	object	\Cheevos\Achievement or \Cheevos\MegaAchievement
	 * @param	object	User
	 * @return	boolean	True
	 */
	static public function onAchievementAwarded($achievement, User $user) {
		global $dsSiteKey;

		if ($achievement === false) {
			return true;
		}

		if (class_exists('\EditPoints') && $achievement->getPoints() > 0) {
			$points = EditPoints::achievementEarned($user->getId(), $achievement->getPoints());
			if ($points->save()) {
				$points->updatePointTotals();
			}
		}

		self::displayAchievement($achievement, $user);

		//Do not fall into a trap of inifinite loops.  MegaAchievement calls this hook as well.
		if (!$achievement->isMega()) {
			register_shutdown_function('CheevosHooks::checkMegaAchievements', $dsSiteKey, $user);
		}

		return true;
	}

	/**
	 * Handle actions when an achievement or a mega achievement is unawarded.
	 *
	 * @access	public
	 * @param	object	\Cheevos\Achievement or \Cheevos\MegaAchievement
	 * @param	object	User
	 * @return	boolean	True
	 */
	static public function onAchievementUnawarded($achievement, User $user) {
		if ($achievement === false) {
			return true;
		}

		if (class_exists('\EditPoints') && $achievement->getPoints() > 0) {
			$points = EditPoints::achievementRevoked($user->getId(), ($achievement->getPoints() * -1));
			if ($points->save()) {
				$points->updatePointTotals();
			}
		}

		return true;
	}

	/**
	 * Adds achievement display HTML to page output.
	 *
	 * @access	private
	 * @param	object	Achievement
	 * @param	object	User
	 * @return	void
	 */
	static private function displayAchievement($achievement, User $user) {
		global $dsSiteKey;

		self::init();

		$lookup = CentralIdLookup::factory();
		$globalId = $lookup->centralIdFromLocalUser($user, CentralIdLookup::AUDIENCE_RAW);

		if (self::$redis === false || !$globalId) {
			return;
		}

		$HTML = self::$templates->achievementBlockPopUp($achievement);

		try {
			//Using a global key.
			$redisKey = 'achievement:display:'.$globalId;
			self::$redis->hSet($redisKey, $dsSiteKey."-".$achievement->getHash(), $HTML);
			self::$redis->expire($redisKey, 3600);
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

		self::init();

		if (self::inMaintenance() || (defined('MW_API') && MW_API === true) || self::$redis === false) {
			//Do not display when being accessed through the API or when in maintenance.
			return true;
		}

		$lookup = CentralIdLookup::factory();
		$globalId = $lookup->centralIdFromLocalUser($wgUser, CentralIdLookup::AUDIENCE_RAW);

		if (!$globalId) {
			return true;
		}

		try {
			//Using a global key.
			$redisKey = 'achievement:display:'.$globalId;
			$displays = self::$redis->hGetAll($redisKey);
		} catch (RedisException $e) {
			wfDebug(__METHOD__.": Caught RedisException - ".$e->getMessage());
			return true;
		}

		if (is_array($displays) && count($displays)) {
			$skin->getOutput()->addModules(['ext.achievements.styles', 'ext.achievements.notice.js']);
			$skin->getOutput()->enableClientCache(false);
			$text .= self::$templates->achievementDisplay(implode("\n", $displays));
		}
		return true;
	}

	/**
	 * Debug Logger
	 *
	 * @access	private
	 * @param	string	Debug Message
	 * @param	string	Method name passed from __METHOD__
	 * @return	void
	 */
	static private function debugLog($message, $method) {
		if (self::$debugging) {
			echo "[{$method}] ".$message."\n";
		}
	}

	/**
	 * Search through an objects getter properties.
	 *
	 * @access	public
	 * @param	array	Object(s) to search.
	 * @param	array	Object properties to search against.
	 * @return	array	Object(s) that match the search criteria.
	 */
	static public function searchByObjectValue($objects, $searchKeys = [], $searchTerm = '') {
		$objects = (array) $objects;
		$searchTerm = mb_strtolower($searchTerm, 'UTF-8');
		$found = array();

		foreach ($objects as $key => $object) {
			foreach ($searchKeys as $sKey) {
				$function = "get".ucfirst($sKey);
				$data = $object->$function();
				if (is_array($$data)) {
					$_temp = mb_strtolower(implode(',', $data), 'UTF-8');
				} else {
					$_temp = mb_strtolower($data, 'UTF-8');
				}
				if (stripos($_temp, $searchTerm) !== false) {
					$found[$key] = $object;
				}
			}
		}

		return $found;
	}

	/**
	 * Registers shutdown function to track daily achievements.
	 *
	 * @access	public
	 * @param	object	OutputPage
	 * @return	boolean	True
	 */
	static public function onParserAfterTidy($output) {
		if (self::$shutdownRegistered) {
			return true;
		}

		register_shutdown_function('CheevosHooks::trackDailyAchievements');

		self::$shutdownRegistered = true;

		return true;
	}

	/**
	 * Handle daily achievement tracking.
	 *
	 * @access	public
	 * @return	boolean	True if the user has visited daily or false if they missed a day.
	 */
	static public function trackDailyAchievements() {
		global $wgUser;

		if (PHP_SAPI === 'cli') {
			return true;
		}

		//Force a resetup since PHP may close the connection while shutting down before this function runs.
		self::$redis = RedisCache::getClient('cache', [], true);

		if (self::$redis === false) {
			return true;
		}

		$lookup = CentralIdLookup::factory();
		$globalId = $lookup->centralIdFromLocalUser($wgUser, CentralIdLookup::AUDIENCE_RAW);
		if ($wgUser->getId() > 0 && $globalId > 0) {
			try {
				$redisKey = wfMemcKey('achievement', 'user', 'trackDaily', $globalId);
				$trackDaily = unserialize(self::$redis->get($redisKey));
				if ($trackDaily > 0) {
					//Day start and ends are relative to the user's typical visit times with them being placed in the middle of it.  This way they have a twelve hour buffer to not miss a day.
					$currentTime = time();
					$trackStart = $trackDaily - 43200; //User visit time minus twelve hour buffer.
					$trackEnd = $trackDaily + 43200; //User visit time plus twelve hour buffer.
					$nextDayStart = $trackEnd + 1;
					$nextDayEnd = $trackEnd + 86401;

					if ($currentTime >= $trackStart && $currentTime <= $trackEnd) {
						//Within the same tracking period so nothing needs to be done.
						return true;
					}

					if ($currentTime >= $nextDayStart && $currentTime <= $nextDayEnd) {
						$trackDaily += 86400;
						//Set a new $trackDaily for this user as they have visited within the next time period.  Increment/Award Achievement as needed.
						self::$redis->set($redisKey, serialize($trackDaily));
						self::$redis->expire($redisKey, 345600);
						self::awardDailyAchievements($wgUser);

						return true;
					}

					if ($currentTime > $nextDayEnd) {
						//Missed a day, reset the key.
						self::$redis->set($redisKey, serialize($currentTime));
						self::$redis->expire($redisKey, 345600);
						self::unawardDailyAchievements($wgUser);
						self::awardDailyAchievements($wgUser);

						return false;
					}
				} else {
					self::unawardDailyAchievements($wgUser);
					self::awardDailyAchievements($wgUser);
					self::$redis->set($redisKey, serialize(time()));
					self::$redis->expire($redisKey, 345600);

					return true;
				}
			} catch (RedisException $e) {
				wfDebug(__METHOD__.": Caught RedisException - ".$e->getMessage());
				return true;
			}
		}
	}

	/**
	 * Awards daily achievements.
	 *
	 * @access	private
	 * @param	object	Mediawiki User Object
	 * @return	void
	 */
	static private function awardDailyAchievements($user) {
		foreach (self::$dailyAchHashs as $hash) {
			$trackDailyAch = \Cheevos\Achievement::newFromHash($hash, true);
			if ($trackDailyAch !== false && $trackDailyAch->award($user) === true) {
				self::displayAchievement($trackDailyAch, $user);
			}
		}
	}

	/**
	 * Unawards daily achievements.
	 *
	 * @access	private
	 * @param	object	Mediawiki User Object
	 * @return	void
	 */
	static private function unawardDailyAchievements($user) {
		foreach (self::$dailyAchHashs as $hash) {
			$trackDailyAch = \Cheevos\Achievement::newFromHash($hash, true);
			if ($trackDailyAch !== false) {
				$trackDailyAch->unaward($user);
			}
		}
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
		if (($score > 0 || $score < 0) && $wgUser->getId() == $userId) {
			foreach (self::$wpAchHashs as $hash) {
				$wpAchievement = \Cheevos\Achievement::newFromHash($hash, true);
				if ($trackDailyAch !== false) {
					$wpAchievement->award($wgUser, $score);
				}
			}
		}
		return true;
	}

	/**
	 * Handles detecting if someone replies to a comment on their page.
	 *
	 * @access	private
	 * @param	object	User
	 * @param	string	Field ID
	 * @param	string	Field Value
	 * @return	boolean	True
	 */
	static private function onCurseProfileEdited($user, $field, $value) {
		global $wgUser;

		if ($user->getId() && $user->getId() == $wgUser->getId() && !empty($value)) {
			return true;
		}

		return false;
	}

	/**
	 * Handles detecting if someone replies to a comment on their page.
	 *
	 * @access	private
	 * @param	object	User that created the comment.
	 * @param	integer	User ID of the user(page) that is receiving the comment.
	 * @param	integer	Parent comment ID.
	 * @param	string	Comment Text
	 * @return	boolean	True if a reply on their own page.
	 */
	static private function onCurseProfileCommentReply($fromUser, $toUserId, $inReplyTo, $commentText) {
		if ($fromUser->getId() == $toUserId && $inReplyTo > 0) {
			return true;
		}

		return false;
	}

	/**
	 * Insert achievement page link into the personal URLs.
	 *
	 * @access	public
	 * @param	array	Peronsal URLs array.
	 * @param	object	Title object for the current page.
	 * @param	object	SkinTemplate instance that is setting up personal urls
	 * @return	boolean	True
	 */
	static public function onPersonalUrls(array &$personalUrls, Title $title, SkinTemplate $skin) {
		$URL = Skin::makeSpecialUrl('Achievements');
		if (!$skin->getUser()->isAnon()) {
			$achievements = [
				'achievements'	=> [
					'text'		=> wfMessage('achievements')->text(),
					'href'		=> $URL,
					'active'	=> true
				]
			];
			HydraCore::array_insert_before_key($personalUrls, 'mycontris', $achievements);
		}

		return true;
	}

	/**
	 * Handle adding achievements menu options into wiki sites.
	 *
	 * @access	public
	 * @param	array	Additional menu options; may already contain options added by other extensions.
	 * @return	boolean	True
	 */
	static public function onWikiSitesMenuOptions(&$extraMenuOptions, \DynamicSettings\Wiki $wiki) {
		global $wgUser;

		if ($wgUser->isAllowed('edit_mega_achievements')) {
			$megaAchievementsPage = Title::newFromText('Special:MegaAchievements');
			$extraMenuOptions[] = "<a href='{$megaAchievementsPage->getFullURL()}/updateSiteMega?siteKey={$wiki->getSiteKey()}' title='".wfMessage('update_site_mega')->escaped()."'>".HydraCore::awesomeIcon('medium').wfMessage('update_site_mega')->escaped()."</a>";
		}

		return true;
	}

	/**
	 * Check if mega achievements need to be awarded.
	 *
	 * @access	public
	 * @param	string	Site Key Hash
	 * @param	object	User
	 * @return	boolean	False if an error occurred.
	 */
	static public function checkMegaAchievements($siteKey, $user) {
		$lookup = CentralIdLookup::factory();
		$globalId = $lookup->centralIdFromLocalUser($user, CentralIdLookup::AUDIENCE_RAW);
		if (strlen($siteKey) == 32 && $globalId > 0) {
			$megas = \Cheevos\MegaAchievement::getAllForSite($siteKey);
			$progress = \Cheevos\Progress::newFromGlobalId($globalId);
			$earnedHashes = $progress->getEarnedHashes();
			if (is_array($megas) && count($megas)) {
				foreach ($megas as $_globalId => $megaAchievement) {
					if (!is_array($megaAchievement->getRequires()) || !count($megaAchievement->getRequires())) {
						//Despite this mega achievement being for this site it appears to be manually awarded.
						continue;
					}

					$valid = true;
					foreach ($megaAchievement->getRequires() as $hash) {
						if (!in_array($hash, $earnedHashes)) {
							$valid = false;
						}
					}

					if ($valid === true) {
						$megaAchievement->award($user);
					}
				}
				return true;
			}
		}
		return false;
	}

	/**
	 * Is the achievements system currently in maintenance?
	 *
	 * @access	public
	 * @param	object	User
	 * @return	boolean	In Maintenance
	 */
	static public function isUserInMaintenance($user) {
		global $wgMetaNamespace;

		$lookup = CentralIdLookup::factory();
		$globalId = $lookup->centralIdFromLocalUser($user, CentralIdLookup::AUDIENCE_RAW);
		if (!$globalId) {
			return false;
		}

		$userKey = $wgMetaNamespace.':achievement:maintenance:user:'.$globalId;

		try {
			if (self::$redis !== false) {
				$hasStarted = self::$redis->exists($userKey);
				$inProgress = (bool) self::$redis->get($userKey);
			}
		} catch (RedisException $e) {
			wfDebug(__METHOD__.": Caught RedisException - ".$e->getMessage());
		}

		if ($hasStarted && $inProgress) {
			//User maintenance has started and is currently in progress.
			return true;
		}

		if (self::inMaintenance() && !$hasStarted) {
			//User is not back logged and global maintenance is enabled.  Do not allow the user to trigger achievements during this time.
			return true;
		}

		return false;
	}

	/**
	 * Is the achievements system currently in maintenance?
	 *
	 * @access	public
	 * @return	boolean	In Maintenance
	 */
	static public function inMaintenance() {
		global $wgMetaNamespace;

		if (self::$redis !== false) {
			try {
				$signaledMaintenance = self::$redis->exists($wgMetaNamespace.':achievement:maintenance');
			} catch (RedisException $e) {
				wfDebug(__METHOD__.": Caught RedisException - ".$e->getMessage());
			}

			if ($signaledMaintenance === true) {
				return true;
			}
		}

		if (defined('ACHIEVEMENTS_MAINTENANCE') === true && ACHIEVEMENTS_MAINTENANCE === true) {
			return true;
		}
		return false;
	}

}
