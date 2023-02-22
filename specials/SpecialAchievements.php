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
use MediaWiki\MediaWikiServices;

class SpecialAchievements extends SpecialPage {
	/**
	 * Output HTML
	 *
	 * @var string
	 */
	private string $content;

	private OutputPage $output;

	private ?string $siteKey;

	private TemplateAchievements $templates;

	/**
	 * Main Constructor
	 *
	 * @return void
	 */
	public function __construct() {
		parent::__construct( 'Achievements' );

		$dsSiteKey = CheevosHelper::getSiteKey();

		$this->output = $this->getOutput();
		$this->siteKey = $dsSiteKey;
	}

	/**
	 * Main Executor
	 *
	 * @param string $subPage SubPage passed in the URL.
	 *
	 * @return void	[Outputs to screen]
	 */
	public function execute( $subPage ) {
		$this->templates = new TemplateAchievements;
		$this->output->addModuleStyles( [
			'ext.cheevos.styles',
			"ext.hydraCore.button.styles",
			'ext.hydraCore.pagination.styles',
			'mediawiki.ui.button',
			'mediawiki.ui.input'
		] );
		$this->output->addModules( [ 'ext.cheevos.js' ] );
		$this->setHeaders();
		$this->achievementsList( $subPage );
		$this->output->addHTML( $this->content );
	}

	/**
	 * Achievements List
	 *
	 * @param string|null $subPage Passed subPage parameter to be intval()'ed for a Global ID.
	 *
	 * @throws ErrorPageError
	 * @throws UserNotLoggedIn
	 */
	public function achievementsList( string $subPage = null ): void {
		$globalId = false;
		if ( $this->getUser()->isRegistered() ) {
			if ( $this->getUser()->getId() > 0 ) {
				// This is unrelated to the user look up.
				//  Just trigger this statistic if a logged-in user visits an achievement page.
				CheevosHooks::increment( 'achievement_engagement', 1, $this->getUser() );
			}

			$globalId = Cheevos::getUserIdForService( $this->getUser() );
			$user = $this->getUser();
		}

		if ( !empty( $subPage ) && !is_numeric( $subPage ) ) {
			$lookupUser = MediaWikiServices::getInstance()->getUserFactory()->newFromName( $subPage );
			if ( $lookupUser && $lookupUser->getId() ) {
				$user = $lookupUser;
				$globalId = Cheevos::getUserIdForService( $user );
			}
			if ( $globalId < 1 || !$lookupUser->getId() ) {
				throw new ErrorPageError( 'achievements', 'no_user_to_display_achievements' );
			}
		}
		if ( intval( $subPage ) > 0 ) {
			$globalId = intval( $subPage );
			$user = Cheevos::getUserForServiceUserId( $globalId );
			if ( $globalId < 1 || $user === null ) {
				throw new ErrorPageError( 'achievements', 'no_user_to_display_achievements' );
			}
		}

		if ( $globalId < 1 || $user === null ) {
			throw new UserNotLoggedIn( 'login_to_display_achievements', 'achievements' );
		}

		try {
			// Just a helper to fix cases of missed achievements.
			$check = Cheevos::checkUnnotified( $globalId, $this->siteKey, true );
			if ( isset( $check['earned'] ) ) {
				foreach ( $check['earned'] as $earned ) {
					$earnedAchievement = new CheevosAchievement( $earned );
					CheevosHooks::broadcastAchievement( $earnedAchievement, $this->siteKey, $globalId );
					$this->getHookContainer()->run( 'AchievementAwarded', [ $earnedAchievement, $globalId ] );
				}
			}
			$_statuses = Cheevos::getAchievementStatus( $globalId, $this->siteKey );
			$achievements = Cheevos::getAchievements( $this->siteKey );
		} catch ( CheevosException $e ) {
			throw new ErrorPageError( 'achievements', 'error_cheevos_service', [ $e->getMessage() ] );
		}

		$categories = [];
		if ( !empty( $achievements ) ) {
			foreach ( $achievements as $aid => $achievement ) {
				if ( !array_key_exists( $achievement->getCategory()->getId(), $categories ) ) {
					$categories[$achievement->getCategory()->getId()] = $achievement->getCategory();
				}
			}
		}

		if ( $user ) {
			$title = $this->msg( 'achievements-title-for', $user->getName() )->escaped();
		} else {
			$title = $this->msg( 'achievements-title' )->escaped();
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

		$this->output->setPageTitle( $title );
		$this->content = $this->templates->achievementsList( $achievements, $categories, $statuses );
	}

	/**
	 * Return the group name for this special page.
	 *
	 * @return string
	 */
	protected function getGroupName(): string {
		return 'users';
	}
}
