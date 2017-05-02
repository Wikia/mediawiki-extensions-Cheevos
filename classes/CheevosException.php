<?php
/**
 * Achievements
 * Cheevos Exception Class
 *
 * @author		Cameron Chunn
 * @copyright	(c) 2017 Curse Inc.
 * @license		All Rights Reserved
 * @package		Cheevos
 * @link		http://www.curse.com/
 *
 **/

namespace Cheevos;

class CheevosException extends \MWException {

	/**
	 * Constructor for Exception
	 *
	 * @access	public
	 * @param	string	$message
	 * @param	integer	$code
	 * @param	object	Exception $previous
	 */
	public function __construct($message, $code = 0, \Exception $previous = null) {
		parent::__construct($message, $code, $previous);
	}

	/**
	 * Return a string of the exception message and code
	 *
	 * @return string
	 */
	public function __toString() {
		return __CLASS__ . ": [{$this->code}]: {$this->message}\n";
	}
}
