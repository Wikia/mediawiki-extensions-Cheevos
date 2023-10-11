<?php
declare( strict_types=1 );
namespace Cheevos;

use Config;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use HashBagOStuff;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentityLookup;
use MediaWikiIntegrationTestCase;
use WANObjectCache;

/**
 * @covers \Cheevos\AchievementService
 */
class AchievementServiceTest extends MediaWikiIntegrationTestCase {

	private MockHandler $httpHandler;
	private AchievementService $achievementService;

	protected function setUp(): void {
		parent::setUp();

		$this->httpHandler = new MockHandler();

		$httpClient = new Client( [ 'handler' => $this->httpHandler ] );
		$client = new CheevosClient( $httpClient, 'http://example', [] );
		$cache = new WANObjectCache( [ 'cache' => new HashBagOStuff() ] );

		$this->achievementService = new AchievementService(
			$client,
			$cache,
			$this->createMock( Config::class ),
			null,
			$this->createMock( UserFactory::class ),
			$this->createMock( UserIdentityLookup::class )
		);

		// CheevosAchievement and co. attempt to access AchievementService from the container
		$this->setService( AchievementService::class, fn () => $this->achievementService );
	}

	public function testShouldCacheAchievementsBySite(): void {
		$this->httpHandler->append(
			new Response( 200, [], file_get_contents( __DIR__ . '/fixtures/all-achievements-response.json' ) ),
			new Response( 200, [], file_get_contents( __DIR__ . '/fixtures/all-achievements-other-response.json' ) )
		);

		$achievements = $this->achievementService->getAchievements( 'test' );
		$other = $this->achievementService->getAchievements( 'other' );

		$this->assertSame( $achievements, $this->achievementService->getAchievements( 'test' ) );
		$this->assertSame( $other, $this->achievementService->getAchievements( 'other' ) );
		$this->assertCount( 2, $achievements );
		$this->assertCount( 1, $other );
	}

	public function testShouldCacheSingleAchievementById(): void {
		$this->httpHandler->append(
			new Response( 200, [], file_get_contents( __DIR__ . '/fixtures/single-achievement-response.json' ) ),
			new Response( 200, [], file_get_contents( __DIR__ . '/fixtures/single-achievement-other-response.json' ) )
		);

		$first = $this->achievementService->getAchievement( 5718 );
		$other = $this->achievementService->getAchievement( 5720 );

		$this->assertSame( $first, $this->achievementService->getAchievement( 5718 ) );
		$this->assertSame( $other, $this->achievementService->getAchievement( 5720 ) );
		$this->assertInstanceOf( CheevosAchievement::class, $first );
		$this->assertInstanceOf( CheevosAchievement::class, $other );

		$this->assertSame( 5718, $first->getId() );
		$this->assertSame( 5720, $other->getId() );
	}
}
