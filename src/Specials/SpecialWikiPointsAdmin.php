<?php
/**
 * Curse Inc.
 * Cheevos
 * A contributor scoring system
 *
 * @package   Cheevos
 * @author    Noah Manneschmidt
 * @copyright (c) 2014 Curse Inc.
 * @license   GPL-2.0-or-later
 * @link      https://gitlab.com/hydrawiki/extensions/cheevos
 */

namespace Cheevos\Specials;

use Cheevos\AchievementService;
use Cheevos\CheevosException;
use Cheevos\CheevosHelper;
use Cheevos\Templates\TemplateWikiPointsAdmin;
use ErrorPageError;
use MediaWiki\User\UserIdentityLookup;
use OutputPage;
use PermissionsError;
use SpecialPage;
use WebRequest;

class SpecialWikiPointsAdmin extends \HydraCore\SpecialPage {

	public function __construct(
		private UserIdentityLookup $userIdentityLookup,
		private AchievementService $achievementService,
		private CheevosHelper $cheevosHelper
	) {
		parent::__construct( 'WikiPointsAdmin', 'wiki_points_admin' );
	}

	/** @inheritDoc */
	public function execute( $subPage ): void {
		$this->checkPermissions();
		$output = $this->getOutput();

		$output->addModuleStyles( [
			'ext.cheevos.wikiPoints.styles',
			'ext.hydraCore.pagination.styles',
			'mediawiki.ui',
			'mediawiki.ui.input',
			'mediawiki.ui.button'
		] );

		$this->setHeaders();

		$request = $this->getRequest();
		switch ( $request->getVal( 'action' ) ) {
			default:
			case 'lookup':
				$this->lookUpUser( $output, $request->getVal( 'user' ) );
				return;
			case 'adjust':
				$this->adjustPoints( $output, $request );
				return;
		}
	}

	/** Shows points only from the searched user, if found. */
	private function lookUpUser( OutputPage $output, ?string $usernameParam ): void {
		if ( empty( $usernameParam ) ) {
			$output->setPageTitle( $this->msg( 'wikipointsadmin' ) );
			$output->addHTML( TemplateWikiPointsAdmin::lookup() );
			return;
		}

		$user = $this->userIdentityLookup->getUserIdentityByName( $usernameParam );
		if ( !$user || !$user->isRegistered() ) {
			$output->setPageTitle( $this->msg( 'wikipointsadmin' ) );
			$output->addHTML( TemplateWikiPointsAdmin::lookup(
				$user,
				[],
				$this->msg( 'error_wikipoints_user_not_found' )->escaped(),
				$usernameParam
			) );
		}

		try {
			$pointsLog = $this->achievementService->getWikiPointLog( [
				'user_id' => $user->getId(),
				'site_key' => CheevosHelper::getSiteKey(),
				'limit' => 100
			] );
			$output->setPageTitle( $this->msg( 'wiki_points_admin_lookup', $user->getName() ) );
			$output->addHTML( TemplateWikiPointsAdmin::lookup( $user, $pointsLog, null, $usernameParam ) );
		} catch ( CheevosException $e ) {
			throw new ErrorPageError(
				$this->msg( 'cheevos_api_error_title' ),
				$this->msg( 'cheevos_api_error', $e->getMessage() )
			);
		}
	}

	/** Adjust points by an arbitrary integer amount. */
	private function adjustPoints( OutputPage $output, WebRequest $request ): void {
		if ( !$this->getUser()->isAllowed( 'wpa_adjust_points' ) ) {
			throw new PermissionsError( 'wpa_adjust_points' );
		}

		$username = $request->getVal( 'user' );
		$page = SpecialPage::getSafeTitleFor( 'WikiPointsAdmin' );
		if ( !$request->wasPosted() ) {
			$output->redirect( $page->getFullURL( [ 'user' => $username ] ) );
			return;
		}

		$amount = $request->getInt( 'amount' );
		$amount = $amount > 0 ? min( $amount, 10000 ) : max( $amount, -10000 );

		$userIdentity = $this->userIdentityLookup->getUserIdentityByName( $username );
		if ( $userIdentity && $amount && $userIdentity->isRegistered() ) {
			$this->cheevosHelper->increment( 'wiki_points', $amount, $userIdentity );
		}

		$output->redirect( $page->getFullURL( [ 'user' => $username, 'pointsAdjusted' => 1 ] ) );
	}

	/** @inheritDoc */
	protected function getGroupName() {
		return 'wikipoints';
	}

	/** @inheritDoc */
	public function isListed() {
		return parent::isListed() && $this->userCanExecute( $this->getUser() );
	}
}
