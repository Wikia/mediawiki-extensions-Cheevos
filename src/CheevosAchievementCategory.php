<?php
/**
 * Cheevos
 * Cheevos Achievement Category Model
 *
 * @package   Cheevos
 * @author    Cameron Chunn
 * @copyright (c) 2017 Curse Inc.
 * @license   GPL-2.0-or-later
 * @link      https://gitlab.com/hydrawiki/extensions/cheevos
 */

namespace Cheevos;

use MediaWiki\MediaWikiServices;

class CheevosAchievementCategory extends CheevosModel {

	private AchievementService $achievementService;

	public function __construct( ?array $data = null ) {
		$this->container['id'] = isset( $data['id'] ) && is_int( $data['id'] ) ? $data['id'] : 0;
		$this->container['name'] = isset( $data['name'] ) && is_array( $data['name'] ) ? $data['name'] : [];
		$this->container['slug'] = isset( $data['slug'] ) && is_string( $data['slug'] ) ? $data['slug'] : '';
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
		$this->achievementService = MediaWikiServices::getInstance()->getService( AchievementService::class );
	}

	/**
	 * Save category up to the service.
	 *
	 * @return array Success Result
	 */
	public function save(): array {
		if ( $this->readOnly ) {
			throw new CheevosException( "This object is read only and can not be saved." );
		}

		if ( $this->getId() !== null ) {
			return $this->achievementService->updateCategory( $this->getId(), $this->toArray() );
		}

		return $this->achievementService->createCategory( $this->toArray() );
	}

	/**
	 * Check if category exists
	 *
	 * @return bool
	 */
	public function exists(): bool {
		if ( $this->getId() <= 0 ) {
			return false;
		}

		try {
			// Throws an error if it doesn't exist.
			$this->achievementService->getCategory( $this->getId() );
			return true;
		} catch ( CheevosException $e ) {
			return false;
		}
	}

	/**
	 * Get name (by current language)
	 *
	 * @return string
	 */
	public function getName(): string {
		if ( $this->container['name'] == null || !count( $this->container['name'] ) ) {
			return "";
		}
		$code = CheevosHelper::getUserLanguage();
		if ( array_key_exists( $code, $this->container['name'] ) && isset( $this->container['name'][$code] ) ) {
			return $this->container['name'][$code];
		}

		return reset( $this->container['name'] );
	}

	/**
	 * Set the name for this category with automatic language code selection.
	 */
	public function setName( string $name ): void {
		$code = CheevosHelper::getUserLanguage();
		if ( !is_array( $this->container['name'] ) ) {
			$this->container['name'] = [ $code => $name ];
		} else {
			$this->container['name'][$code] = $name;
		}
		if ( empty( $this->container['slug'] ) ) {
			$this->container['slug'] = $this->makeCanonicalTitle( $name );
		}
	}

	public function getTitle(): string {
		return $this->getName();
	}

	/** Transforms text into canonical versions safe for usage in URLs and Javascript data attributes. */
	private function makeCanonicalTitle( string $text ): string {
		$text = html_entity_decode( rawurldecode( trim( $text ) ), ENT_QUOTES, 'UTF-8' );
		$text = mb_strtolower( $text, 'UTF-8' );

		$text = str_replace( ' ', '-', $text );

		// Replace non-alpha numeric characters that would be bad for SEO
		$text = preg_replace( '#(?![a-zA-Z0-9_\-|\\\|/|\+]).*?#is', '', $text );

		// Replace other separators with dashes.
		$text = preg_replace( "#(_+|/+|\\\+|\+)#is", "-", $text );

		// Remove excess dashes.
		$text = preg_replace( "#(-+)#is", "-", $text );

		// Remove trailing dashes.
		return trim( $text, '-' );
	}

	/**
	 * Does this category roughly equal another category?
	 *
	 * @return bool
	 */
	public function sameAs( CheevosModel $model ): bool {
		foreach ( [ 'name', 'slug', 'deleted_at', 'deleted_by' ] as $field ) {
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
}
