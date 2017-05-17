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
					<th>".wfMessage('threshold')->escaped()."</th>
					<th>".wfMessage('month_start')->escaped()."</th>
					<th>".wfMessage('month_end')->escaped()."</th>
				</tr>
			</thead>
			<tbody>";
		if (count($reports)) {
			foreach ($reports as $report) {
				$html .= "
				<tr>
					<td>{$report->getPointThreshold()}</td>
					<td>{$report->getMonthStart()}</td>
					<td>{$report->getMonthEnd()}</td>
				</tr>";
			}
		}
		$html .= "
			</tbody>
		</table>";

		return $html;
	}
}
