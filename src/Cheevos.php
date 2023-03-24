<?php
/**
 * Cheevos
 * Cheevos Class
 *
 * @package   Cheevos
 * @author    Cameron Chunn
 * @copyright (c) 2017 Curse Inc.
 * @license   GPL-2.0-or-later
 * @link      https://gitlab.com/hydrawiki/extensions/cheevos
 */

namespace Cheevos;

use MediaWiki\MediaWikiServices;
use MediaWiki\User\UserIdentity;

/**
 * @deprecated
 */
class Cheevos {
	/**
	 * @deprecated
	 * Invalid API Cache
	 */
	public static function invalidateCache(): void {
		MediaWikiServices::getInstance()->getService( AchievementService::class )->invalidateCache();
	}

	/**
	 * @deprecated
	 * Returns all relationships for a user
	 */
	public static function getFriends( UserIdentity $user ): array {
		return MediaWikiServices::getInstance()->getService( FriendService::class )->getFriends( $user );
	}

	/**
	 * @deprecated
	 * Return friendship status
	 */
	public static function getFriendStatus( UserIdentity $from, UserIdentity $to ): array {
		return MediaWikiServices::getInstance()->getService( FriendService::class )->getFriendStatus( $from, $to );
	}

	/**
	 * @deprecated
	 * Create a frienship request
	 */
	public static function createFriendRequest( UserIdentity $from, UserIdentity $to ): array {
		return MediaWikiServices::getInstance()->getService( FriendService::class )->createFriendRequest( $from, $to );
	}

	/**
	 * @deprecated
	 * Accept a friendship request (by creating a request the oposite direction!)
	 */
	public static function acceptFriendRequest( UserIdentity $from, UserIdentity $to ): array {
		return MediaWikiServices::getInstance()->getService( FriendService::class )->acceptFriendRequest( $from, $to );
	}

	/**
	 * @deprecated
	 * Remove a friendship association between 2 users.
	 */
	public static function removeFriend( UserIdentity $from, UserIdentity $to ): array {
		return MediaWikiServices::getInstance()->getService( FriendService::class )->removeFriend( $from, $to );
	}

	/**
	 * @deprecated
	 * Cancel friend request by removing assosiation.
	 */
	public static function cancelFriendRequest( UserIdentity $from, UserIdentity $to ): array {
		return MediaWikiServices::getInstance()->getService( FriendService::class )->cancelFriendRequest( $from, $to );
	}

	/**
	 * @deprecated
	 * @return CheevosAchievement[]
	 */
	public static function getAchievements( $siteKey = null ): array {
		return MediaWikiServices::getInstance()->getService( AchievementService::class )->getAchievements( $siteKey );
	}

	/**
	 * @deprecated
	 * Get achievement by database ID with caching.
	 */
	public static function getAchievement( int $id ): ?CheevosAchievement {
		return MediaWikiServices::getInstance()->getService( AchievementService::class )->getAchievement( $id );
	}

	/**
	 * @deprecated
	 * Soft delete an achievement from the service.
	 */
	public static function deleteAchievement( int $id, int $authorId ): array {
		return MediaWikiServices::getInstance()->getService( AchievementService::class )
			->deleteAchievement( $id, $authorId );
	}

	/**
	 * @deprecated
	 * Update an existing achievement on the service.
	 */
	public static function updateAchievement( int $id, array $body ): void {
		MediaWikiServices::getInstance()->getService( AchievementService::class )
			->updateAchievement( $id, $body );
	}

	/**
	 * @deprecated
	 * Create Achievement
	 */
	public static function createAchievement( array $body ): void {
		MediaWikiServices::getInstance()->getService( AchievementService::class )
			->createAchievement( $body );
	}

	/**
	 * @deprecated
	 * Get all categories.
	 *
	 * @param bool $skipCache [Optional] Skip pulling data from the local cache. Will still update the local cache.
	 *
	 * @return CheevosAchievementCategory[]
	 */
	public static function getCategories( bool $skipCache = false ): array {
		return MediaWikiServices::getInstance()->getService( AchievementService::class )
			->getCategories( $skipCache );
	}

	/**
	 * @deprecated
	 * Get Category by ID
	 */
	public static function getCategory( int $id ): ?CheevosAchievementCategory {
		return MediaWikiServices::getInstance()->getService( AchievementService::class )
			->getCategory( $id );
	}

	/**
	 * @deprecated
	 * Delete Category by ID (with optional user_id for user that deleted the category)
	 */
	public static function deleteCategory( int $id, int $userId ): void {
		MediaWikiServices::getInstance()->getService( AchievementService::class )
			->deleteCategory( $id, $userId );
	}

	/**
	 * @deprecated
	 * Update Category by ID
	 */
	public static function updateCategory( int $id, array $body ): array {
		return MediaWikiServices::getInstance()->getService( AchievementService::class )
			->updateCategory( $id, $body );
	}

	/**
	 * @deprecated
	 * Create Category
	 */
	public static function createCategory( array $body ): array {
		return MediaWikiServices::getInstance()->getService( AchievementService::class )
			->createCategory( $body );
	}

	/**
	 * @deprecated
	 * Call the increment end point on the API.
	 */
	public static function increment( array $body ): array {
		return MediaWikiServices::getInstance()->getService( AchievementService::class )
			->increment( $body );
	}

	/**
	 * @deprecated
	 * Call increment to check for any unnotified achievement rewards.
	 */
	public static function checkUnnotified( int $globalId, string $siteKey, bool $forceRecalculate = false ): array {
		return MediaWikiServices::getInstance()->getService( AchievementService::class )
			->checkUnnotified( $globalId, $siteKey, $forceRecalculate );
	}

	/**
	 * @deprecated
	 * Return StatProgress for selected filters.
	 *
	 * @param array $filters Limit Filters - All filters are optional and can be omitted from the array.
	 *                        This is an array since the amount of filter parameters is expected to be reasonably
	 * 						  volatile over the life span of the product.
	 *                        This function does minimum validation of the filters.
	 * 						  For example, sending a numeric string when the service is expecting an integer will
	 * 						  result in an exception being thrown.
	 *                        - $filters = [
	 *                        -     'user_id' => 0, //Limit by global user ID.
	 *                        -     'site_key' => 'example', //Limit by site key.
	 *                        -     'global' => false, //Set to true to aggregate stats from all sites.
	 * 													(Also causes site_key to be ignored.)
	 *                        -     'stat' => 'example', //Filter by a specific stat name.
	 *                        -     'sort_direction' => 'asc' or 'desc', //If supplied, the result will be sorted
	 * 																	on the stats' count field.
	 *                        -     'start_time' => 'example', //If supplied, only stat deltas after this
	 * 															unix timestamp are considered.
	 *                        -     'end_time' => 'example', //If supplied, only stat deltas before this unix
	 * 															timestamp are considered.
	 *                        -     'limit' => 200, //Maximum number of results.  Defaults to 200.
	 *                        -     'offset' => 0, //Offset to start from the beginning of the result set.
	 *                        - ];
	 * @return CheevosStatProgress[]
	 */
	public static function getStatProgress( array $filters = [], ?UserIdentity $userIdentity = null ): array {
		return MediaWikiServices::getInstance()->getService( AchievementService::class )
			->getStatProgress( $filters, $userIdentity );
	}

	/**
	 * @deprecated
	 * Return WikiPointLog for selected filters.
	 *
	 * @param array $filters Limit Filters - All filters are optional and can omitted from the array.
	 *                        This is an array since the amount of filter parameters is expected to be reasonably
	 * 						  volatile over the life span of the product.
	 *                        This function does minimum validation of the filters.
	 * 						  For example, sending a numeric string when the service is expecting an integer will
	 * 						  result in an exception being thrown.
	 *                        - $filters = [
	 *                        -     'user_id' => 0, //Limit by global user ID.
	 *                        -     'site_key' => 'example', //Limit by site key.
	 *                        -     'limit' => 200, //Maximum number of results.  Defaults to 200.
	 *                        -     'offset' => 0, //Offset to start from the beginning of the result set.
	 *                        - ];
	 * @return CheevosStatProgress[]
	 */
	public static function getWikiPointLog( array $filters = [], ?UserIdentity $userIdentity = null ): array {
		return MediaWikiServices::getInstance()->getService( AchievementService::class )
			->getStatProgress( $filters, $userIdentity );
	}

	/**
	 * @deprecated
	 * Return stats/user_site_count for selected filters.
	 *
	 * @return mixed
	 */
	public static function getUserPointRank( UserIdentity $user, ?string $siteKey = null ): mixed {
		return MediaWikiServices::getInstance()->getService( AchievementService::class )
			->getUserPointRank( $user, $siteKey );
	}

	/**
	 * @deprecated
	 * Return StatMonthlyCount for selected filters.
	 *
	 * @param array $filters Limit Filters - All filters are optional and can omitted from the array.
	 *                          This is an array since the amount of filter parameters is expected to be
	 *                          reasonably volatile over the life span of the product. This function
	 *                          does minimum validation of the filters.  For example, sending a numeric
	 *                          string when the service is expecting an integer will result in an
	 *                          exception being thrown.
	 * 		                    - $filters = [
	 *                          -     'user_id' => 1, //Limit by service user ID.
	 *                          -     'site_key' => 'example', //Limit by site key.
	 *                          -     'stat' => 'example', //Filter by a specific stat name.
	 *                          -     'global' => true, //Overrides site_key to aggregate across all sites.
	 *                          -     'month' => 1601510400, //Limit to one month (starting timestamp).
	 *                          -     'limit' => 200, //Maximum number of results.  Defaults to 200.
	 *                          -     'offset' => 0, //Offset to start from the beginning of the result set.
	 * 		                    - ];
	 * @return CheevosStatMonthlyCount[]
	 */
	public static function getStatMonthlyCount( array $filters = [], ?UserIdentity $user = null ): array {
		return MediaWikiServices::getInstance()->getService( AchievementService::class )
			->getStatMonthlyCount( $filters, $user );
	}

	/**
	 * @deprecated
	 * Return stats/user_site_count for selected filters.
	 */
	public static function getUserSitesCountByStat( UserIdentity $user, string $stat ): mixed {
		return MediaWikiServices::getInstance()->getService( AchievementService::class )
			->getUserSitesCountByStat( $user, $stat );
	}

	/**
	 * @deprecated
	 * Get achievement status for an user.
	 *
	 * @return CheevosAchievementStatus[]
	 */
	public static function getAchievementStatus( int $userId, string $siteKey ): array {
		return MediaWikiServices::getInstance()->getService( AchievementService::class )
			->getAchievementStatus( $userId, $siteKey );
	}

	/**
	 * @deprecated
	 * Return AchievementProgress for selected filters.
	 *
	 * @param array $filters Limit Filters - All filters are optional and can be omitted from the array.
	 *                           - $filters = [
	 *                           -     'site_key' => 'example', //Limit by site key.
	 *                           -     'achievement_id' => 0, //Limit by achievement ID.
	 *                           -     'user_id' => 0, //Limit by global user ID.
	 *                           -     'category_id' => 0, //Limit by category ID.
	 *                           -     'earned' => false, //Only get progress for earned achievements.
	 *                           -     'limit' => 100, //Maximum number of results.
	 *                           -     'offset' => 0, //Offset to start from the beginning of the result set.
	 *                           - ];
	 * @param UserIdentity|null $user Filter by user.  Overwrites 'user_id' in $filters if provided.
	 *
	 * @return mixed
	 */
	public static function getAchievementProgress( array $filters = [], ?UserIdentity $user = null ): array {
		return MediaWikiServices::getInstance()->getService( AchievementService::class )
			->getAchievementProgress( $filters, $user );
	}

	/**
	 * @deprecated
	 * Get process for achievement
	 *
	 * @param int $id
	 */
	public static function getProgress( $id ): ?CheevosAchievementProgress {
		return MediaWikiServices::getInstance()->getService( AchievementService::class )->getProgress( $id );
	}

	/**
	 * @deprecated
	 * Delete progress towards an achievement.
	 */
	public static function deleteProgress( int $id ): array {
		return MediaWikiServices::getInstance()->getService( AchievementService::class )->deleteProgress( $id );
	}

	/**
	 * @deprecated
	 * Put process for achievement. Either create or updates.
	 */
	public static function putProgress( $body ): array {
		return MediaWikiServices::getInstance()->getService( AchievementService::class )->putProgress( $body );
	}

	/**
	 * @deprecated
	 * Revokes edit points for the provided revision IDs related to the page ID.
	 */
	public static function revokeEditPoints( int $pageId, array $revisionIds, string $siteKey ): array {
		return MediaWikiServices::getInstance()->getService( AchievementService::class )
			->revokeEditPoints( $pageId, $revisionIds, $siteKey );
	}
}
