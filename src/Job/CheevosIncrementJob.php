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
use Cheevos\CheevosHooks;
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
		$services = MediaWikiServices::getInstance();
		$hookContainer = $services->getHookContainer();
		$achievementService = $services->getService( AchievementService::class );
		$increment = $this->getParams();

		$return = $achievementService->increment( $increment );
		if ( isset( $return['earned'] ) ) {
			foreach ( $return['earned'] as $achievement ) {
				$achievement = new CheevosAchievement( $achievement );
				CheevosHooks::broadcastAchievement( $achievement, $increment['site_key'], $increment['user_id'] );
				$hookContainer->run( 'AchievementAwarded', [ $achievement, $increment['user_id'] ] );
			}
		}
		return (bool)$return;
	}
}
