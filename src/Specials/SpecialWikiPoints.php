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

use Cheevos\CheevosHelper;
use Cheevos\Points\PointsDisplay;
use Cheevos\Templates\TemplateWikiPoints;
use Cheevos\Templates\TemplateWikiPointsAdmin;
use MediaWiki\User\UserIdentityLookup;
use OutputPage;
use SpecialPage;
use WebRequest;

class SpecialWikiPoints extends SpecialPage {

	public function __construct( private UserIdentityLookup $userIdentityLookup ) {
		parent::__construct( 'WikiPoints' );
	}

	/** @inheritDoc */
	public function execute( $subPage ) {
		$output = $this->getOutput();
		$output->addModuleStyles( [
			'ext.cheevos.wikiPoints.styles',
			'ext.hydraCore.pagination.styles',
			'mediawiki.ui',
			'mediawiki.ui.input',
			'mediawiki.ui.button'
		] );

		$this->setHeaders();

		$this->wikiPoints( $subPage, $output, $this->getRequest() );
	}

	public function wikiPoints( ?string $subPage = null, OutputPage $output, WebRequest $request ): void {
		$username = $request->getVal( 'user' );
		$error = null;
		$globalId = null;
		if ( !empty( $username ) ) {
			$userIdentity = $this->userIdentityLookup->getUserIdentityByName( $username );
			if ( $userIdentity && $userIdentity->isRegistered() ) {
				$globalId = $userIdentity->getId();
			} else {
				$error = $this->msg( 'error_wikipoints_user_not_found' )->escaped();
			}
		}

		$modifiers = explode( '/', trim( trim( $subPage ), '/' ) );
		$isSitesMode = in_array( 'sites', $modifiers ) && CheevosHelper::isCentralWiki();
		$isMonthly = in_array( 'monthly', $modifiers );
		$isGlobal = in_array( 'global', $modifiers );

		$thisPage = SpecialPage::getTitleFor( 'WikiPoints', $subPage );
		$output->setPageTitle(
			$this->msg(
				'top_wiki_editors' .
				( $isGlobal ? '_global' : '' ) .
				( $isSitesMode ? '_sites' : '' ) .
				( $isMonthly ? '_monthly' : '' )
			) );

		$html = TemplateWikiPoints::getWikiPointsLinks();
		if ( !$isMonthly ) {
			$html .= TemplateWikiPointsAdmin::userSearch( $thisPage, $username, $error ) . "<hr/>";
		}
		$html .= PointsDisplay::pointsBlockHtml(
			$isSitesMode || $isGlobal ? null : CheevosHelper::getSiteKey(),
			$globalId,
			100,
			$request->getInt( 'st' ),
			$isSitesMode,
			$isMonthly,
			'table',
			$thisPage
		);
		$output->addHTML( $html );
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
