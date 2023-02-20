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

use Cheevos\Cheevos;
use Cheevos\CheevosHelper;
use Cheevos\Points\PointsDisplay;
use MediaWiki\MediaWikiServices;

class SpecialWikiPoints extends HydraCore\SpecialPage {
	/**
	 * Output HTML
	 *
	 * @var string
	 */
	private string $content;

	/**
	 * Main Constructor
	 *
	 * @return void
	 */
	public function __construct() {
		parent::__construct( 'WikiPoints' );
	}

	/**
	 * Main Executor
	 *
	 * @param string $subPage Subpage passed in the URL.
	 *
	 * @return void	[Outputs to screen]
	 */
	public function execute( $subPage ) {
		$this->output->addModuleStyles( [
			'ext.cheevos.wikiPoints.styles',
			'ext.hydraCore.pagination.styles',
			'mediawiki.ui',
			'mediawiki.ui.input',
			'mediawiki.ui.button'
		] );

		$this->setHeaders();

		$this->wikiPoints( $subPage );

		$this->output->addHTML( $this->content );
	}

	/**
	 * Display the wiki points page.
	 *
	 * @param string|null $subPage Subpage
	 *
	 * @return void
	 */
	public function wikiPoints( ?string $subPage = null ): void {
		$dsSiteKey = CheevosHelper::getSiteKey();

		$start = $this->wgRequest->getInt( 'st' );
		$itemsPerPage = 100;

		$form['username'] = $this->wgRequest->getVal( 'user' );

		$globalId = null;
		if ( !empty( $form['username'] ) ) {
			$user = MediaWikiServices::getInstance()->getUserFactory()->newFromName( $form['username'] );

			if ( $user->getId() ) {
				$globalId = Cheevos::getUserIdForService( $user );
			}

			if ( !$globalId ) {
				$globalId = null;
				$form['error'] = wfMessage( 'error_wikipoints_user_not_found' )->escaped();
			}
		}

		$modifiers = explode( '/', trim( trim( $subPage ), '/' ) );
		$isSitesMode = in_array( 'sites', $modifiers ) && CheevosHelper::isCentralWiki();
		$isMonthly = in_array( 'monthly', $modifiers );
		$isGlobal = in_array( 'global', $modifiers );

		$thisPage = SpecialPage::getTitleFor( 'WikiPoints', $subPage );
		$this->output->setPageTitle(
			wfMessage(
				'top_wiki_editors' .
				( $isGlobal ? '_global' : '' ) .
				( $isSitesMode ? '_sites' : '' ) .
				( $isMonthly ? '_monthly' : '' )
			) );
		$this->content = TemplateWikiPoints::getWikiPointsLinks();
		if ( !$isMonthly ) {
			$this->content .= TemplateWikiPointsAdmin::userSearch( $thisPage, $form ) . "<hr/>";
		}
		$this->content .= PointsDisplay::pointsBlockHtml(
			( $isSitesMode || $isGlobal ? null : $dsSiteKey ),
			$globalId,
			$itemsPerPage,
			$start,
			$isSitesMode,
			$isMonthly,
			'table',
			$thisPage
		);
	}

	/**
	 * Return the group name for this special page.
	 *
	 * @return string
	 */
	protected function getGroupName(): string {
		return 'wikipoints';
	}
}
