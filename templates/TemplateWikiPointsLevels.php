<?php
/**
 * Curse Inc.
 * Cheevos
 * Wiki Points Levels Template
 *
 * @package   Cheevos
 * @author    Alexia E. Smith
 * @copyright (c) 2014 Curse Inc.
 * @license   GPL-2.0-or-later
 * @link      https://gitlab.com/hydrawiki/extensions/cheevos
**/

class TemplateWikiPointsLevels {
	/**
	 * Report Card Form
	 *
	 * @access public
	 * @param  array	[Optional] Array of existing levels.
	 * @param  array	[Optional] Key name => Error of errors
	 * @param  boolean	[Optional] If the save was successful.
	 * @return string	Built HTML
	 */
	public function levelsForm($levels = [], $errors = [], $success = false) {
		global $wgScriptPath;

		$html = '';

		if (is_bool($success)) {
			if ($success) {
				$html .= "
		<div class='successbox'>
			<strong><p>" . wfMessage('levels_updated')->escaped() . "</p></strong>
		</div>";
			} else {
				$html .= "
		<div class='errorbox'>
			<strong><p>" . wfMessage('levels_update_error')->escaped() . "</p></strong>
		</div>";
			}
		}

		$html .= "
		<form id='levels_form' method='post' action='?do=save'>
			<fieldset>
				<legend>" . wfMessage('modify_levels') . " <input class='add_level' name='add_level' type='button' value='" . wfMessage('add_level') . "'/></legend>
				<div class='level_head'><span>" . wfMessage('points') . "</span><span>" . wfMessage('level_text') . "</span><span>" . wfMessage('image_icon') . "</span><span>" . wfMessage('image_large') . "</span></div>
				" . (isset($errors['level']) ? '<span class="error">' . $errors['level'] . '</span>' : '');
		if (is_array($levels) and count($levels)) {
			foreach ($levels as $lid => $level) {
				$html .= "<div class='level'><input name='lid[]' value='{$level['lid']}' type='hidden'/><input name='points[]' value='{$level['points']}' type='text'/><input name='text[]' value='" . htmlentities($level['text'], ENT_QUOTES) . "' type='text'/><input name='image_icon[]' value='" . htmlentities($level['image_icon'], ENT_QUOTES) . "' type='text'/><input name='image_large[]' value='" . htmlentities($level['image_large'], ENT_QUOTES) . "' type='text'/><img src='" . wfExpandUrl("{$wgScriptPath}/extensions/Cheevos/images/delete.png") . "' class='delete_level'></div>";
			}
		}

		$html .= "
			</fieldset>
			<fieldset>
				" . wfMessage('levels_explanation') . "<br/>
				<input id='levels_submit' type='submit' value='" . wfMessage('save_levels') . "'/>
			</fieldset>
		</form>";

		return $html;
	}
}
