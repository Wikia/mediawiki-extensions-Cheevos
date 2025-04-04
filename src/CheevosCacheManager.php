<?php

namespace Cheevos;

use Wikimedia\ObjectCache\WANObjectCache;

class CheevosCacheManager {
	private const VERSION_KEY = [ 'cheevos', 'apicache', 'version' ];

	public function __construct(
		private readonly WANObjectCache $cache
	) {
	}

	/**
	 * Get the current global version value used in cache keys
	 */
	public function getVersion(): int {
		$key = $this->cache->makeGlobalKey( ...self::VERSION_KEY );
		return $this->cache->get( $key ) ?? 1;
	}

	/**
	 * Get a cache key that includes the current global version
	 */
	public function getVersionedKey( string ...$parts ): string {
		$version = $this->getVersion();
		$fullParts = array_merge( [ 'cheevos', 'apicache' ], $parts, [ 'v' . $version ] );
		return $this->cache->makeKey( ...$fullParts );
	}

	/**
	 * Invalidate all cache entries by bumping the global version
	 */
	public function invalidate(): void {
		$key = $this->cache->makeGlobalKey( ...self::VERSION_KEY );
		$this->cache->touchCheckKey( $key );
	}
}
