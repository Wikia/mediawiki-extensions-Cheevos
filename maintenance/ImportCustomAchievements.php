<?php
/**
 * Curse Inc.
 * Cheevos
 * Imports customized achievements.
 *
 * @author		Alexia E. Smith
 * @copyright	(c) 2017 Curse Inc.
 * @license		All Rights Reserved
 * @package		Cheevos
 * @link		https://www.gamepedia.com/
 *
**/
require_once(__DIR__.'/../../../maintenance/Maintenance.php');

class ImportCustomAchievements extends Maintenance {
	/**
	 * Constructor
	 *
	 * @access	public
	 * @return	void
	 */
	public function __construct() {
		parent::__construct();
		$this->addDescription('Imports customized achievements.  Should only ever be run once.');
		$this->addOption('restart', 'Run again from the beginning even if run before.');
	}

	/**
	 * Main execution of the maintenance script
	 *
	 * @access	public
	 * @return	void
	 */
	public function execute() {
		$cache = wfGetCache(CACHE_MEMCACHED);
		if (MASTER_WIKI !== true || (boolval($cache->get('ImportCustomAchievements')) && !$this->getOption('restart'))) {
			throw new MWException('This script is intended to be ran from the master wiki and only once.');
		} elseif ($this->getOption('restart')) {
			$cache->set('ImportCustomAchievements', 0);
		}

		$db = wfGetDB(DB_MASTER);

		$where = [
			"edited > 0 OR unique_hash NOT IN('".implode("', '", $this->uniqueHashes)."')"
		];

		$this->output("Importing custom achievements...\n");

		$result = $db->select(
			['achievement'],
			['*'],
			$where,
			__METHOD__,
			[
				'ORDER BY'	=> 'aid ASC'
			]
		);

		while ($row = $result->fetchRow()) {
			var_dump($row);
		}
		$cache->set('ImportCustomAchievements', 1);
	}

	private $uniqueHashes = [
		'026f8f84a0290e33a4e81d2cb54db9ad',
		'04196ca7fb45f1aa508f499d11f84f26',
		'048991dddd8826edf4cb94d2ea5c9817',
		'065d1866bf2aed806b1d889e1c50f0d8',
		'07ea966fe4dc1a8d62616e2f67221440',
		'0a482a5627931f2c625c4143b500cd8f',
		'0c1e03f80ad2aef4e10d9edae3a7e4ea',
		'0d19dd8f209f8eea383b6bb47cf73320',
		'0e9b8997ea70842c765e924e3eaccfd2',
		'100680c95b90763f18a23cc99bb58adf',
		'101adee83112cc91625083d5299c9ae7',
		'11f442cbb18f1bd215381a88041be237',
		'139e8acf121e41c5f25eb0b97b984a51',
		'14bcf3f36a3ba4b1b2b128cf83a324ce',
		'165bf2971205636162ffbbffc8da4e8a',
		'1ce57554ea488a404cd1771b80d0055e',
		'1f42c336e8b47a7545445a622ea79919',
		'22e7c0b0603fe3121a4b142e71047482',
		'2491316950f4577b384c3c675278e6d0',
		'25a9763af8e2ec732fc1c447fc0dda02',
		'274a54224321f20396625893efbcf8fd',
		'2de6bb2cd411c3792b0f9b7cd9853ffb',
		'311da8de28cb78fff2572bc5b9d0e9ae',
		'3212d56187dc06abcbb67bcf16574511',
		'3460c793c9009cbce681258f9dd5d257',
		'34c4718866242f4c123a3d601c98b348',
		'35064fa6e5ef147e1987b1776bd8ce5d',
		'39660a1bf953eb03d4af7ec911ca2bdd',
		'3a2447e5e1dffdbec83f8ec2444cff68',
		'3c946bb7f2e7b301bc72fc5f316f147c',
		'40907164a875b027347c9af1d4717daa',
		'40f25f9a784e7afc37d65acf5123fc59',
		'425cc8d7c131dcea5c5856ff438be761',
		'428fe8ad48d773862c85fd3e81b37992',
		'4334613d46605675afac07f1f039d4ea',
		'44b13b11cba8b88d6e7873ff2756270a',
		'44d0564974f77cc5916c4b0a17fd6293',
		'45c670f9d925434a0106c9359cc4d01f',
		'4ef0a771ef2b35427ee6c7b7502b903e',
		'4f989a19728dc13199fcf836c9b1815f',
		'500b9f5535c3143801934d9873ae9725',
		'5266e99f4070ba4b46104c0a52867ebe',
		'552616d830fc51c685800f0aae93fc4b',
		'5ced0fb05f32c0c4a8b62ca28e7b55c5',
		'65f0fba4f743b6bc76d024bb155b0633',
		'68dec7fafbadf63068d480a08e96b754',
		'6a571a009f60681a76d11f2c14dac165',
		'73583f8ccf81a83bc3c90ad9ac74bf4e',
		'73f5b7e1159790febfdb6426a499cef3',
		'748d1ad899042ce702e076a642a28517',
		'760fda84c4bf32ddc64be9ebfb617cdb',
		'762e88a5edc5d603d8462bbe074b5a6a',
		'7d62334836eaea6ba7e867c74584093c',
		'816490d025abf7e0ed45a5dbb5b2985d',
		'83225d693faa26f0260371e0bcaa7b37',
		'86f526b5af5f407fbf41b175270dfb3a',
		'88344052603c964342aed19c32c8d082',
		'8b9d08e5e9399473b1227ab6b3b60035',
		'93bf01c35fb2c922d19cb198d78bb671',
		'9b0c25150271c0a234724b0599ee083d',
		'9ce74d8eb6b77a6bd04502a05f90af9e',
		'a0f2fc0c8b793567690cd9e3347a336b',
		'a21929a7189a96f715371940839320a6',
		'a77bc45ca06e3ecf72c87c3e7ab185ef',
		'a7db3e38d502a69a5cea2406a49526e0',
		'aa686a359703713c5a5456395b7fff47',
		'adfb11f4301ee0a15dffa1f21c706faa',
		'b328b045b482c692d5b448eaa0a5973a',
		'b6de70fa3946d98eba23cef479fb1e9d',
		'b92b137928a4aca68c2409e40aa3c136',
		'b94d2d47a07e3f0c5ad3b0c4c3a9f5cc',
		'bb123fd82de4704dca67a68df8899044',
		'be563c063b8515e2566f086f3e9730a5',
		'be88a5fddc8fa8f4125df60e45d5b574',
		'c722bcde56a731bf5a428cd002017944',
		'c914a2f5026130e2609de7fc3879c344',
		'cbc4aa86d04f7235f7f9b01b6f876cb5',
		'cc922accdd95403dbf707e63b7a76b92',
		'd4358d09b2de299bce66df55fe80ba67',
		'd686809aa3f27c8ae5036d2c41496615',
		'd691e42b961fdc04da570d8a757ef793',
		'da619dda86314d8269f4656091ea8377',
		'dc3004e60061fc7787ff3522adcc9023',
		'dd3f4b1f3f896eeb557f808ec4d33db1',
		'df3fc983590d6c77f6aaacad10fe1c4f',
		'e15ce310a8c13ade76d038c8771cd1a9',
		'e4f445f9a33868c8e6eb0d7b5ba2db65',
		'e61b70fc244f178cfba85053951b7ea0',
		'eaa165b7a59694955e8155991c38be9f',
		'f1d1c4e3820c27185bd9c8893607010a',
		'f4d4efedcb71112ccfe031c8ad40fd2f',
		'f8145a07b82eeef80f37bb76060a3479',
		'fdc4d83eefbe1eb2faafd0b911fa2864',
		'ff6e2c2a5bfc6adb9b19834078b60bcc',
		'fff1256b47b8fa46ac3f9102d5fa8ff3'
	];
}

$maintClass = 'ImportCustomAchievements';
if (defined('RUN_MAINTENANCE_IF_MAIN')) {
	require_once( RUN_MAINTENANCE_IF_MAIN );
} else {
	require_once( DO_MAINTENANCE );
}
