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
 */

namespace Cheevos\Job;

use Cheevos\AchievementService;
use Cheevos\CheevosAchievement;
use Job;
use MediaWiki\MediaWikiServices;

class CheevosIncrementJob extends Job {
	private const COMMAND = 'Cheevos\Job\CheevosIncrementJob';

	public static function queue( array $parameters = [] ): void {
		$job = new self( self::COMMAND, $parameters );
		MediaWikiServices::getInstance()->getJobQueueGroup()->push( $job );
	}

	/** @inheritDoc */
	public function run() {
		$achievementService = MediaWikiServices::getInstance()->getService( AchievementService::class );
		$increment = $this->getParams();

		$return = $achievementService->increment( $increment );
		if ( isset( $return['earned'] ) ) {
			foreach ( $return['earned'] as $achievement ) {
				$achievement = new CheevosAchievement( $achievement );
				$achievementService->broadcastAchievement(
					$achievement,
					$increment['site_key'],
					(int)$increment['user_id']
				);
			}
		}
		return (bool)$return;
	}
}
