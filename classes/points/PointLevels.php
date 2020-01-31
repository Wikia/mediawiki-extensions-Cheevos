<?php
/**
 * Curse Inc.
 * Cheevos
 * Wiki Points Levels Class
 *
 * @package   Cheevos
 * @author    Alexia E. Smith
 * @copyright (c) 2014 Curse Inc.
 * @license   GPL-2.0-or-later
 * @link      https://gitlab.com/hydrawiki/extensions/cheevos
 **/

namespace Cheevos\Points;

use DynamicSettings\Environment;
use RedisCache;
use RedisException;

class PointLevels {
	/**
	 * Threshold Values
	 *
	 * @var array
	 */
	static private $levels;

	/**
	 * Class Initialized
	 *
	 * @var boolean
	 */
	static private $initialized = false;

	/**
	 * Redis Cache Key
	 *
	 * @var string
	 */
	static private $redisCacheKey = 'wikipoints::levels';

	/**
	 * Main Initializer
	 *
	 * @return void
	 */
	public static function init() {
		if (!self::$initialized) {
			self::loadLevels();

			self::$initialized = true;
		}
	}

	/**
	 * Get the level information.
	 *
	 * @return array	Array of levels containing $point => $score key value pairs.
	 */
	public static function getLevels() {
		self::init();

		return self::$levels;
	}

	/**
	 * Load the level values from the database.
	 *
	 * @return void
	 */
	private static function loadLevels() {
		if (Environment::isMasterWiki()) {
			$db = wfGetDB(DB_MASTER);
			$result = $db->select(
				['wiki_points_levels'],
				['*'],
				[],
				__METHOD__,
				[
					'ORDER BY'	=> 'points ASC'
				]
			);

			while ($row = $result->fetchRow()) {
				self::$levels[$row['lid']] = $row;
			}
		} else {
			$redis = RedisCache::getClient('cache');
			if ($redis !== false) {
				try {
					$levels = unserialize($redis->get(self::$redisCacheKey));
					if (is_array($levels) && count($levels)) {
						self::$levels = $levels;
					}
				} catch (RedisException $e) {
					wfDebug(__METHOD__ . ": Caught RedisException - " . $e->getMessage());
				}
			}
		}
	}

	/**
	 * Load the level values from the database.
	 *
	 * @param array	Array of levels containing $point => $score key value pairs.
	 *
	 * @return boolean	Successful Save
	 */
	public static function saveLevels($levels) {
		global $wgDBprefix;
		self::init();

		$db = wfGetDB(DB_MASTER);
		$redis = RedisCache::getClient('cache');

		try {
			$db->startAtomic(__METHOD__);
			$db->query("TRUNCATE TABLE {$wgDBprefix}wiki_points_levels;");

			foreach ($levels as $index => $level) {
				$db->insert('wiki_points_levels', $level, __METHOD__);
			}
			$db->endAtomic(__METHOD__);
			if ($redis !== false) {
				$redis->set(self::$redisCacheKey, serialize($levels));
			}
		} catch (\Exception $e) {
			$db->cancelAtomic(__METHOD__);
			return false;
		}
		return true;
	}
}
