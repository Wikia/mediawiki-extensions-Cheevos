<?php
/**
 * Cheevos
 * Cheevos Template Points Comp Page
 *
 * @author		Alexia E. Smith
 * @copyright	(c) 2017 Curse Inc.
 * @license		All Rights Reserved
 * @package		Cheevos
 * @link		http://www.curse.com/
 *
**/

class TemplatePointsComp {
	/**
	 * Points Comp Reports List
	 *
	 * @access	public
	 * @return	string	HTML
	 */
	static public function pointsCompReports($reports = []) {
		$html .= "
		<table class='wikitable'>
			<thead>
				<tr>
					<th>&nbsp;</th>
					<th>".wfMessage('point_threshold')->escaped()."</th>
					<th>".wfMessage('month_start')->escaped()."</th>
					<th>".wfMessage('month_end')->escaped()."</th>
					<th>".wfMessage('total_new')->escaped()."</th>
					<th>".wfMessage('total_extended')->escaped()."</th>
					<th>".wfMessage('total_failed')->escaped()."</th>
					<th>".wfMessage('total_performed')->escaped()."</th>
					<th>".wfMessage('total_emailed')->escaped()."</th>
				</tr>
			</thead>
			<tbody>";
		if (count($reports)) {
			foreach ($reports as $report) {
				$html .= "
				<tr>
					<td>".Linker::linkKnown(SpecialPage::getTitleFor('PointsComp', $report->getReportId()), wfMessage('comp_report_link', $report->getReportId())->escaped())."</td>
					<td>{$report->getPointThreshold()}</td>
					<td>".gmdate('Y-m-d', $report->getMonthStart())."</td>
					<td>".gmdate('Y-m-d', $report->getMonthEnd())."</td>
					<td>{$report->getTotalNew()}</td>
					<td>{$report->getTotalExtended()}</td>
					<td>{$report->getTotalFailed()}</td>
					<td>{$report->getTotalPerformed()}</td>
					<td>{$report->getTotalEmailed()}</td>
				</tr>";
			}
		}
		$html .= "
			</tbody>
		</table>";

		return $html;
	}

	/**
	 * Points Comp Report Detail
	 *
	 * @access	public
	 * @return	string	HTML
	 */
	static public function pointsCompReportDetail($report) {
		$html .= "
		<dl>
			<dt>".wfMessage('point_threshold')->escaped()."</dt><dd>{$report->getPointThreshold()}</dd>
			<dt>".wfMessage('month_start')->escaped()."</dt><dd>".gmdate('Y-m-d', $report->getMonthStart())."</dd>
			<dt>".wfMessage('month_end')->escaped()."</dt><dd>".gmdate('Y-m-d', $report->getMonthEnd())."</dd>
			<dt>".wfMessage('total_new')->escaped()."</dt><dd>{$report->getTotalNew()}</dd>
			<dt>".wfMessage('total_extended')->escaped()."</dt><dd>{$report->getTotalExtended()}</dd>
			<dt>".wfMessage('total_failed')->escaped()."</dt><dd>{$report->getTotalFailed()}</dd>
			<dt>".wfMessage('total_performed')->escaped()."</dt><dd>{$report->getTotalPerformed()}</dd>
			<dt>".wfMessage('total_emailed')->escaped()."</dt><dd>{$report->getTotalEmailed()}</dd>
		</dl>
		<form method='post' action='?do=run'>
			<input name='report_id' type='hidden' value='{$report->getReportId()}'/>
			<input name='report_id' type='button' value='".wfMessage('give_all_comps')->escaped()."'/>
			<input name='report_id' type='button' value='".wfMessage('email_users')->escaped()."'/>
			<input name='report_id' type='button' value='".wfMessage('give_all_comps_and_email')->escaped()."'/>
		</form>
		<table class='wikitable'>
			<thead>
				<tr>
					<th>".wfMessage('wpa_user')->escaped()."</th>
					<th>".wfMessage('comp_points')->escaped()."</th>
					<th>".wfMessage('comp_new')->escaped()."</th>
					<th>".wfMessage('comp_extended')->escaped()."</th>
					<th>".wfMessage('comp_failed')->escaped()."</th>
					<th>".wfMessage('comp_expires')->escaped()."</th>
					<th>".wfMessage('comp_done')->escaped()."</th>
					<th>".wfMessage('emailed')->escaped()."</th>
				</tr>
			</thead>
			<tbody>";
		$lookup = CentralIdLookup::factory();
		while (($reportRow = $report->getNextRow()) !== false) {
			$user = $lookup->localUserFromCentralId($reportRow['global_id']);
			$html .= "
				<tr>
					<td>".($user ? $user->getName() : 'GID: '.$reportRow['global_id'])."</td>
					<td>{$reportRow['points']}</td>
					<td>{$reportRow['comp_new']}</td>
					<td>{$reportRow['comp_extended']}</td>
					<td>{$reportRow['comp_failed']}</td>
					<td>".($reportRow['comp_expires'] > 0 ? gmdate('Y-m-d', $reportRow['comp_expires']) : '&nbsp;')."</td>
					<td>".($reportRow['comp_performed'] ? '✓' : 'DO IT BUTTON')."</td>
					<td>".($reportRow['email_sent'] ? '✓' : 'DO IT BUTTON')."</td>
				</tr>";
		}
		$html .= "
			</tbody>
		</table>";

		return $html;
	}
}
