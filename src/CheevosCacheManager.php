<?php

namespace Cheevos;

use Wikimedia\ObjectCache\WANObjectCache;

class CheevosCacheManager {
	private const VERSION_KEY = [ 'cheevos', 'apicache', 'version' ];
	private ?int $version = null;

	public function __construct(
		private readonly WANObjectCache $cache
	) {
	}

	/**
	 * Get the current global version value used in cache keys
	 */
	public function getVersion(): int {
		if ( $this->version !== null ) {
			return $this->version;
		}

		$key = $this->cache->makeGlobalKey( ...self::VERSION_KEY );
		$this->version = $this->cache->get( $key ) ?? 1;

		return $this->version;
	}

	/**
	 * Get a cache key that includes the current global version
	 */
	public function getVersionedKey( string ...$parts ): string {
		$fullParts = array_merge( [ 'cheevos', 'apicache' ], $parts, [ 'v' . $this->getVersion() ] );
		return $this->cache->makeKey( ...$fullParts );
	}

	/**
	 * Invalidate all cache entries by bumping the global version
	 */
	public function invalidate(): void {
		$key = $this->cache->makeGlobalKey( ...self::VERSION_KEY );
		$this->cache->touchCheckKey( $key );

		// invalidate local cache so next getVersion() gets fresh version
		$this->version = null;
	}
}
