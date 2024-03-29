<?php

namespace Cheevos\Templates;

use Cheevos\Points\PointsCompReport;
use MediaWiki\MediaWikiServices;
use SpecialPage;

/**
 * Cheevos
 * Cheevos Template Points Comp Page
 *
 * @package   Cheevos
 * @author    Alexia E. Smith
 * @copyright (c) 2017 Curse Inc.
 * @license   GPL-2.0-or-later
 * @link      https://gitlab.com/hydrawiki/extensions/cheevos
 */

class TemplatePointsComp {

	/**
	 * @param PointsCompReport[] $reports
	 */
	public static function pointsCompReports(
		array $reports,
		string $pagination,
		int $compedThreshold,
		bool $queued
	): string {
		$pointsCompURL = SpecialPage::getTitleFor( 'PointsComp' )->getFullURL();

		$html = '';
		if ( $queued ) {
			$html .= "
			<div><div class='successbox'>" . wfMessage( 'points_comp_report_queued' )->escaped() . "</div></div>";
		}

		$html .= "
		<div>
			<dl class='collapse_dl'>
				<dt>" . wfMessage( 'current_active_comps' )->escaped() . "</dt><dd>" .
				 PointsCompReport::getNumberOfActiveSubscriptions() .
				 "</dd>
			</dl>
		</div>
		<form method='post' action='$pointsCompURL'>
			<fieldset>
				<legend>" . wfMessage( 'run_new_report' )->escaped() . "</legend>
				<label for='start_time'>" . wfMessage( 'start_time' )->escaped() . "</label>
				<input id='start_time_datepicker' data-input='start_time' type='text' value=''/>
				<input id='start_time' name='start_time' type='hidden' value=''/>

				<label for='min_point_threshold'>" . wfMessage( 'min_point_threshold' )->escaped() . "</label>
				<input id='min_point_threshold' name='min_point_threshold' type='text' value='$compedThreshold'/>

				<label for='max_point_threshold'>" . wfMessage( 'max_point_threshold' )->escaped() . "</label>
				<input id='max_point_threshold' name='max_point_threshold' type='text' value=''/>

				<input type='submit' value='" . wfMessage( 'run_new_report' )->escaped() . "'/>
			</fieldset>
		</form>
		$pagination
		<table class='wikitable'>
			<thead>
				<tr>
					<th>&nbsp;</th>
					<th>" . wfMessage( 'min_point_threshold' )->escaped() . "</th>
					<th>" . wfMessage( 'max_point_threshold' )->escaped() . "</th>
					<th>" . wfMessage( 'start_time' )->escaped() . "</th>
					<th>" . wfMessage( 'end_time' )->escaped() . "</th>
					<th>" . wfMessage( 'total_users' )->escaped() . "</th>
					<th>" . wfMessage( 'total_new' )->escaped() . "</th>
					<th>" . wfMessage( 'total_extended' )->escaped() . "</th>
					<th>" . wfMessage( 'total_failed' )->escaped() . "</th>
					<th>" . wfMessage( 'total_skipped' )->escaped() . "</th>
					<th>" . wfMessage( 'total_performed' )->escaped() . "</th>
					<th>" . wfMessage( 'total_emailed' )->escaped() . "</th>
					<th>" . wfMessage( 'report_finished' )->escaped() . "</th>
				</tr>
			</thead>
			<tbody>";

		if ( count( $reports ) ) {
			$linkRenderer = MediaWikiServices::getInstance()->getLinkRenderer();
			foreach ( $reports as $report ) {
				$html .= "
				<tr>
					<td>" . $linkRenderer->makeKnownLink(
						SpecialPage::getTitleFor( 'PointsComp', $report->getReportId() ),
						wfMessage(
							'comp_report_link',
							$report->getReportId(),
							gmdate( 'Y-m-d', $report->getRunTime() )
						)->escaped()
					) . "</td>
					<td>{$report->getMinPointThreshold()}</td>
					<td>{$report->getMaxPointThreshold()}</td>
					<td>" . gmdate( 'Y-m-d', $report->getStartTime() ) . "</td>
					<td>" . gmdate( 'Y-m-d', $report->getEndTime() ) . "</td>
					<td>" . ( $report->getTotalNew() + $report->getTotalExtended() ) . "</td>
					<td>{$report->getTotalNew()}</td>
					<td>{$report->getTotalExtended()}</td>
					<td>{$report->getTotalFailed()}</td>
					<td>{$report->getTotalSkipped()}</td>
					<td>{$report->getTotalPerformed()}</td>
					<td>{$report->getTotalEmailed()}</td>
					<td>" . ( $report->isFinished() ? '✓' : '&nbsp;' ) . "</td>
				</tr>";
			}
		}
		$html .= "
			</tbody>
		</table>
		$pagination";

		return $html;
	}

	public static function pointsCompReportDetail( PointsCompReport $report, $userComped, $emailSent ): string {
		$pointsCompPage	= SpecialPage::getTitleFor( 'PointsComp', $report->getReportId() );
		$pointsCompURL	= $pointsCompPage->getFullURL();

		$html = '';

		if ( $userComped !== null ) {
			$successText = $userComped ? 'success' : 'error';
			$html .= "
			<div><div class='" . $successText . "box'>" .
					 wfMessage( 'points_comp_report_user_comp_' . $successText )->escaped() .
					 "</div></div>";
		}

		if ( $emailSent !== null ) {
			$successText = $emailSent ? 'success' : 'error';
			$html .= "
			<div><div class='" . $successText . "box'>" .
					 wfMessage( 'points_comp_report_email_' . $successText )->escaped() .
					 "</div></div>";
		}

		$html .= "
		<div class='button_bar'>
			<div class='buttons_left'>
				<a href='{$pointsCompPage->getFullURL(['csv' => 1])}' class='mw-ui-button'>" .
				 wfMessage( 'download_report_csv' ) .
				 "</a>
			</div>
		</div>
		<dl class='collapse_dl'>
			<dt>" . wfMessage( 'run_time' )->escaped() .
				 "</dt><dd>" . gmdate( 'Y-m-d', $report->getRunTime() ) . "</dd><br/>
			<dt>" . wfMessage( 'min_point_threshold' )->escaped() .
				 "</dt><dd>{$report->getMinPointThreshold()}</dd><br/>
			<dt>" . wfMessage( 'max_point_threshold' )->escaped() .
				 "</dt><dd>{$report->getMaxPointThreshold()}</dd><br/>
			<dt>" . wfMessage( 'start_time' )->escaped() .
				 "</dt><dd>" . gmdate( 'Y-m-d', $report->getStartTime() ) . "</dd><br/>
			<dt>" . wfMessage( 'end_time' )->escaped() .
				 "</dt><dd>" . gmdate( 'Y-m-d', $report->getEndTime() ) . "</dd><br/>
			<dt>" . wfMessage( 'total_users' )->escaped() .
				 "</dt><dd>" . ( $report->getTotalNew() + $report->getTotalExtended() ) . "</dd><br/>
			<dt>" . wfMessage( 'total_new' )->escaped() .
				 "</dt><dd>{$report->getTotalNew()}</dd><br/>
			<dt>" . wfMessage( 'total_extended' )->escaped() .
				 "</dt><dd>{$report->getTotalExtended()}</dd><br/>
			<dt>" . wfMessage( 'total_failed' )->escaped() .
				 "</dt><dd>{$report->getTotalFailed()}</dd><br/>
			<dt>" . wfMessage( 'total_skipped' )->escaped() .
				 "</dt><dd>{$report->getTotalSkipped()}</dd><br/>
			<dt>" . wfMessage( 'total_performed' )->escaped() .
				 "</dt><dd>{$report->getTotalPerformed()}</dd><br/>
			<dt>" . wfMessage( 'total_emailed' )->escaped() .
				 "</dt><dd>{$report->getTotalEmailed()}</dd><br/>
			<dt>" . wfMessage( 'report_finished' )->escaped() .
				 "</dt><dd>" . ( $report->isFinished() ? '✓' : '&nbsp;' ) . "</dd>
		</dl>
		<form method='post' action='$pointsCompURL'>
			<input name='report_id' type='hidden' value='{$report->getReportId()}'/>
			<button name='do' type='submit' value='grantAll'>" . wfMessage( 'grant_all_comps' )->escaped() .
				 "</button>
			<button name='do' type='submit' value='emailAll'/>" . wfMessage( 'email_comped_users' )->escaped() .
				 "</button>
			<button name='do' type='submit' value='grantAndEmailAll'/>" .
				 wfMessage( 'grant_all_comps_and_email' )->escaped() . "</button>
			<table class='wikitable'>
				<thead>
					<tr>
						<th>" . wfMessage( 'wpa_user' )->escaped() . "</th>
						<th>" . wfMessage( 'comp_points' )->escaped() . "</th>
						<th>" . wfMessage( 'comp_new' )->escaped() . "</th>
						<th>" . wfMessage( 'comp_extended' )->escaped() . "</th>
						<th>" . wfMessage( 'comp_failed' )->escaped() . "</th>
						<th>" . wfMessage( 'comp_skipped' )->escaped() . "</th>
						<th>" . wfMessage( 'current_comp_expires' )->escaped() . "</th>
						<th>" . wfMessage( 'new_comp_expires' )->escaped() . "</th>
						<th>" . wfMessage( 'comp_done' )->escaped() . "</th>
						<th>" . wfMessage( 'emailed' )->escaped() . "</th>
					</tr>
				</thead>
				<tbody>";
		$userFactory = MediaWikiServices::getInstance()->getUserFactory();
		while ( ( $reportRow = $report->getNextRow() ) !== false ) {
			$user = $userFactory->newFromId( $reportRow['user_id'] );
			$html .= "
					<tr>
						<td>" . ( $user ? $user->getName() : 'User ID: ' . $user->getId() ) . "</td>
						<td>{$reportRow['points']}</td>
						<td>{$reportRow['comp_new']}</td>
						<td>{$reportRow['comp_extended']}</td>
						<td>{$reportRow['comp_failed']}</td>
						<td>{$reportRow['comp_skipped']}</td>
						<td>" . ( $reportRow['current_comp_expires'] > 0 ?
							gmdate( 'Y-m-d', $reportRow['current_comp_expires'] ) :
							'&nbsp;' ) . "</td>
						<td>" . ( $reportRow['new_comp_expires'] > 0 ?
							gmdate( 'Y-m-d', $reportRow['new_comp_expires'] ) :
							'&nbsp;' ) . "</td>
						<td>" . ( $reportRow['comp_performed'] ? '✓' :
							"<button name='compUser' type='submit' value='{$user->getId()}'/>" .
							wfMessage( 'grant_comp' )->escaped() . "</button>" ) . "</td>
						<td>" . ( $reportRow['email_sent'] ? '✓' :
							"<button name='emailUser' type='submit' value='{$user->getId()}'/>" .
							wfMessage( 'send_comp_email' )->escaped() . "</button>" ) . "</td>
					</tr>";
		}
		$html .= "
				</tbody>
			</table>
		</form>";

		return $html;
	}

	public static function pointsCompReportCSV( PointsCompReport $report ) {
		$headers = wfMessage( 'wpa_user' )->escaped() . "," .
				   wfMessage( 'comp_points' )->escaped() . "," .
				   wfMessage( 'comp_new' )->escaped() . "," .
				   wfMessage( 'comp_extended' )->escaped() . "," .
				   wfMessage( 'comp_failed' )->escaped() . "," .
				   wfMessage( 'comp_skipped' )->escaped() . "," .
				   wfMessage( 'current_comp_expires' )->escaped() . "," .
				   wfMessage( 'new_comp_expires' )->escaped() . "," .
				   wfMessage( 'comp_done' )->escaped() . "," .
				   wfMessage( 'emailed' )->escaped();

		$rows = [];
		$userIdentityLookup = MediaWikiServices::getInstance()->getUserIdentityLookup();
		while ( ( $reportRow = $report->getNextRow() ) !== false ) {
			$user = $userIdentityLookup->getUserIdentityByUserId( (int)$reportRow['user_id'] );
			$rows[] = implode(
				',',
				[
					$user ? $user->getName() : 'User ID: ' . $user->getId(),
					$reportRow['points'],
					$reportRow['comp_new'],
					$reportRow['comp_extended'],
					$reportRow['comp_failed'],
					$reportRow['comp_skipped'],
					$reportRow['current_comp_expires'] > 0 ?
						gmdate( 'Y-m-d', $reportRow['current_comp_expires'] ) : '',
					$reportRow['new_comp_expires'] > 0 ?
						gmdate( 'Y-m-d', $reportRow['new_comp_expires'] ) : '',
					$reportRow['comp_performed'] ? '✓' : '',
					$reportRow['email_sent'] ? '✓' : ''
				]
			);
		}

		return $headers . "\n" . implode( "\n", $rows );
	}
}
