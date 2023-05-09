<?php

/**
 * Copyright © 2024 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package Persistence
 */
namespace NoreSources\Persistence\Cache;

use Psr\Cache\CacheItemPoolInterface;

/**
 * Represents an object that provide a reference to a cache implementation
 */
interface CacheItemPoolProviderInterface
{

	/**
	 * Get the cache implementation
	 *
	 * @return CacheItemPoolInterface
	 */
	function getCache();
}
