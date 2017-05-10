<?php
/**
 * Curse Inc.
 * Cheevos
 * Points Multipliers Class
 *
 * @author		Alexia E. Smith
 * @copyright	(c) 2014 Curse Inc.
 * @license		All Rights Reserved
 * @package		Wiki Points
 * @link		http://www.curse.com/
 *
**/

namespace Cheevos\Points;

class PointsMultiplier {
	/**
	 * Multiplier Container
	 *
	 * @var		array
	 */
	private $data = [];

	/**
	 * Load all Multiplier objects.
	 *
	 * @access	public
	 * @param	string	[Optional] Column to sort by.
	 * @param	string	[Optional] Sort direction.
	 * @return	mixed	Array of Multiplier objects or false for no results.
	 */
	static public function loadAll($sortKey = 'multiplier', $sortDir = 'ASC') {
		$multipliers = false;

		try {
			$promotions = \Cheevos\Cheevos::getPointsPromotions(null, true);
		} catch (\Cheevos\CheevosException $e) {
			return false;
		}

		return $promotions;
	}

	/**
	 * Load a new PointsMultiplier object from a multiplier database ID.
	 *
	 * @access	public
	 * @param	integer	Multiplier Database ID
	 * @return	mixed	Multiplier object or false on failure.
	 */
	static public function loadFromId($multiplierId) {
		$multiplier = new PointsMultiplier();

		$results = $multiplier->DB->select(
			['wiki_points_multipliers', 'wiki_points_multipliers_sites'],
			['*'],
			'wiki_points_multipliers.mid = '.intval($multiplierId),
			__METHOD__,
			[],
			[
				'wiki_points_multipliers_sites' => [
					'LEFT JOIN', 'wiki_points_multipliers_sites.multiplier_id = wiki_points_multipliers.mid'
				]
			]
		);

		$_multiplier = [];
		while ($row = $results->fetchRow()) {
			if (array_key_exists('mid', $_multiplier)) {
				$_multiplier['wikis'][] = ['site_key' => $row['site_key'], 'override' => intval($row['override'])];
				continue;
			}
			$_multiplier = $row;
			$_multiplier['wikis'][] = ['site_key' => $row['site_key'], 'override' => intval($row['override'])];
		}

		if (!$multiplier->load($_multiplier)) {
			$multiplier = false;
		}
		unset($_multiplier);

		return $multiplier;
	}

	/**
	 * Load a new PointsMultiplier object from Wiki object.
	 *
	 * @access	public
	 * @param	object	Wiki
	 * @return	mixed	Multiplier object or false on failure.
	 */
	static public function loadFromWiki(\DynamicSettings\Wiki $wiki) {
		$multipliers = false;
		$multiplier = new PointsMultiplier();

		$results = $multiplier->DB->select(
			['wiki_points_multipliers_sites'],
			['*'],
			"wiki_points_multipliers_sites.site_key = '".$multiplier->DB->strencode($wiki->getSiteKey())."'",
			__METHOD__
		);

		$show = [];
		$hide = [];
		while ($row = $results->fetchRow()) {
			if ($row['override'] == 1) {
				$show[] = $row['multiplier_id'];
			}
			if ($row['override'] == -1) {
				$hide[] = $row['multiplier_id'];
			}
		}

		$results = $multiplier->DB->select(
			['wiki_points_multipliers'],
			['*'],
			(count($show) ? "mid IN(".implode(', ', $show).") OR " : null)."(everywhere = 1".(count($hide) ? " AND mid NOT IN(".implode(', ', $hide).")" : null).")",
			__METHOD__,
			[
				'ORDER BY'	=> 'weight ASC',
				'GROUP BY'	=> 'mid',
			]
		);

		$_multipliers = [];
		while ($row = $results->fetchRow()) {
			if (array_key_exists($row['mid'], $_multipliers)) {
				$_multipliers[$row['mid']]['wikis'][] = ['site_key' => $row['site_key'], 'override' => intval($row['override'])];
				continue;
			}
			$_multipliers[$row['mid']] = $row;
			$_multipliers[$row['mid']]['wikis'][] = ['site_key' => $row['site_key'], 'override' => intval($row['override'])];
		}

		foreach ($_multipliers as $mid => $_multiplier) {
			$multiplier = new PointsMultiplier();
			if ($multiplier->load($_multiplier) === true) {
				$multipliers[$mid] = $multiplier;
			}
			unset($_multipliers[$mid]);
		}

		return $multipliers;
	}

	/**
	 * Returns a new PointsMultiplier object.
	 *
	 * @access	public
	 * @return	object	Fresh Namespace
	 */
	static public function loadFromNew() {
		$multiplier = new PointsMultiplier();

		return $multiplier;
	}

	/**
	 * Loads multipliers for this wiki.
	 *
	 * @access	public
	 * @param	array	Fetched database row array.
	 * @return	boolean	Success
	 */
	public function load($row) {
		if (!$row['mid']) {
			return false;
		}

		$this->data = [
			'mid'			=> $row['mid'],
			'multiplier'	=> $row['multiplier'],
			'begins'		=> $row['begins'],
			'expires'		=> $row['expires'],
			'everywhere'	=> (bool) $row['everywhere'],
			'wikis'			=> $row['wikis']
		];

		return true;
	}

	/**
	 * Save multiplier to the database.
	 *
	 * @access	public
	 * @return	boolean	Success
	 */
	public function save() {
		$multiplierSites = $this->data['wikis'];

		$save = [
			'mid'			=> $this->data['mid'],
			'multiplier'	=> $this->data['multiplier'],
			'begins'		=> $this->data['begins'],
			'expires'		=> $this->data['expires'],
			'everywhere'	=> intval($this->data['everywhere'])
		];

		if ($this->data['mid']) {
			$this->DB->update(
				'wiki_points_multipliers',
				$save,
				['mid' => $this->data['mid']],
				__METHOD__
			);

			$multiplierID = $this->data['mid'];
		} else {
			$return = $this->DB->insert(
				'wiki_points_multipliers',
				$save,
				__METHOD__
			);

			$multiplierID = $this->DB->insertId();
		}

		//Time to delete the linking table.
		$this->DB->delete(
			'wiki_points_multipliers_sites',
			['multiplier_id' => $multiplierID],
			__METHOD__
		);

		//Regenerate data for linking table.
		foreach ($multiplierSites as $data) {
			$wikis[] = [
				'multiplier_id'	=> $multiplierID,
				'site_key'		=> $data['site_key'],
				'override'		=> $data['override']
			];
		}

		$this->DB->insert(
			'wiki_points_multipliers_sites',
			$wikis,
			__METHOD__
		);
		$this->DB->commit();

		$this->data['mid'] = $multiplierID;

		$this->updateCache();

		return true;
	}

	/**
	 * Function Documentation
	 *
	 * @access	public
	 * @return	void
	 */
	public function updateCache() {
		$save = [
			'mid'			=> $this->data['mid'],
			'multiplier'	=> $this->data['multiplier'],
			'begins'		=> $this->data['begins'],
			'expires'		=> $this->data['expires'],
			'everywhere'	=> intval($this->data['everywhere']),
			'wikis'			=> $this->data['wikis']
		];

		if ($this->data['everywhere']) {
			$this->redis->hSet('wikipoints:multiplier:everywhere', $save['mid'], serialize($save));
		}
		foreach ($this->data['wikis'] as $data) {
			if (is_array($data) && $data['override'] == 1) {
				$this->redis->set('wikipoints:multiplier:'.$data['site_key'], serialize($save));
				if ($save['expires']) {
					$this->redis->expire('wikipoints:multiplier:'.$data['site_key'], $save['expires'] - time());
				}
			}
		}
	}

	/**
	 * Return the database ID.
	 *
	 * @access	public
	 * @return	integer	Database ID
	 */
	public function getDatabaseId() {
		return ($this->data['mid'] ? $this->data['mid'] : false);
	}

	/**
	 * Return multiplier float.
	 *
	 * @access	public
	 * @return	float	Multiplier
	 */
	public function getMultiplier() {
		return floatval($this->data['multiplier']);
	}

	/**
	 * Set multiplier name.
	 *
	 * @access	public
	 * @param	string	Name
	 * @return	boolean	Success
	 */
	public function setMultiplier($multiplier) {
		$multiplier = floatval($multiplier);
		if (empty($multiplier)) {
			return false;
		}
		$this->data['multiplier'] = $multiplier;
		return true;
	}

	/**
	 * Return multiplier begins.
	 *
	 * @access	public
	 * @return	string	Begins
	 */
	public function getBegins() {
		return $this->data['begins'];
	}

	/**
	 * Set multiplier begins.
	 *
	 * @access	public
	 * @param	string	Begins
	 * @return	boolean	Success
	 */
	public function setBegins($begins) {
		if (empty($begins)) {
			$this->data['begins'] = null;
		} else {
			$this->data['begins'] = intval($begins);
		}
		return true;
	}

	/**
	 * Return multiplier expires.
	 *
	 * @access	public
	 * @return	string	Expires
	 */
	public function getExpires() {
		return $this->data['expires'];
	}

	/**
	 * Set multiplier expires.
	 *
	 * @access	public
	 * @param	string	Expires
	 * @return	boolean	Success
	 */
	public function setExpires($expires) {
		if (empty($expires)) {
			$this->data['expires'] = null;
		} else {
			$this->data['expires'] = intval($expires);
		}
		return true;
	}

	/**
	 * Return all the selected wiki information for this multiplier.
	 *
	 * @access	public
	 * @return	array	Wiki Information
	 */
	public function getWikis() {
		return $this->data['wikis'];
	}

	/**
	 * Clear the assigned wikis.
	 *
	 * @access	public
	 * @return	void
	 */
	public function clearWikis() {
		$this->data['wikis'] = [];
	}

	/**
	 * Assign a wiki to this multiplier.
	 *
	 * @access	public
	 * @param	string	Site Key for the wiki to add.
	 * @param	integer	[Optional] Override value.  -1 = Do not show on this regardless of being enabled everywhere.  0 = Default, show on this wiki.  1 = Show on this wiki regardless if disabled everywhere.
	 * @return	boolean	Success
	 */
	public function addWiki($siteKey, $override = 0) {
		if (\DynamicSettings\Wiki::exists($siteKey)) {
			$this->data['wikis'][] = ['site_key' => $siteKey, 'override' => intval($override)];
			return true;
		}
		return false;
	}

	/**
	 * Return if this multiplier is enabled everywhere.
	 *
	 * @access	public
	 * @return	boolean	Enabled Everywhere
	 */
	public function isEnabledEverywhere() {
		return (bool) $this->data['everywhere'];
	}

	/**
	 * Set if this multiplier is enabled everywhere.
	 *
	 * @access	public
	 * @param	boolean	[Optional] Enabled Everywhere, default true.
	 * @return	void
	 */
	public function setEnabledEverywhere($enabled = true) {
		return $this->data['everywhere'] = (bool) $enabled;
	}
}
