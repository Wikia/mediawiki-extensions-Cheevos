<?php

namespace Cheevos;

use MediaWiki\MediaWikiServices;

class CheevosRegistrationCallback {

	/** Setup anything that needs to be configured before anything else runs. */
	public static function onRegistration(): void {
		global $wgDefaultUserOptions, $wgNamespacesForEditPoints, $wgReverbNotifications;

		$wgDefaultUserOptions['cheevos-popup-notification'] = 1;

		// Allowed namespaces.
		if ( empty( $wgNamespacesForEditPoints ) ) {
			$namespaceInfo = MediaWikiServices::getInstance()->getNamespaceInfo();
			$wgNamespacesForEditPoints = $namespaceInfo->getContentNamespaces();
		}

		$reverbNotifications = [
			'user-interest-achievement-earned' => [ 'importance' => 8 ],
		];
		$wgReverbNotifications = array_merge( $wgReverbNotifications, $reverbNotifications );
	}
}
