<?php
/**
 * Cheevos
 * Find Existing WikiPoints
 *
 * @author		Wiki Platform Team
 * @copyright	(c) 2017 Curse Inc.
 * @license		All Rights Reserved
 * @package		Cheevos
 * @link		http://www.curse.com/
 *
 **/

require_once(dirname(dirname(dirname(__DIR__)))."/maintenance/Maintenance.php");

class FindExistingWikiPoints extends Maintenance {
	/**
	 * Constructor
	 *
	 * @access	public
	 * @return	void
	 */
	public function __construct() {
		parent::__construct();

		$this->mDescription = "Handles back logging WikiPoints achievements based on historical data.";
	}

	/**
	 * Processes achievements that may need to be awarded to people.
	 *
	 * @access	public
	 * @return	void
	 */
	public function execute() {
		global $wgMetaNamespace, $dsSiteKey;

		$this->DB = wfGetDB(DB_MASTER);

		$result = $this->DB->select(
			['wiki_points'],
			['count(*) AS total'],
			null,
			__METHOD__,
			[
				'GROUP BY' => 'user_id'
			]
		);
		$total = $result->fetchRow();
		$total = intval($total['total']);

		for ($i = 0; $i <= $total; $i = $i + 1000) {
			$result = $this->DB->select(
				['wiki_points'],
				['user_id'],
				null,
				__METHOD__,
				[
					'OFFSET'	=> $i,
					'LIMIT'		=> 1000,
					'GROUP BY' => 'user_id'
				]
			);

			foreach ($result as $user) {
				if (!$user->user_id) {
					continue;
				}

				$user = User::newFromId($user->user_id);
				if ($user !== false && $user->getId()) {
					$this->output("\nChecking user ".$user->getName()."\n");
					$this->wikiPoints($user);
				}
			}
		}
	}

	/**
	 * Do any awards for WikiPoints category.
	 *
	 * @access	private
	 * @param	object	User
	 * @return	void
	 */
	private function wikiPoints($user) {
		$hashes = [
			'c722bcde56a731bf5a428cd002017944',
			'ff6e2c2a5bfc6adb9b19834078b60bcc',
			'0e9b8997ea70842c765e924e3eaccfd2',
			'760fda84c4bf32ddc64be9ebfb617cdb',
			'73583f8ccf81a83bc3c90ad9ac74bf4e',
			'df3fc983590d6c77f6aaacad10fe1c4f',
			'1f42c336e8b47a7545445a622ea79919',
			'45c670f9d925434a0106c9359cc4d01f',
			'a77bc45ca06e3ecf72c87c3e7ab185ef'
		];

		$result = $this->DB->select(
			['wiki_points'],
			[
				'user_id',
				'SUM(score) as points'
			],
			[
				'user_id'		=> $user->getId(),
			],
			__METHOD__,
			[
				'GROUP BY' => 'user_id'
			]
		);
		$result = $result->fetchRow();
		$points = intval($result['points']);

		if ($points > 0) {
			$this->output("Found {$points} points for {$result['user_id']}\n");
			foreach ($hashes as $hash) {
				$achievement = \Cheevos\Achievement::newFromHash($hash);
				if ($achievement !== false) {
					$achievement->unaward($user, true);
					$achievement->award($user, $points);
				}
			}
		}
	}
}

$maintClass = 'FindExistingWikiPoints';
require_once(RUN_MAINTENANCE_IF_MAIN);
