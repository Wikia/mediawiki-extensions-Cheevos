<?php
/**
 * Curse Inc.
 * Wiki Points
 * Wiki Points Multipliers Template
 *
 * @author		Alexia E. Smith
 * @copyright	(c) 2014 Curse Inc.
 * @license		All Rights Reserved
 * @package		Wiki Points
 * @link		http://www.curse.com/
 *
**/

class TemplateWikiPointsMultipliers {
	/**
	 * Output HTML
	 *
	 * @var		string
	 */
	private $HMTL;

	/**
	 * Points Multipliers List
	 *
	 * @access	public
	 * @param	array	Multidimensional array of multipliers information
	 * @return	string	Built HTML
	 */
	public function pointsMultipliersList($multipliers) {
		global $wgOut, $wgUser, $wgRequest, $wgScriptPath;

		$pointsMultipliersPage	= Title::newFromText('Special:WikiPointsMultipliers');
		$pointsMultipliersURL	= $pointsMultipliersPage->getFullURL();

		$HTML = "
			<div class='buttons'>
				<a href='{$pointsMultipliersURL}?section=form&amp;do=add' class='button'>".wfMessage('add_multiplier')->escaped()."</a>
			</div>
			<table id='multiplierlist'>
				<thead>
					<tr>
						<th>".wfMessage('multiplier_multiplier')->escaped()."</th>
						<th>".wfMessage('multiplier_begins')->escaped()."</th>
						<th>".wfMessage('multiplier_expires')->escaped()."</th>
						<th class='controls wide'>&nbsp;</th>
					</tr>
				</thead>
				<tbody>";
		if (is_array($multipliers) && count($multipliers)) {
 			foreach ($multipliers as $multiplierId => $multiplier) {
	  			$HTML .= "
					<tr data-id='{$multiplierId}'>
						<td>".$multiplier->getMultiplier()."</td>
						<td>".($multiplier->getBegins() ? htmlentities(date('Y-m-d', $multiplier->getBegins()), ENT_QUOTES) : "&nbsp;")."</td>
						<td>".($multiplier->getExpires() ? htmlentities(date('Y-m-d', $multiplier->getExpires()), ENT_QUOTES) : "&nbsp;")."</td>
						<td class='controls wide'>
							<a href='{$pointsMultipliersURL}?section=form&amp;do=edit&amp;multiplier_id={$multiplier->getDatabaseId()}' title='".wfMessage('edit_multiplier')->escaped()."' class='edit multiplier'><img src='".wfExpandUrl($wgScriptPath."/extensions/DynamicSettings/images/edit.png")."'/></a>
							<a href='{$pointsMultipliersURL}?section=delete&amp;do=delete&amp;multiplier_id={$multiplier->getDatabaseId()}' title='".wfMessage('delete_multiplier')->escaped()."' class='delete multiplier'><img src='".wfExpandUrl($wgScriptPath."/extensions/DynamicSettings/images/delete.png")."'/></a>
						</td>
					</tr>";
 			}
		} else {
			$HTML .= "
					<tr>
						<td class='no_multipliers_found' colspan='4'>".wfMessage('no_multipliers_found')->escaped()."</td>
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
	* @access	public
	* @param	array	Array of promo information.
	* @param	array	Wikis there are added/removed from this multiplier.
	* @param	array	Key name => Error of errors
	* @return	string	Built HTML
	*/
	public function pointsMultipliersForm($multiplier, $wikis, $errors) {
		$HTML .= "
			<form id='multiplier_form' method='post' action='?section=form&do=save'>
				<fieldset>
					".($errors['multiplier'] ? '<span class="error">'.$errors['multiplier'].'</span>' : '')."
					<label for='multiplier' class='label_above'>".wfMessage('multiplier_multiplier')->escaped()."</label>
					<input id='multiplier' name='multiplier' type='text' value='".htmlentities($multiplier->getMultiplier(), ENT_QUOTES)."' />

					".($errors['begins'] ? '<span class="error">'.$errors['begins'].'</span>' : '')."
					<label for='begins' class='label_above'>".wfMessage('multiplier_begins')->escaped()."</label>
					<input id='begins_datepicker' data-input='begins' type='text' value=''/>
					<input id='begins' name='begins' type='hidden' value='".htmlentities($multiplier->getBegins(), ENT_QUOTES)."'/>

					".($errors['expires'] ? '<span class="error">'.$errors['expires'].'</span>' : '')."
					<label for='expires' class='label_above'>".wfMessage('multiplier_expires')->escaped()."</label>
					<input id='expires_datepicker' data-input='expires' type='text' value=''/>
					<input id='expires' name='expires' type='hidden' value='".htmlentities($multiplier->getExpires(), ENT_QUOTES)."'/>

					<label for='wiki_search' class='label_above'>".wfMessage('add_or_remove_wikis')->escaped()."</label>
					<div id='wiki_selection_container'>
						<input class='wiki_selections' name='wikis' data-select-key='wikipointsmultipliers' data-select-type='addremove' type='hidden' value='".(is_array($wikis) && count($wikis) ? json_encode($wikis) : '[]')."'/>
						<input type='hidden' class='everywhere' name='everywhere' value='".($multiplier->isEnabledEverywhere() ? 1 : 0)."'>
					</div>
				</fieldset>
				<fieldset class='submit'>
					<input id='multiplier_id' name='multiplier_id' type='hidden' value='{$multiplier->getDatabaseId()}'/>
					<input id='wiki_submit' name='wiki_submit' type='submit' value='Save'/>
				</fieldset>
			</form>";

	return $HTML;
	}

	/**
	 * Points Multiplier Deletion Form
	 *
	 * @access	public
	 * @param	object	PointsMultipler object.
	 * @return	string	Built HTML
	 */
	public function pointsMultipliersDelete($multiplier) {
		$pointsMultipliersPage	= Title::newFromText('Special:WikiPointsMultipliers');
		$pointsMultipliersURL	= $pointsMultipliersPage->getFullURL();

		$HTML .= "
		<div>
			".wfMessage('delete_multiplier_confirm')."<br/>
			<a href='{$pointsMultipliersURL}?section=delete&do=delete&multiplier_id={$multiplier->getDatabaseId()}&confirm=true' class='button'>".wfMessage('delete_multiplier')->escaped()."</a>
		</div>
		";

		return $HTML;
	}
}
