<?php
/**
 * Cheevos
 * Find Existing Friends
 *
 * @author		Wiki Platform Team
 * @copyright	(c) 2017 Curse Inc.
 * @license		All Rights Reserved
 * @package		Cheevos
 * @link		http://www.curse.com/
 *
 **/

require_once(dirname(dirname(dirname(__DIR__)))."/maintenance/Maintenance.php");

class FindExistingFriends extends Maintenance {
	/**
	 * Constructor
	 *
	 * @access	public
	 * @return	void
	 */
	public function __construct() {
		parent::__construct();

		$this->mDescription = "Handles back logging Friends achievements based on historical data.";
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
			['user'],
			['count(*) AS total'],
			null,
			__METHOD__
		);
		$total = $result->fetchRow();
		$total = intval($total['total']);

		for ($i = 0; $i <= $total; $i = $i + 1000) {
			$result = $this->DB->select(
				['user'],
				['*'],
				null,
				__METHOD__,
				[
					'OFFSET'	=> $i,
					'LIMIT'		=> 1000
				]
			);

			foreach ($result as $user) {
				$lookup = CentralIdLookup::factory();
				$globalId = $lookup->centralIdFromLocalUser($user, CentralIdLookup::AUDIENCE_RAW);
				if (!$globalId) {
					continue;
				}

				$user = User::newFromRow($user);
				if ($user !== false && $user->getId()) {
					$this->output("\nChecking user ".$user->getName()."\n");
					$this->friends($user);
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
	private function friends($user) {
		$hashes = [
			'0a482a5627931f2c625c4143b500cd8f',
			'bb123fd82de4704dca67a68df8899044'
		];

		$friends = \CurseProfile\FriendDisplay::count($nothing, $user->getId());

		$this->output("Found {$friends} friends for {$user->getId()}\n");

		if ($friends > 0) {
			foreach ($hashes as $hash) {
				$achievement = \Cheevos\Achievement::newFromHash($hash);
				if ($achievement !== false) {
					$achievement->award($user, $friends);
				}
			}
		}
	}
}

$maintClass = 'FindExistingFriends';
require_once(RUN_MAINTENANCE_IF_MAIN);
