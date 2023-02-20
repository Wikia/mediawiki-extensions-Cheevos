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

use Cheevos\CheevosHelper;
use MediaWiki\MediaWikiServices;

class TemplateWikiPoints {
	/**
	 * Points table
	 *
	 * @param array	Array of rows of top points
	 * @param string  Pagination HTML
	 * @param integer Current starting position.
	 * @param array   [Optional] Load wiki information for sites mode.
	 * @param boolean [Optional] Including all wikis or not.
	 * @param boolean [Optional] Showing monthly totals.
	 *
	 * @return string Built HTML
	 */
	public static function pointsBlockHtml( $userPoints, $pagination, $start, $wikis = [], $isSitesMode = false, $isMonthly = false ) {
		$html = "
		<div>{$pagination}</div>
		<table class='wikitable'>
			<thead>
				<tr>
					<th>" . wfMessage( 'rank' )->escaped() . "</th>
					<th>" . wfMessage( 'wiki_user' )->escaped() . "</th>" .
					( $isSitesMode ? "<th>" . wfMessage( 'wiki_site' )->escaped() . "</th>" : "\n" )
					. "<th>" . wfMessage( 'score' )->escaped() . "</th>
					" . ( $isMonthly ? "<th>" . wfMessage( 'monthly' )->escaped() . "</th>" : '' ) . "
				</tr>
			</thead>
			<tbody>";
		if ( !empty( $userPoints ) ) {
			$i = $start;
			foreach ( $userPoints as $userPointsRow ) {
				$wikiName = $userPointsRow->siteKey;
				if ( $isSitesMode && isset( $wikis[$userPointsRow->siteKey] ) ) {
					$wikiName = CheevosHelper::getSiteName( $userPointsRow->siteKey, $wikis[$userPointsRow->siteKey] );
				}
				$i++;
				$html .= "
				<tr>
					<td>{$i}</td>
					<td>{$userPointsRow->userLink}{$userPointsRow->userToolsLinks}</td>" .
					( $isSitesMode ? "<td>{$wikiName}</td>" : "\n" )
					. "<td class='score'>{$userPointsRow->score}</td>"
					. ( $isMonthly ? "<td class='monthly'>" . $userPointsRow->yyyymm . "</td>" : '' ) . "
				</tr>";
			}
		} else {
			$html .= "
				<tr>
					<td colspan='" . ( 3 + $isSitesMode + $isMonthly ) . "'>" . wfMessage( 'no_points_results_found' )->escaped() . "</td>
				</tr>
			";
		}
		$html .= "
			</tbody>
		</table>
		<div>{$pagination}</div>";

		return $html;
	}

	/**
	 * Get links for various wiki points special pages.
	 *
	 * @return array Anchor links.
	 */
	public static function getWikiPointsLinks() {
		$linkRenderer = MediaWikiServices::getInstance()->getLinkRenderer();
		$links = [
			$linkRenderer->makeKnownLink( SpecialPage::getTitleFor( 'WikiPoints' ), wfMessage( 'top_wiki_editors' )->escaped() ),
			$linkRenderer->makeKnownLink( SpecialPage::getTitleFor( 'WikiPoints', 'monthly' ), wfMessage( 'top_wiki_editors_monthly' )->escaped() ),
			$linkRenderer->makeKnownLink( SpecialPage::getTitleFor( 'WikiPoints', 'global' ), wfMessage( 'top_wiki_editors_global' )->escaped() )
		];
		if ( CheevosHelper::isCentralWiki() ) {
			$links[] = $linkRenderer->makeKnownLink( SpecialPage::getTitleFor( 'WikiPoints', 'sites' ), wfMessage( 'top_wiki_editors_sites' )->escaped() );
			$links[] = $linkRenderer->makeKnownLink( SpecialPage::getTitleFor( 'WikiPoints', 'sites/monthly' ), wfMessage( 'top_wiki_editors_sites_monthly' )->escaped() );
		}

		return implode( ' | ', $links ) . "<hr>";
	}

	/**
	 * Get simple dumb pagination.
	 *
	 * @param string	URL Destination
	 * @param integer	Number of items per page.
	 * @param integer	Current starting position.
	 *
	 * @return string HTML
	 */
	public static function getSimplePagination( Title $title, $itemsPerPage, $start ) {
		$previous = max( 0, $start - $itemsPerPage );
		$next = $start + $itemsPerPage;
		$previous = "<a href='{$title->getFullUrl(['st' => $previous])}' class='mw-ui-button'>&lt;</a>";
		$next = "<a href='{$title->getFullUrl(['st' => $next])}' class='mw-ui-button'>&gt;</a>";
		return $previous . ' ' . $next;
	}
}
