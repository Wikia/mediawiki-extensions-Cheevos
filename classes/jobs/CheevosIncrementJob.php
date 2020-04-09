<?php
/**
 * Cheevos
 * Cheevos Increment Job
 *
 * @package   Cheevos
 * @author    Cameron Chunn
 * @copyright (c) 2017 Curse Inc.
 * @license   GPL-2.0-or-later
 * @link      https://gitlab.com/hydrawiki/extensions/cheevos
 **/

namespace Cheevos\Job;

use CheevosHooks;
use Cheevos\Cheevos;
use Cheevos\CheevosAchievement;
use Cheevos\CheevosException;
use Hooks;
use Job;
use JobQueueGroup;
use MediaWiki\MediaWikiServices;

class CheevosIncrementJob extends Job {
	/**
	 * Queue a new job.
	 *
	 * @param array $parameters Job Parameters
	 */
	public static function queue(array $parameters = []) {
		$job = new self(__CLASS__, $parameters);
		JobQueueGroup::singleton()->push($job);
	}

	/**
	 * Cheevos Increment Job
	 *
	 * @param string           $command    Unused, specific thing to do.
	 * @param array|Title|null $parameters Named arguments passed by the command that queued this job.
	 *
	 * @return boolean Success
	 */
	public function run() {
		$increment = $this->getParams();
		try {
			$return = Cheevos::increment($increment);
			if (isset($return['earned'])) {
				foreach ($return['earned'] as $achievement) {
					$achievement = new CheevosAchievement($achievement);
					CheevosHooks::broadcastAchievement($achievement, $increment['site_key'], $increment['user_id']);
					Hooks::run('AchievementAwarded', [$achievement, $increment['user_id']]);
				}
			}
			return ($return === false ? 1 : 0);
		} catch (CheevosException $e) {
			// Allows requeue to be turned off
			$config = MediaWikiServices::getInstance()->getMainConfig();
			if ($config->has('CheevosNoRequeue') && $config->get('CheevosNoRequeue') === true) {
				return true;
			}
			if ($e->getCode() != 409) {
				self::queue($increment); // Requeue in case of unintended failure.
			}
		}
		return true;
	}
}
