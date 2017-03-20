<?php
/**
 * Achievements
 * Cheevos Class
 *
 * @author		Cameron Chunn
 * @copyright	(c) 2017 Curse Inc.
 * @license		All Rights Reserved
 * @package		Achievements
 * @link		http://www.curse.com/
 *
 **/

namespace Cheevos;

class CheevosHelper {

	/**
	 * Undocumented function
	 *
	 * @return void
	 */
	public static function getUserLanguage() {
		global $wgLang;

		try {
			$code = $wgLang->getCode();
		} catch (Exception $e) {
			$code = "en"; // faulure? English is best anyway.
		}
		return $code;
	}
}
