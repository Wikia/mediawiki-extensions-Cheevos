<?php

declare( strict_types=1 );

use Cheevos\AchievementService;
use Cheevos\CheevosClient;
use Cheevos\CheevosHelper;
use Cheevos\FriendService;
use Fandom\Includes\Article\GlobalTitleLookup;
use GuzzleHttp\Client;
use MediaWiki\MediaWikiServices;
use Reverb\Notification\NotificationBroadcastFactory;
use WikiDomain\WikiConfigDataService;

return [
	CheevosClient::class => static function ( MediaWikiServices $services ): CheevosClient {
		$config = $services->getMainConfig();

		// Use the shared HTTP client instance in the Fandom setup if available,
		// but don't fail if it is absent (e.g. in tests).
		$httpClient = defined( 'SERVICE_HTTP_CLIENT' ) ?
			$services->getService( SERVICE_HTTP_CLIENT ) : new Client();
		return new CheevosClient(
			$httpClient,
			$config->get( 'CheevosHost' ),
			[
				'Accept' => 'application/json',
				'Content-Type' => 'application/json',
				'Client-ID' => $config->get( 'CheevosClientId' ),
			]
		);
	},

	FriendService::class => static function ( MediaWikiServices $services ): FriendService {
		return new FriendService(
			$services->getService( CheevosClient::class ),
			$services->getUserIdentityLookup(),
			$services->getUserFactory()
		);
	},

	AchievementService::class => static function ( MediaWikiServices $services ): AchievementService {
		// Make Reverb notifications an optional dependency to facilitate testing.
		$notificationBroadcastFactory = $services->has( NotificationBroadcastFactory::class ) ?
			$services->getService( NotificationBroadcastFactory::class ) : null;
		return new AchievementService(
			$services->getService( CheevosClient::class ),
			$services->getMainWANObjectCache(),
			$services->getMainConfig(),
			$notificationBroadcastFactory,
			$services->getUserFactory(),
			$services->getUserIdentityLookup()
		);
	},

	CheevosHelper::class => static function ( MediaWikiServices $services ): CheevosHelper {
		return new CheevosHelper(
			$services->getService( AchievementService::class ),
			$services->getMainConfig(),
			$services->getService( GlobalTitleLookup::class ),
			$services->getService( WikiConfigDataService::class )
		);
	},
];
