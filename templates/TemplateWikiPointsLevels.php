<?php
/**
 * Curse Inc.
 * Wiki Points
 * Wiki Points Levels Template
 *
 * @author		Alexia E. Smith
 * @copyright	(c) 2014 Curse Inc.
 * @license		All Rights Reserved
 * @package		Wiki Points
 * @link		http://www.curse.com/
 *
**/

class TemplateWikiPointsLevels {
	/**
	 * Report Card Form
	 *
	 * @access	public
	 * @param	array	[Optional] Array of existing levels.
	 * @param	array	[Optional] Key name => Error of errors
	 * @param	boolean	[Optional] If the save was successful.
	 * @return	string	Built HTML
	 */
	public function levelsForm($levels = array(), $errors = array(), $success = false) {
		global $wgScriptPath;

		if ($success) {
			$HTML .= "
		<div class='successbox'>
			<strong><p>".wfMessage('levels_updated')."</p></strong>
		</div>";
		}

		$HTML .= "
		<form id='levels_form' method='post' action='?do=save'>
			<fieldset>
				<legend>".wfMessage('modify_levels')." <input class='add_level' name='add_level' type='button' value='".wfMessage('add_level')."'/></legend>
				<div class='level_head'><span>".wfMessage('points')."</span><span>".wfMessage('level_text')."</span><span>".wfMessage('image_icon')."</span><span>".wfMessage('image_large')."</span></div>
				".($errors['level'] ? '<span class="error">'.$errors['level'].'</span>' : '');
		if (is_array($levels) and count($levels)) {
			foreach ($levels as $lid => $level) {
				$HTML .= "<div class='level'><input name='lid[]' value='{$level['lid']}' type='hidden'/><input name='points[]' value='{$level['points']}' type='text'/><input name='text[]' value='".htmlentities($level['text'], ENT_QUOTES)."' type='text'/><input name='image_icon[]' value='".htmlentities($level['image_icon'], ENT_QUOTES)."' type='text'/><input name='image_large[]' value='".htmlentities($level['image_large'], ENT_QUOTES)."' type='text'/><img src='".wfExpandUrl("{$wgScriptPath}/extensions/WikiPoints/images/delete.png")."' class='delete_level'></div>";
			}
		}

		$HTML .= "
			</fieldset>
			<fieldset>
				".wfMessage('levels_explanation')."<br/>
				<input id='levels_submit' type='submit' value='".wfMessage('save_levels')."'/>
			</fieldset>
		</form>
		";

		return $HTML;
	}
}