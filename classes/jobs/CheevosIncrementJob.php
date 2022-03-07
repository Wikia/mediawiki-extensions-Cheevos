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
use Job;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;

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
		MediaWikiServices::getInstance()->getJobQueueGroup()->push($job);
	}

	/**
	 * Cheevos Increment Job
	 *
	 * @return boolean Success
	 */
	public function run() {
		$increment = $this->getParams();
		try {
			$return = Cheevos::increment($increment);
			if (isset($return['earned'])) {
				$hookContainer = MediaWikiServices::getInstance()->getHookContainer();
				foreach ($return['earned'] as $achievement) {
					$achievement = new CheevosAchievement($achievement);
					CheevosHooks::broadcastAchievement($achievement, $increment['site_key'], $increment['user_id']);
					$hookContainer->run('AchievementAwarded', [$achievement, $increment['user_id']]);
				}
			}
			return ($return === false ? 1 : 0);
		} catch (CheevosException $e) {
			LoggerFactory::getInstance('cheevos')->error( (string)$e, [ 'exception' => $e ] );

			// Allows requeue to be turned off
			$config = MediaWikiServices::getInstance()->getMainConfig();
			if ($config->has('CheevosNoRequeue') && $config->get('CheevosNoRequeue') === true) {
				return true;
			}
			if ($e->getCode() != 409) {
				// Requeue in case of unintended failure.
				self::queue($increment);
			}
		}
		return true;
	}
}
