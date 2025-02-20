<?php
/**
 * Achievements
 * Cheevos Exception Class
 *
 * @package   Cheevos
 * @author    Cameron Chunn
 * @copyright (c) 2017 Curse Inc.
 * @license   GPL-2.0-or-later
 * @link      https://gitlab.com/hydrawiki/extensions/cheevos
 */

namespace Cheevos;

use Exception;

class CheevosException extends Exception {
	/**
	 * Constructor for Exception
	 *
	 * @param string $message
	 * @param int $code
	 * @param Exception|null $previous
	 */
	public function __construct( string $message, int $code = 0, ?Exception $previous = null ) {
		parent::__construct( $message, $code, $previous );
	}

	/**
	 * Return a string of the exception message and code
	 */
	public function __toString(): string {
		return __CLASS__ . ": [$this->code]: $this->message\n";
	}
}
