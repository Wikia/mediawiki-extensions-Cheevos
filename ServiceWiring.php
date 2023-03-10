<?php

declare( strict_types=1 );

use Cheevos\AchievementService;
use Cheevos\CheevosClient;
use Cheevos\CheevosHelper;
use Cheevos\FriendService;
use MediaWiki\MediaWikiServices;
use Reverb\Notification\NotificationBroadcastFactory;

return [
	CheevosClient::class => static function ( MediaWikiServices $services ): CheevosClient {
		$config = $services->getMainConfig();
		return new CheevosClient(
			$services->getService( SERVICE_HTTP_CLIENT ),
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
		return new AchievementService(
			$services->getService( CheevosClient::class ),
			$services->getService( RedisCache::class ),
			$services->getMainConfig(),
			$services->getService( NotificationBroadcastFactory::class ),
			$services->getUserFactory(),
			$services->getUserIdentityLookup()
		);
	},

	CheevosHelper::class => static function ( MediaWikiServices $services ): CheevosHelper {
		return new CheevosHelper(
			$services->getService( AchievementService::class ),
			$services->getMainConfig()
		);
	},
];
