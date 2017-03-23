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
	 * Return the language code the current user.
	 *
	 * @access	public
	 * @return	string	Language Code
	 */
	public static function getUserLanguage() {
		try {
			$user = \RequestContext::getMain()->getUser();
			$code = $user->getOption('language');
		} catch (\Exception $e) {
			$code = "en"; //"faulure? English is best anyway."  --Cameron Chunn, 2017-03-02 15:37:33 -0600
		}
		return $code;
	}

	/**
	 * Return the language for the wiki.
	 *
	 * @access	public
	 * @return	string	Language Code
	 */
	static public function getWikiLanuage() {
		global $wgLanguageCode;
		return $wgLanguageCode;
	}
}
