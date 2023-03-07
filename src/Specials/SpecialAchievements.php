<?php
/**
 * Cheevos
 * Cheevos Special Page
 *
 * @package   Cheevos
 * @author    Hydra Wiki Platform Team
 * @copyright (c) 2017 Curse Inc.
 * @license   GPL-2.0-or-later
 * @link      https://gitlab.com/hydrawiki/extensions/cheevos
 */

use Cheevos\Cheevos;
use Cheevos\CheevosAchievement;
use Cheevos\CheevosException;
use Cheevos\CheevosHelper;
use Cheevos\CheevosHooks;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityLookup;

class SpecialAchievements extends SpecialPage {

	private ?string $siteKey;

	public function __construct( private UserIdentityLookup $userIdentityLookup ) {
		parent::__construct( 'Achievements' );

		$this->siteKey = CheevosHelper::getSiteKey();
	}

	/** @inheritDoc */
	public function execute( $subPage ) {
		$output = $this->getOutput();
		$output->addModuleStyles( [
			'ext.cheevos.styles',
			"ext.hydraCore.button.styles",
			'ext.hydraCore.pagination.styles',
			'mediawiki.ui.button',
			'mediawiki.ui.input'
		] );
		$output->addModules( [ 'ext.cheevos.js' ] );
		$this->setHeaders();

		if ( $this->getUser()->isRegistered() ) {
			// This is unrelated to the user look up.
			// Just trigger this statistic if a logged-in user visits an achievement page
			CheevosHooks::increment( 'achievement_engagement', 1, $this->getUser() );
		}

		$targetUser = $this->getTargetUser( $subPage );

		// Just a helper to fix cases of missed achievements.
		$this->sendUnnotifiedAchievements( $targetUser );

		$output->addHTML( $this->achievementsList( $targetUser ) );
		$output->setPageTitle( $this->msg( 'achievements-title-for', $targetUser->getName() )->escaped() );
	}

	private function achievementsList( UserIdentity $targetUser ): string {
		$userId = $targetUser->getId();

		try {
			$_statuses = Cheevos::getAchievementStatus( $userId, $this->siteKey );
			$achievements = Cheevos::getAchievements( $this->siteKey );
		} catch ( CheevosException $e ) {
			throw new ErrorPageError( 'achievements', 'error_cheevos_service', [ $e->getMessage() ] );
		}

		$categories = [];
		if ( !empty( $achievements ) ) {
			foreach ( $achievements as $achievement ) {
				if ( !array_key_exists( $achievement->getCategory()->getId(), $categories ) ) {
					$categories[$achievement->getCategory()->getId()] = $achievement->getCategory();
				}
			}
		}

		// Fix requires achievement child IDs for display purposes.
		$achievements = CheevosAchievement::correctCriteriaChildAchievements( $achievements );
		// Remove achievements that should not be shown in this context.
		[ $achievements, $_statuses ] = CheevosAchievement::pruneAchievements( [ $achievements, $_statuses ] );

		// @TODO: This fuckery of the $statuses array is backwards compatibility for the template.
		//  If we fix the template to be able to handle more than one wiki at a time
		// this piece of code needs to be removed.
		$statuses = [];
		if ( !empty( $_statuses ) ) {
			foreach ( $_statuses as $_status ) {
				$statuses[$_status->getAchievement_Id()] = $_status;
			}
		}

		return ( new TemplateAchievements() )->achievementsList(
			$this->getUser(),
			$achievements,
			$categories,
			$statuses
		);
	}

	private function sendUnnotifiedAchievements( UserIdentity $userIdentity ): void {
		$userId = $userIdentity->getId();
		try {
			$check = Cheevos::checkUnnotified( $userId, $this->siteKey, true );
			if ( isset( $check['earned'] ) ) {
				foreach ( $check['earned'] as $earned ) {
					$earnedAchievement = new CheevosAchievement( $earned );
					CheevosHooks::broadcastAchievement( $earnedAchievement, $this->siteKey, $userId );
					$this->getHookContainer()->run( 'AchievementAwarded', [ $earnedAchievement, $userId ] );
				}
			}
		} catch ( CheevosException $e ) {
			throw new ErrorPageError( 'achievements', 'error_cheevos_service', [ $e->getMessage() ] );
		}
	}

	private function getTargetUser( ?string $subPage ): UserIdentity {
		if ( !empty( $subPage ) && !is_numeric( $subPage ) ) {
			$userIdentity = $this->userIdentityLookup->getUserIdentityByName( $subPage );
			if ( $userIdentity && $userIdentity->isRegistered() ) {
				return $userIdentity;
			}
			throw new ErrorPageError( 'achievements', 'no_user_to_display_achievements' );
		}

		if ( (int)$subPage > 0 ) {
			$userIdentity = $this->userIdentityLookup->getUserIdentityByUserId( (int)$subPage );
			if ( $userIdentity && $userIdentity->isRegistered() ) {
				return $userIdentity;
			}
			throw new ErrorPageError( 'achievements', 'no_user_to_display_achievements' );
		}

		if ( $this->getUser()->isRegistered() ) {
			return $this->getUser();
		}

		throw new UserNotLoggedIn( 'login_to_display_achievements', 'achievements' );
	}

	/** @inheritDoc */
	protected function getGroupName() {
		return 'users';
	}
}
