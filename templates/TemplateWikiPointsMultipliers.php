<?php
/**
 * Curse Inc.
 * Cheevos
 * Wiki Points Multipliers Template
 *
 * @package   Cheevos
 * @author    Alexia E. Smith
 * @copyright (c) 2014 Curse Inc.
 * @license   GPL-2.0-or-later
 * @link      https://gitlab.com/hydrawiki/extensions/cheevos
**/

class TemplateWikiPointsMultipliers {
	/**
	 * Output HTML
	 *
	 * @var string
	 */
	private $HMTL;

	/**
	 * Points Multipliers List
	 *
	 * @access public
	 * @param  array	Multidimensional array of multipliers information
	 * @return string	Built HTML
	 */
	public static function pointsMultipliersList($multipliers) {
		global $wgOut, $wgUser, $wgRequest, $wgScriptPath;

		$pointsMultipliersPage	= Title::newFromText('Special:WikiPointsMultipliers');
		$pointsMultipliersURL	= $pointsMultipliersPage->getFullURL();

		$HTML = "
			<a href='{$pointsMultipliersURL}?section=form&amp;do=add' class='mw-ui-button mw-ui-constructive'>" . wfMessage('add_multiplier')->escaped() . "</a>
			<table class='wikitable'>
				<thead>
					<tr>
						<th>" . wfMessage('multiplier_multiplier')->escaped() . "</th>
						<th>" . wfMessage('multiplier_begins')->escaped() . "</th>
						<th>" . wfMessage('multiplier_expires')->escaped() . "</th>
						<th>" . wfMessage('wiki')->escaped() . "</th>
						<th colspan='2'>&nbsp;</th>
					</tr>
				</thead>
				<tbody>";
		if (is_array($multipliers) && count($multipliers)) {
			foreach ($multipliers as $multiplierId => $multiplier) {
				$wiki = false;
				if (!empty($multiplier->getSite_Key())) {
					$wiki = \DynamicSettings\Wiki::loadFromHash($multiplier->getSite_Key());
				}
				$HTML .= "
					<tr data-id='{$multiplierId}'>
						<td>" . $multiplier->getMultiplier() . "</td>
						<td>" . ($multiplier->getBegins() ? htmlentities(date('Y-m-d', $multiplier->getBegins()), ENT_QUOTES) : "&nbsp;") . "</td>
						<td>" . ($multiplier->getExpires() ? htmlentities(date('Y-m-d', $multiplier->getExpires()), ENT_QUOTES) : "&nbsp;") . "</td>
						<td>" . ($wiki !== false ? $wiki->getNameForDisplay() : '(' . wfMessage('all_wikis')->escaped() . ')') . "</td>
						<td><a href='{$pointsMultipliersURL}?section=form&amp;do=edit&amp;multiplier_id={$multiplier->getId()}' title='" . wfMessage('edit_multiplier')->escaped() . "' class='edit multiplier'>" . HydraCore::awesomeIcon('pencil') . "</a></td>
						<td><a href='{$pointsMultipliersURL}?section=delete&amp;do=delete&amp;multiplier_id={$multiplier->getId()}' title='" . wfMessage('delete_multiplier')->escaped() . "' class='delete multiplier'>" . HydraCore::awesomeIcon('minus-circle') . "</a></td>
					</tr>";
			}
		} else {
			$HTML .= "
					<tr>
						<td class='no_multipliers_found' colspan='6'>" . wfMessage('no_multipliers_found')->escaped() . "</td>
					</tr>";
		}
		$HTML .= "
				</tbody>
			</table>";

		return $HTML;
	}

	/**
	* Points Multiplier Form
	*
	* @access public
	* @param  array	Array of promo information.
	* @param  array	Wikis there are added/removed from this multiplier.
	* @param  array	Key name => Error of errors
	* @return string	Built HTML
	*/
	public static function pointsMultipliersForm($multiplier, $errors) {
		$html = "
			<form id='multiplier_form' method='post' action='?section=form&do=save'>
				<fieldset>
					" . (isset($errors['multiplier']) ? '<span class="error">' . $errors['multiplier'] . '</span>' : '') . "
					<label for='multiplier' class='label_above'>" . wfMessage('multiplier_multiplier')->escaped() . "</label>
					<input id='multiplier' name='multiplier' type='text' value='" . htmlentities($multiplier->getMultiplier(), ENT_QUOTES) . "' />

					" . (isset($errors['begins']) ? '<span class="error">' . $errors['begins'] . '</span>' : '') . "
					<label for='begins' class='label_above'>" . wfMessage('multiplier_begins')->escaped() . "</label>
					<input id='begins_datepicker' data-input='begins' type='text' value=''/>
					<input id='begins' name='begins' type='hidden' value='" . htmlentities($multiplier->getBegins(), ENT_QUOTES) . "'/>

					" . (isset($errors['expires']) ? '<span class="error">' . $errors['expires'] . '</span>' : '') . "
					<label for='expires' class='label_above'>" . wfMessage('multiplier_expires')->escaped() . "</label>
					<input id='expires_datepicker' data-input='expires' type='text' value=''/>
					<input id='expires' name='expires' type='hidden' value='" . htmlentities($multiplier->getExpires(), ENT_QUOTES) . "'/>

					<label for='wiki_search' class='label_above'>" . wfMessage('limit_to_wiki')->escaped() . "</label>
					<div id='wiki_selection_container'>
						<input class='wiki_selections' name='wikis' data-select-key='wikipointsmultipliers' data-select-type='single' type='hidden' value='" . json_encode(['single' => $multiplier->getSite_Key()]) . "'/>
					</div>
				</fieldset>
				<fieldset class='submit'>
					<input id='multiplier_id' name='multiplier_id' type='hidden' value='{$multiplier->getId()}'/>
					<input id='wiki_submit' name='wiki_submit' type='submit' value='Save'/>
				</fieldset>
			</form>";

		return $html;
	}

	/**
	 * Points Multiplier Deletion Form
	 *
	 * @access public
	 * @param  object	PointsMultipler object.
	 * @return string	Built HTML
	 */
	public static function pointsMultipliersDelete($multiplier) {
		$pointsMultipliersPage	= Title::newFromText('Special:WikiPointsMultipliers');
		$pointsMultipliersURL	= $pointsMultipliersPage->getFullURL();

		$HTML .= "
		<div>
			" . wfMessage('delete_multiplier_confirm') . "<br/>
			<form method='post' action='{$pointsMultipliersURL}?section=delete&do=delete&multiplier_id={$multiplier->getId()}&confirm=true'>
				<input type='submit' class='mw-ui-button mw-ui-destructive' value='" . wfMessage('delete_multiplier')->escaped() . "'/>
			</form>
		</div>
		";

		return $HTML;
	}
}
