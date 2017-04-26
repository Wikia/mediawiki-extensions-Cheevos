<?php
/**
 * Curse Inc.
 * Wiki Points
 * A contributor scoring system
 *
 * @author		Noah Manneschmidt
 * @copyright	(c) 2014 Curse Inc.
 * @license		All Rights Reserved
 * @package		Wiki Points
 * @link		http://www.curse.com/
 *
**/

namespace Cheevos\Points;

/**
 * Class containing some business and display logic for points blocks
 */
class PointsDisplay {
	/**
	 * Displays scores of wiki editors in an HTML table
	 * {{#GPScore}} displays top 25 scoring users from this wiki
	 * {{#GPScore: 10}} displays top 10 scoring users from this wiki
	 * {{#GPScore: 10 | all}} displays top 10 scoring users from all wikis
	 * {{#GPScore: User:Cathadan}} displays my score from this wiki
	 * {{#GPScore: 50 | destiny,dota2,theorder1886}} displays top 50 scoring users with points from any of those three wikis
	 * {{#GPScore: User:Cathadan | destiny,dota2,theorder1886}} displays the sum of my scores from the given wikis
	 *
	 * TODO: break this function down, make it easier to read
	 *
	 * @param	Parser	mediawiki Parser reference
	 * @param	mixed	[optional, default: 25, max 500] number of top users to display or string username
	 * @param	string	[optional, default: ''] comma separated list of wiki namespaces, defaults to the current wiki
	 * @param	string	[optional, default: 'table'] determines what type of markup is used for the output,
	 * 					'raw' returns an unformatted number for a single user and is ignored for multi-user results
	 *					'badged' returns the same as raw, but with the GP badge branding following it in an <img> tag
	 * 					'table' uses an unstyled HTML table
	 * @return	array	generated HTML string as element 0, followed by parser options
	 */
	public static function pointsBlock(&$parser, $users = 25, $wikis = '', $markup = 'table') {


		return [
			$HTML,
			'isHTML' => true,
		];
	}
}
