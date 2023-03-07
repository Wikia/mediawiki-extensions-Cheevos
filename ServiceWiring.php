<?php

declare( strict_types=1 );

use Cheevos\CheevosClient;
use MediaWiki\MediaWikiServices;

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
];
