<?php

namespace Cheevos;

use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityLookup;

class FriendService {
	public function __construct(
		private CheevosClient $cheevosClient,
		private UserIdentityLookup $userIdentityLookup,
		private UserFactory $userFactory
	) {
	}

	/** Returns all relationships for a user */
	public function getFriends( UserIdentity $userIdentity ): array {
		$friendTypes = $this->cheevosClient->get( "friends/{$userIdentity->getId()}" );

		foreach ( $friendTypes as $category => $userIds ) {
			if ( !is_array( $userIds ) ) {
				continue;
			}
			foreach ( $userIds as $key => $userId ) {
				$userIdentity = $this->userIdentityLookup->getUserIdentityByUserId( $userId );
				if ( !$userIdentity || !$userIdentity->isRegistered() ) {
					unset( $friendTypes[$category][$key] );
				} else {
					$friendTypes[$category][$key] = $this->userFactory->newFromUserIdentity( $userIdentity );
				}
			}
		}

		return $friendTypes;
	}

	/** Return friendship status */
	public function getFriendStatus( UserIdentity $from, UserIdentity $to ): array {
		return $this->cheevosClient->get( "friends/{$from->getId()}/{$to->getId()}" );
	}

	/** Create a frienship request */
	public function createFriendRequest( UserIdentity $from, UserIdentity $to ): array {
		return $this->cheevosClient->put( "friends/{$from->getId()}/{$to->getId()}" );
	}

	/** Accept a friendship request (by creating a request the oposite direction!) */
	public function acceptFriendRequest( UserIdentity $from, UserIdentity $to ): array {
		return $this->createFriendRequest( $from, $to );
	}

	/** Remove a friendship association between 2 users. */
	public function removeFriend( UserIdentity $from, UserIdentity $to ): array {
		return $this->cheevosClient->delete( "friends/{$from->getId()}/{$to->getId()}" );
	}

	/** Cancel friend request by removing assosiation. */
	public function cancelFriendRequest( UserIdentity $from, UserIdentity $to ): array {
		return $this->removeFriend( $from, $to );
	}
}
