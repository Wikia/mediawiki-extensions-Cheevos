<?php
/**
 * Cheevos
 * Cheevos Hooks
 *
 * @author		Wiki Platform Team
 * @copyright	(c) 2017 Curse Inc.
 * @license		All Rights Reserved
 * @package		Cheevos
 * @link		http://www.curse.com/
 *
 **/

class CheevosHooks {

	
	static public function onRegistration() {
		// load the Cheevo's Client code.
		require_once(__DIR__.'/cheevos-client/autoload.php');
	}

}
