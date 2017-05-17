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
					<th>".wfMessage('point_threshold')->escaped()."</th>
					<th>".wfMessage('month_start')->escaped()."</th>
					<th>".wfMessage('month_end')->escaped()."</th>
					<th>".wfMessage('total_new')->escaped()."</th>
					<th>".wfMessage('total_extended')->escaped()."</th>
				</tr>
			</thead>
			<tbody>";
		if (count($reports)) {
			foreach ($reports as $report) {
				$html .= "
				<tr>
					<td>{$report->getPointThreshold()}</td>
					<td>".gmdate('Y-m-d', $report->getMonthStart())."</td>
					<td>".gmdate('Y-m-d', $report->getMonthEnd())."</td>
					<td>{$report->getTotalNew()}</td>
					<td>{$report->getTotalExtended()}</td>
				</tr>";
			}
		}
		$html .= "
			</tbody>
		</table>";

		return $html;
	}
}
