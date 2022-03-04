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

use Cheevos\Cheevos;
use Cheevos\CheevosAchievement;
use CheevosHooks;
use Hooks;
use Job;
use JobQueueGroup;

class CheevosIncrementJob extends Job {
	/**
	 * Queue a new job.
	 *
	 * @param array $parameters Job Parameters
	 *
	 * @return void
	 */
	public static function queue(array $parameters = []) {
		$job = new self(__CLASS__, $parameters);
		JobQueueGroup::singleton()->push($job);
	}

	/**
	 * Cheevos Increment Job
	 *
	 * @return boolean Success
	 */
	public function run() {
		$increment = $this->getParams();

		$return = Cheevos::increment($increment);
		if (isset($return['earned'])) {
			foreach ($return['earned'] as $achievement) {
				$achievement = new CheevosAchievement($achievement);
				CheevosHooks::broadcastAchievement($achievement, $increment['site_key'], $increment['user_id']);
				Hooks::run('AchievementAwarded', [$achievement, $increment['user_id']]);
			}
		}
		return (bool)$return;
	}
}
