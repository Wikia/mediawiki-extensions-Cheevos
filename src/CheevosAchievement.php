<?php
/**
 * Cheevos
 * Cheevos Achievement Model
 *
 * @package   Cheevos
 * @author    Cameron Chunn
 * @copyright (c) 2017 Curse Inc.
 * @license   GPL-2.0-or-later
 * @link      https://gitlab.com/hydrawiki/extensions/cheevos
 */

namespace Cheevos;

use MediaWiki\MediaWikiServices;
use Title;

class CheevosAchievement extends CheevosModel {

	private const FIELDS = [
		'name',
		'description',
		'image',
		'category',
		'points',
		'global',
		'protected',
		'secret',
		'special',
		'show_on_all_sites',
		'deleted_at',
		'deleted_by',
		'criteria'
	];

	/**
	 * What achievements this achievement is required by.
	 *
	 * @var array|null
	 */
	private ?array $requiredBy = null;

	private AchievementService $achievementService;

	public function __construct( array $data = null ) {
		$this->achievementService = MediaWikiServices::getInstance()->getService( AchievementService::class );
		$this->container['id'] = isset( $data['id'] ) && is_int( $data['id'] ) ? $data['id'] : 0;
		$this->container['parent_id'] = isset( $data['parent_id'] ) &&
										is_int( $data['parent_id'] ) ? $data['parent_id'] : 0;
		$this->container['site_id'] = isset( $data['site_id'] ) && is_int( $data['site_id'] ) ? $data['site_id'] : 0;
		$this->container['site_key'] = isset( $data['site_key'] ) &&
									   is_string( $data['site_key'] ) ? $data['site_key'] : "";
		$this->container['name'] = isset( $data['name'] ) && is_array( $data['name'] ) ? $data['name'] : [];
		$this->container['description'] = isset( $data['description'] ) &&
										  is_array( $data['description'] ) ? $data['description'] : [];
		$this->container['image'] = isset( $data['image'] ) && is_string( $data['image'] ) ? $data['image'] : '';

		if ( !isset( $data['category'] ) ) {
			$this->container['category'] = new CheevosAchievementCategory();
		} elseif ( $data['category'] instanceof CheevosAchievementCategory ) {
			$this->container['category'] = $data['category'];
		} elseif ( is_array( $data['category'] ) ) {
			$this->container['category'] = new CheevosAchievementCategory( $data['category'] );
		} else {
			$this->container['category'] = new CheevosAchievementCategory();
		}

		$this->container['points'] = isset( $data['points'] ) && is_int( $data['points'] ) ? $data['points'] : 0;
		$this->container['global'] = isset( $data['global'] ) && is_bool( $data['global'] ) && $data['global'];
		$this->container['protected'] = isset( $data['protected'] ) &&
										is_bool( $data['protected'] ) && $data['protected'];
		$this->container['secret'] = isset( $data['secret'] ) && is_bool( $data['secret'] ) && $data['secret'];
		$this->container['special'] = isset( $data['special'] ) && is_bool( $data['special'] ) && $data['special'];
		$this->container['show_on_all_sites'] =
			isset( $data['show_on_all_sites'] ) && is_bool( $data['show_on_all_sites'] ) && $data['show_on_all_sites'];
		$this->container['created_at'] = isset( $data['created_at'] ) &&
										 is_int( $data['created_at'] ) ? $data['created_at'] : 0;
		$this->container['updated_at'] = isset( $data['updated_at'] ) &&
										 is_int( $data['updated_at'] ) ? $data['updated_at'] : 0;
		$this->container['deleted_at'] = isset( $data['deleted_at'] ) &&
										 is_int( $data['deleted_at'] ) ? $data['deleted_at'] : 0;
		$this->container['created_by'] = isset( $data['created_by'] ) &&
										 is_int( $data['created_by'] ) ? $data['created_by'] : 0;
		$this->container['updated_by'] = isset( $data['updated_by'] ) &&
										 is_int( $data['updated_by'] ) ? $data['updated_by'] : 0;
		$this->container['deleted_by'] = isset( $data['deleted_by'] ) &&
										 is_int( $data['deleted_by'] ) ? $data['deleted_by'] : 0;

		if ( !isset( $data['criteria'] ) ) {
			$this->container['criteria'] = new CheevosAchievementCriteria();
		} elseif ( $data['criteria'] instanceof CheevosAchievementCriteria ) {
			$this->container['criteria'] = $data['criteria'];
		} elseif ( is_array( $data['criteria'] ) ) {
			$this->container['criteria'] = new CheevosAchievementCriteria( $data['criteria'] );
		} else {
			$this->container['criteria'] = new CheevosAchievementCriteria();
		}
	}

	/**
	 * Save achievement up to the service.
	 *
	 * @param bool $forceCreate Force create instead of save.
	 * Typically used when copying from a global parent to a child.
	 */
	public function save( bool $forceCreate = false ): void {
		if ( $this->readOnly ) {
			throw new CheevosException( "This object is read only and can not be saved." );
		}

		if ( $this->getId() !== null && !$forceCreate ) {
			$this->achievementService->updateAchievement( $this->getId(), $this->toArray() );
		} else {
			$this->achievementService->createAchievement( $this->toArray() );
		}
	}

	public function exists(): bool {
		if ( $this->getId() <= 0 ) {
			return false;
		}

		try {
			// Throws an error if it doesn't exist.
			$this->achievementService->getAchievement( $this->getId() );
			return true;
		} catch ( CheevosException $e ) {
			return false;
		}
	}

	public function isManuallyAwarded(): bool {
		$crit = $this->getCriteria();
		return !isset( $crit[ 'category_id' ] ) && !$crit[ 'stats' ] && !$crit[ 'achievement_ids' ];
	}

	public function isMega(): bool {
		return false; // No no no... you buy.
	}

	/**
	 * Get the achievement name for display.
	 *
	 * @param string|null $siteKey Site Key - Pass in a different site key to substitute different|null
	 * $wgSitenames in cases of an earned achievement being displayed on a different wiki.
	 *
	 * @return string Achievement Name
	 */
	public function getName( string $siteKey = null ): string {
		if ( $this->container['name'] == null || !count( $this->container['name'] ) ) {
			return "";
		}
		$cheevosHelper = MediaWikiServices::getInstance()->getService( CheevosHelper::class );
		$code = $cheevosHelper->getUserLanguage();
		if ( array_key_exists( $code, $this->container['name'] ) && isset( $this->container['name'][$code] ) ) {
			$name = $this->container['name'][$code];
		} else {
			$name = reset( $this->container['name'] );
		}

		if ( $siteKey === null ) {
			$siteKey = $this->container['site_key'];
		}

		$sitename = $cheevosHelper->getSiteName( $siteKey );

		return str_replace( "{{SITENAME}}", $sitename, $name );
	}

	/**
	 * Set the name for this achievement with automatic language code selection.
	 *
	 * @param string $name Name
	 *
	 * @return void
	 */
	public function setName( string $name ): void {
		$code = CheevosHelper::getUserLanguage();
		if ( !is_array( $this->container['name'] ) ) {
			$this->container['name'] = [ $code => $name ];
		} else {
			$this->container['name'][$code] = $name;
		}
	}

	public function getCategoryId() {
		return $this->container['category']['id'];
	}

	public function getCategory(): CheevosAchievementCategory {
		if ( $this->container['category'] instanceof CheevosAchievementCategory ) {
			return $this->container['category'];
		}
		return new CheevosAchievementCategory( $this->container['category'] );
	}

	public function getDescription() {
		if ( $this->container['description'] == null || !count( $this->container['description'] ) ) {
			return "";
		}
		$code = CheevosHelper::getUserLanguage();
		if (
			array_key_exists( $code, $this->container['description'] ) &&
			 isset( $this->container['description'][$code] )
		) {
			return $this->container['description'][$code];
		}

		return reset( $this->container['description'] );
	}

	/**
	 * Set the description for this achievement with automatic language code selection.
	 *
	 * @param string $desc Description
	 *
	 * @return void
	 */
	public function setDescription( string $desc ): void {
		$code = CheevosHelper::getUserLanguage();
		if ( !is_array( $this->container['description'] ) ) {
			$this->container['description'] = [ $code => $desc ];
		} else {
			$this->container['description'][$code] = $desc;
		}
	}

	/**
	 * Returns the image article name.
	 * "File:ExampleAchievement.png"
	 *
	 * @return string Image Article Name - If available
	 */
	public function getImage() {
		$image = $this->container['image'];
		if ( empty( $image ) ) {
			return null;
		}
		return $image;
	}

	/**
	 * Returns the image HTTP(S) URL.
	 *
	 * @return false|string Image URL; false if unable to locate the file.
	 */
	public function getImageUrl(): bool|string {
		global $wgExtensionAssetsPath;

		$title = Title::newFromText( $this->getImage() );
		$file = MediaWikiServices::getInstance()->getRepoGroup()->findFile( $title );
		if ( $file ) {
			$url = $file->getCanonicalUrl();
			return $url;
		}

		$config = MediaWikiServices::getInstance()->getMainConfig();
		$url = $wgExtensionAssetsPath . $config->get( 'AchImageFallback' );
		if ( !empty( $url ) ) {
			return $url;
		}

		return false;
	}

	/**
	 * Sets this achievement as global.
	 *
	 * @param bool $global Set to global.
	 *
	 * @return void
	 */
	public function setGlobal( bool $global = true ): void {
		$this->container['global'] = $global;
		if ( $this->container['global'] ) {
			$this->container['site_id'] = 0;
			$this->container['site_key'] = '';
		}
	}

	/**
	 * Is this achievement deleted?
	 *
	 * @return bool Is Deleted
	 */
	public function isDeleted(): bool {
		return (bool)$this->container[ 'deleted_at' ];
	}

	/**
	 * Is this achievement child of a parent achievement?
	 *
	 * @return bool Is Child
	 */
	public function isChild(): bool {
		return (bool)$this->container[ 'parent_id' ];
	}

	/**
	 * Does this achievement roughly equal another achievement?
	 * Such as criteria, points to be earned, etc. Ignore fields such as created and updated timestamps.
	 *
	 * @return bool
	 */
	public function sameAs( CheevosModel $model ): bool {
		foreach ( self::FIELDS as $field ) {
			if ( $this->container[$field] instanceof CheevosModel ) {
				if ( !$this->container[$field]->sameAs( $model->container[$field] ) ) {
					return false;
				}
				continue;
			}
			if ( $this->container[$field] !== $model[$field] ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Removes achievements that should not be used or shown in the context they are called from.
	 *
	 * @param array $toPrune Two key array of:
	 *		[
	 *			[CheevosAchievement objects]
	 *			[CheevosAchievementStatus OR CheevosAchievementProgress objects]
	 *		]
	 *		Note: Just pruning statuses still requires a blank array to be passed as the first array index:
	 *  		[[], $statuses]
	 *		Also note: Just pruning statuses is useless as it needs achievement information to successfully prune them.
	 *		Pruning statuses is not required.
	 * @param bool $removeParents Remove parent achievements if the child achievement is present.
	 * @param bool $removeDeleted Remove deleted achievements.
	 *
	 * @return array CheevosAchievement objects.
	 */
	public static function pruneAchievements(
		array $toPrune,
		bool $removeParents = true,
		bool $removeDeleted = true
	): array {
		[ $achievements, $statuses ] = $toPrune;
		$_achievements = $achievements;
		if ( count( $_achievements ) ) {
			$preserveAchs = [];
			if ( $removeParents && count( $statuses ) ) {
				foreach ( $statuses as $statusId => $status ) {
					if ( !$status->isEarned() ) {
						continue;
					}
					if ( !isset( $_achievements[$status->getAchievement_Id()] ) ) {
						continue;
					}
					$achievement = $_achievements[$status->getAchievement_Id()];
					if ( $achievement->getParent_Id() > 0 ) {
						continue;
					}
					if ( $removeDeleted && $achievement->getDeleted_At() > 0 ) {
						unset( $statuses[$statusId] );
						unset( $_achievements[$achievement->getId()] );
						continue;
					}
					$fixChildrenStatus[$status->getAchievement_Id()][$status->getSite_Key()][$status->getUser_Id()] =
						$statusId;
				}
				foreach ( $statuses as $statusId => $status ) {
					if ( isset( $_achievements[$status->getAchievement_Id()] ) ) {
						$achParentId = $_achievements[$status->getAchievement_Id()]->getParent_Id();
						if (
							$achParentId > 0 &&
							isset( $fixChildrenStatus[$achParentId][$status->getSite_Key()][$status->getUser_Id()] )
						) {
							$parentStatusId =
								$fixChildrenStatus[$achParentId][$status->getSite_Key()][$status->getUser_Id()];
							if ( isset( $statuses[$parentStatusId] ) ) {
								$status->copyFrom( $statuses[$parentStatusId] );
								$status->setReadOnly();
								if ( $status->isEarned() ) {
									$preserveAchs[$status->getAchievement_Id()] = true;
								}
								unset( $statuses[$parentStatusId] );
							}
						} else {
							if ( $status->isEarned() ) {
								$preserveAchs[$status->getAchievement_Id()] = true;
							}
						}
					}
				}
			}
			foreach ( $_achievements as $achievement ) {
				if (
					$removeParents &&
					$achievement->getParent_Id() > 0 &&
					!array_key_exists( $achievement->getParent_Id(), $preserveAchs )
				) {
					unset( $_achievements[$achievement->getParent_Id()] );
				}

				if ( $removeDeleted && $achievement->getDeleted_At() > 0 ) {
					unset( $_achievements[$achievement->getId()] );
				}
			}
		}
		return [ $_achievements, $statuses ];
	}

	/**
	 * When displaying "Requires" criteria it may refer to a parent achievement that has been succeeded by a child
	 * achievement. This corrects it for display purposes.
	 *
	 * @param CheevosAchievement[] $achievements CheevosAchievement objects.
	 *
	 * @return CheevosAchievement[] CheevosAchievement objects.
	 */
	public static function correctCriteriaChildAchievements( array $achievements ): array {
		if ( count( $achievements ) ) {
			$children = self::getParentToChild( $achievements );
			if ( count( $children ) ) {
				foreach ( $achievements as $id => $achievement ) {
					$requiredIds = $achievement->getCriteria()->getAchievement_Ids();
					foreach ( $requiredIds as $key => $requiresAid ) {
						if ( isset( $children[$requiresAid] ) ) {
							$requiredIds[$key] = $children[$requiresAid];
						}
					}
					$achievement->getCriteria()->setAchievement_Ids( $requiredIds );
					$achievement->setReadOnly();
				}
			}
		}
		return $achievements;
	}

	/**
	 * Get an array of child information for parents.
	 *
	 * @param array $achievements CheevosAchievement objects.
	 *
	 * @return array Array of parent_id => child_id.
	 */
	public static function getParentToChild( array $achievements ): array {
		$children = [];
		if ( count( $achievements ) ) {
			foreach ( $achievements as $achievement ) {
				if ( $achievement->getParent_Id() ) {
					$children[$achievement->getParent_Id()] = $achievement->getId();
				}
			}
		}
		return $children;
	}

	/**
	 * Get achievement IDs that require this achievement.
	 *
	 * @return array|null Array achievement IDs that require this achievement.
	 */
	public function getRequiredBy(): ?array {
		$dsSiteKey = CheevosHelper::getSiteKey();

		if ( $this->requiredBy !== null ) {
			return $this->requiredBy;
		}

		$this->requiredBy = [];
		$achievements = $this->achievementService->getAchievements( $dsSiteKey );
		foreach ( $achievements as $id => $achievement ) {
			$requiredIds = $achievement->getCriteria()->getAchievement_Ids();
			if ( in_array( $this->getId(), $requiredIds ) ) {
				$this->requiredBy[(
					$achievement->getParent_Id() > 0 ?
						$achievement->getParent_Id() :
						$achievement->getId()
				)] = $achievement->getId();
			}
		}
		$this->requiredBy = array_unique( $this->requiredBy );
		sort( $this->requiredBy );

		return $this->requiredBy;
	}
}
