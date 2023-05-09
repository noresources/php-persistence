<?php

/**
 * Copyright © 2024 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package Persistence
 */
namespace NoreSources\Persistence\Cache\Traits;

use Psr\Cache\CacheItemPoolInterface;

/**
 * Implements CacheItemPoolAwareInterface and CacheItemPoolProviderInterface
 */
trait CacheItemPoolReferenceTrait
{

	/**
	 *
	 * @return \Psr\Cache\CacheItemPoolInterface
	 */
	public function getCache()
	{
		return $this->cacheItemPool;
	}

	/**
	 *
	 * @param CacheItemPoolInterface $cacheItemPool
	 *        	Cache implementation
	 */
	public function setCache(
		CacheItemPoolInterface $cacheItemPool = null)
	{
		$this->cacheItemPool = $cacheItemPool;
	}

	/**
	 *
	 * @var CacheItemPoolInterface
	 */
	private $cacheItemPool;
}
