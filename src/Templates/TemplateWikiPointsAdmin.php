<?php

namespace Cheevos\Templates;

use Cheevos\AchievementService;
use Cheevos\CheevosException;
use MediaWiki\MediaWikiServices;
use MediaWiki\User\UserIdentity;
use RequestContext;
use SpecialPage;
use Title;

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

class TemplateWikiPointsAdmin {
	public static function userSearch( Title $title, ?string $error = null, ?string $username ): string {
		$username = htmlspecialchars( $username );

		$html = '';
		if ( !empty( $error ) ) {
			$html .= "<div class='errorbox'>$error</div>";
		}
		return $html . "
		<form id='wikipoints_lookup_form' class='mw-ui-vform' method='get' action='" . $title->getFullURL() . "'>
			<div class='mw-ui-vform-field'>
				<input type='text'
				 name='user'
				 placeholder='" . wfMessage( 'wpa_user' )->escaped() . "'
				 value='$username' class='oo-ui-inputWidget-input'/>
				<input class='submit' type='submit' value='" . wfMessage( 'lookup' )->escaped() . "'/>
			</div>
		</form>";
	}

	/**
	 * User lookup display
	 *
	 * @return string Built HTML
	 */
	public static function lookup(
		?UserIdentity $user = null, array $points = [], ?string $error = null, string $username = ''
	): string {
		$context = RequestContext::getMain();
		$request = $context->getRequest();
		$currentUser = $context->getUser();
		$html = self::userSearch( SpecialPage::getSafeTitleFor( 'WikiPointsAdmin' ), $error, $username );

		$addSubtractButtonText = wfMessage( 'wikipointsaddsubtractbutton' )->escaped();
		$addSubtractTooltip = wfMessage( 'wikipointsaddsubtracttooltip' )->escaped();
		$pointsAdjusted = wfMessage( 'wikipointsaddsubtractsuccess' )->escaped();

		if ( $request->getVal( 'pointsAdjusted' ) ) {
			$html .= "
			<div><div class='successbox'>$pointsAdjusted</div></div>";
		}

		$wpaPage = SpecialPage::getSafeTitleFor( 'WikiPointsAdmin' );
		$escapedUserName = $user ? htmlspecialchars( $user->getName(), ENT_QUOTES ) : '';
		if ( $currentUser->isAllowed( 'wpa_adjust_points' ) ) {
			$html .= "
		<div id='wpa_user_controls'>
			<form method='post' action='" . $wpaPage->getFullURL() . "'>
				<fieldset>
					<input type='hidden' name='action' value='adjust'>
					<input type='hidden' name='user' value='$escapedUserName'>
					<input type='number' name='amount' placeholder='$addSubtractTooltip' id='addSubtractField'>
					<input type='submit' value='$addSubtractButtonText'>
				</fieldset>
			</form>
		</div>";
		}
		if ( $user !== null ) {
			$html .= "
		<h2>" . wfMessage( 'points_recently_earned' )->escaped() . "</h2>
		<table class='wikitable wikipoints'>
			<thead>
				<tr>
					<th>" . wfMessage( 'wpa_reason' )->escaped() . "</th>
					<th>" . wfMessage( 'wpa_date' )->escaped() . "</th>
					<th title='" . wfMessage( 'char_size' )->escaped() . "'>" .
					 wfMessage( 'char_size' )->escaped() . "</th>
					<th title='" . wfMessage( 'char_diff' )->escaped() . "'>" .
					 wfMessage( 'char_diff' )->escaped() . "</th>
					<th>" . wfMessage( 'score' )->escaped() . "</th>
				</tr>
			</thead>
			<tbody>";
			if ( count( $points ) ) {
				foreach ( $points as $pointRow ) {
					$html .= "
				<tr>";

					$link = wfMessage( 'manual_adjustment' );
					if ( $pointRow->getPage_Id() ) {
						$title = Title::newFromID( $pointRow->getPage_Id() );
						if ( $title ) {
							$arguments = [];
							if ( $pointRow->getRevision_Id() ) {
								$arguments['oldid'] = $pointRow->getRevision_Id();
							}
							$link = '<a href="' . $title->getInternalURL( $arguments ) . '">' .
									$title->getPrefixedText() . '</a>';
						} else {
							$link = '<span title="' . wfMessage( 'deleted_article' )->escaped() . '">' .
									wfMessage( 'article_id_number' )->escaped() . $pointRow->getPage_Id() .
									'</span>';
						}
					}
					if ( $pointRow->getAchievement_Id() ) {
						$title = SpecialPage::getTitleFor( 'Achievements' );
						try {
							$achievement = MediaWikiServices::getInstance()->getService( AchievementService::class )
								->getAchievement( $pointRow->getAchievement_Id() );
							$link = '<a
							href="' . $title->getInternalURL() .
									'/' .
									$escapedUserName .
									'#category=' .
									$achievement->getCategory()->getSlug() .
									'&achievement=' .
									$pointRow->getAchievement_Id() .
									'">' . htmlentities( $achievement->getName() ) . '</a>';
						} catch ( CheevosException $e ) {
							$link = '<a
							href="' . $title->getInternalURL() . '#achievement=' . $pointRow->getAchievement_Id() .
									'">' .
									wfMessage( 'achievement_id', $pointRow->getAchievement_Id() )->escaped() .
									'</a>';
						}
					}
					$html .= "
					<td>$link</td>
					<td>" . date( 'Y-m-d H:i:s', $pointRow->getTimestamp() ) . "</td>
					<td class='numeric'>" .
							 ( $pointRow->getPage_Id() ? $pointRow->getSize() : wfMessage( "n-a" )->escaped() ) .
							 "</td>
					<td class='numeric'>" .
							 (
								 $pointRow->getPage_Id() ?
									 $pointRow->getSize_Diff() :
									 wfMessage( "n-a" )->escaped()
							 ) . "</td>
					<td class='numeric'>{$pointRow->getPoints()}</td>
				</tr>";
				}
			}
			$html .= "
			</tbody>
		</table>";
		}

		return $html;
	}
}
