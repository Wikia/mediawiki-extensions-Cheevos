<?php
/**
 * Cheevos
 * Find Existing Achievements
 *
 * @author		Wiki Platform Team
 * @copyright	(c) 2017 Curse Inc.
 * @license		All Rights Reserved
 * @package		Cheevos
 * @link		http://www.curse.com/
 *
 **/

require_once(dirname(dirname(dirname(__DIR__)))."/maintenance/Maintenance.php");

class FindExistingAchievements extends Maintenance {
	/**
	 * Mediawiki start timestamp.
	 *
	 * @var		string
	 */
	private $start = null;

	/**
	 * Mediawiki end timestamp.
	 *
	 * @var		string
	 */
	private $end = null;

	/**
	 * Constructor
	 *
	 * @access	public
	 * @return	void
	 */
	public function __construct() {
		parent::__construct();

		$this->mDescription = "Handles back logging achievements based on historical data.";

		$this->addOption('start', 'Unix Epoch timestamp to start from for pulling historical data.', false, true);
		$this->addOption('end', 'Unix Epoch timestamp to end at for pulling historical data.', false, true);
		$this->addOption('user', 'User name to limit the user look up to.', false, true);
	}

	/**
	 * Processes achievements that may need to be awarded to people.
	 *
	 * @access	public
	 * @return	void
	 */
	public function execute() {
		global $wgMetaNamespace, $dsSiteKey;

		\Cheevos\SiteMegaUpdate::queue(['site_key' => $dsSiteKey]);
		$this->output("Queueing mega update and waiting five seconds...\n");
		sleep(5);

		$this->DB = wfGetDB(DB_MASTER);

		$this->redis = RedisCache::getClient('cache');
		$maintenanceKey = $wgMetaNamespace.':achievement:maintenance';

		try {
			$this->redis->set($maintenanceKey, 1);
			$this->redis->expire($maintenanceKey, 86400); //Safety in case this script fails to remove the key.

			$start = false;
			if ($this->hasOption('start')) {
				$start = wfTimestamp(TS_MW, $this->getOption('start'));
				if ($start === false) {
					$this->error("Invalid timestamp passed for parameter start.");
					exit;
				}
				$this->start = $start;
			}

			$end = false;
			if ($this->hasOption('end')) {
				$end = wfTimestamp(TS_MW, $this->getOption('end'));
				if ($end === false) {
					$this->error("Invalid timestamp passed for parameter end.");
					exit;
				}
				$this->end = $end;
			}

			$userWhere = null;
			if ($this->hasOption('user')) {
				$userWhere['user_name'] = $this->getOption('user');
			}

			$result = $this->DB->select(
				['user'],
				['count(*) AS total'],
				$userWhere,
				__METHOD__
			);
			$total = $result->fetchRow();
			$total = intval($total['total']);

			for ($i = 0; $i <= $total; $i = $i + 1000) {
				$result = $this->DB->select(
					['user'],
					['*'],
					$userWhere,
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
					$userKey = $wgMetaNamespace.':achievement:maintenance:user:'.$globalId;
					$this->redis->set($userKey, 1);

					$user = User::newFromRow($user);
					if ($user !== false && $user->getId()) {
						$this->output("\nChecking user ".$user->getName()."\n");
						$totalActions = 0;
						$totalActions += $this->articleCreation($user);
						$totalActions += $this->makeEdits($user);
						$totalActions += $this->uploadingImages($user);
						$totalActions += $this->watchlist($user);
						$this->totalActions($user, $totalActions);
					}
					$this->redis->set($userKey, 0);
					$this->redis->expire($userKey, 3600);
				}
			}
			$this->redis->del($maintenanceKey);
		} catch (RedisException $e) {
			$this->error(__METHOD__.": Caught RedisException - ".$e->getMessage());
			exit;
		}
	}

	/**
	 * Do any awards for Article Creation category.
	 *
	 * @access	private
	 * @param	object	User
	 * @return	integer	Total actions done by user for this category.
	 */
	private function articleCreation($user) {
		$this->output("Article Creation...\n");

		$hashes = [
			'93bf01c35fb2c922d19cb198d78bb671',
			'1ce57554ea488a404cd1771b80d0055e',
			'c914a2f5026130e2609de7fc3879c344',
			'3c946bb7f2e7b301bc72fc5f316f147c',
			'da619dda86314d8269f4656091ea8377',
			'552616d830fc51c685800f0aae93fc4b'
		];

		$where = [
			'rev_user'		=> $user->getId(),
			'rev_parent_id'	=> 0,
			'rev_deleted'	=> 0
		];
		if ($this->start !== null) {
			$where[] = 'rev_timestamp >= '.$this->DB->addQuotes($this->start);
		}
		if ($this->end !== null) {
			$where[] = 'rev_timestamp <= '.$this->DB->addQuotes($this->end);
		}

		$result = $this->DB->select(
			['revision'],
			['count(*) AS total'],
			$where,
			__METHOD__
		);
		$total = $result->fetchRow();
		$total = intval($total['total']);

		$this->output("Found {$total} articles\n");

		if ($total > 0) {
			foreach ($hashes as $hash) {
				$achievement = \Cheevos\Achievement::newFromHash($hash);
				if ($achievement !== false) {
					$achievement->award($user, $total);
				}
			}
		}

		return $total;
	}

	/**
	 * Do any awards for Make Edits category.
	 *
	 * @access	private
	 * @param	object	User
	 * @return	integer	Total actions done by user for this category.
	 */
	private function makeEdits($user) {
		$this->output("Make Edits...\n");

		$hashes = [
			'8b9d08e5e9399473b1227ab6b3b60035',
			'eaa165b7a59694955e8155991c38be9f',
			'fff1256b47b8fa46ac3f9102d5fa8ff3',
			'0c1e03f80ad2aef4e10d9edae3a7e4ea',
			'd691e42b961fdc04da570d8a757ef793',
			'b328b045b482c692d5b448eaa0a5973a',
			'e15ce310a8c13ade76d038c8771cd1a9',
			'22e7c0b0603fe3121a4b142e71047482',
			'e61b70fc244f178cfba85053951b7ea0',
			'4334613d46605675afac07f1f039d4ea',
			'65f0fba4f743b6bc76d024bb155b0633',
			'5266e99f4070ba4b46104c0a52867ebe',
			'9b0c25150271c0a234724b0599ee083d'
		];

		$where = [
			'rev_user'		=> $user->getId(),
			'rev_deleted'	=> 0
		];
		if ($this->start !== null) {
			$where[] = 'rev_timestamp >= '.$this->DB->addQuotes($this->start);
		}
		if ($this->end !== null) {
			$where[] = 'rev_timestamp <= '.$this->DB->addQuotes($this->end);
		}

		$result = $this->DB->select(
			['revision'],
			['count(*) AS total'],
			$where,
			__METHOD__
		);
		$total = $result->fetchRow();
		$total = intval($total['total']);

		$this->output("Found {$total} edits\n");

		if ($total > 0) {
			foreach ($hashes as $hash) {
				$achievement = \Cheevos\Achievement::newFromHash($hash);
				if ($achievement !== false) {
					$achievement->award($user, $total);
				}
			}
		}

		return $total;
	}

	/**
	 * Do any awards for Uploading Images category.
	 *
	 * @access	private
	 * @param	object	User
	 * @return	integer	Total actions done by user for this category.
	 */
	private function uploadingImages($user) {
		$this->output("Uploading Images...\n");

		$hashes = [
			'e4f445f9a33868c8e6eb0d7b5ba2db65',
			'4ef0a771ef2b35427ee6c7b7502b903e',
			'd4358d09b2de299bce66df55fe80ba67',
			'f1d1c4e3820c27185bd9c8893607010a',
			'14bcf3f36a3ba4b1b2b128cf83a324ce',
			'44d0564974f77cc5916c4b0a17fd6293',
			'3460c793c9009cbce681258f9dd5d257',
			'311da8de28cb78fff2572bc5b9d0e9ae',
			'b6de70fa3946d98eba23cef479fb1e9d',
			'04196ca7fb45f1aa508f499d11f84f26',
			'500b9f5535c3143801934d9873ae9725',
			'a21929a7189a96f715371940839320a6',
			'fdc4d83eefbe1eb2faafd0b911fa2864'
		];

		$where = [
			'page.page_namespace'		=> NS_IMAGE,
			'revision.rev_user'			=> $user->getId()
		];
		if ($this->start !== null) {
			$where[] = 'revision.rev_timestamp >= '.$this->DB->addQuotes($this->start);
		}
		if ($this->end !== null) {
			$where[] = 'revision.rev_timestamp <= '.$this->DB->addQuotes($this->end);
		}

		$result = $this->DB->select(
			['page', 'revision'],
			['count(*) AS total'],
			$where,
			__METHOD__,
			[],
			[
				'revision' => [
					'INNER JOIN', 'revision.rev_page = page.page_id'
				]
			]
		);
		$total = $result->fetchRow();
		$total = intval($total['total']);

		$this->output("Found {$total} uploaded images\n");

		if ($total > 0) {
			foreach ($hashes as $hash) {
				$achievement = \Cheevos\Achievement::newFromHash($hash);
				if ($achievement !== false) {
					$achievement->award($user, $total);
				}
			}
		}

		return $total;
	}

	/**
	 * Do any awards for Watchlist achievements.
	 *
	 * @access	private
	 * @param	object	User
	 * @return	integer	Total actions done by user for this category.
	 */
	private function watchlist($user) {
		$this->output("Watchlist...\n");

		$hashes = [
			'be88a5fddc8fa8f4125df60e45d5b574',
			'2de6bb2cd411c3792b0f9b7cd9853ffb'
		];

		$result = $this->DB->select(
			['watchlist'],
			['count(*) AS total'],
			[
				'wl_user'		=> $user->getId(),
			],
			__METHOD__
		);
		$total = $result->fetchRow();
		$total = intval($total['total']);

		$this->output("Found {$total} watched items\n");

		if ($total > 0) {
			foreach ($hashes as $hash) {
				$achievement = \Cheevos\Achievement::newFromHash($hash);
				if ($achievement !== false) {
					$achievement->award($user, $total);
				}
			}
		}

		return $total;
	}

	/**
	 * Do any awards for Total Actions category.
	 *
	 * @access	private
	 * @param	object	User
	 * @param	integer	Total Actions Performed
	 * @return	void
	 */
	private function totalActions($user, $total) {
		$this->output("Updating total actions...\n");

		$hashes = [
			'86f526b5af5f407fbf41b175270dfb3a',
			'34c4718866242f4c123a3d601c98b348',
			'425cc8d7c131dcea5c5856ff438be761',
			'adfb11f4301ee0a15dffa1f21c706faa',
			'cbc4aa86d04f7235f7f9b01b6f876cb5',
			'25a9763af8e2ec732fc1c447fc0dda02',
			'44b13b11cba8b88d6e7873ff2756270a',
			'a7db3e38d502a69a5cea2406a49526e0',
			'73f5b7e1159790febfdb6426a499cef3',
			'274a54224321f20396625893efbcf8fd',
			'4f989a19728dc13199fcf836c9b1815f',
			'f4d4efedcb71112ccfe031c8ad40fd2f',
			'7d62334836eaea6ba7e867c74584093c'
		];

		$this->output("Found {$total} total actions\n");

		if ($total > 0) {
			foreach ($hashes as $hash) {
				$achievement = \Cheevos\Achievement::newFromHash($hash);
				if ($achievement !== false) {
					$achievement->award($user, $total);
				}
			}
		}

		return $total;
	}
}

$maintClass = 'FindExistingAchievements';
require_once(RUN_MAINTENANCE_IF_MAIN);
