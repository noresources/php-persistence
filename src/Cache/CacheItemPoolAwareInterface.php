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
 * Represents an object that own a reference to a CacheItemPoolInterface
 */
interface CacheItemPoolAwareInterface
{

	/**
	 * Set the cache implementation
	 *
	 * @param CacheItemPoolIter|NULL $cacheItemPool
	 *        	Cache implementation
	 */
	function setCache(CacheItemPoolInterface $cacheItemPool = null);
}
