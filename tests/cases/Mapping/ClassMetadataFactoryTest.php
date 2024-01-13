<?php

/**
 * Copyright © 2023 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package Persistence
 */
namespace NoreSources\Persistence\TestCase\Mapping;

use Doctrine\Persistence\Mapping\ClassMetadata;
use NoreSources\Persistence\Mapping\ClassMetadataFactory;
use NoreSources\Persistence\Mapping\Driver\ReflectionDriver;
use NoreSources\Persistence\TestData\User;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Redis;

class ClassMetadataFactoryTest extends \PHPUnit\Framework\TestCase
{

	public function testCache()
	{
		$cacheItemPool = $this->createRedisCache();
		if (!$cacheItemPool)
			$cacheItemPool = new ArrayAdapter();

		$this->assertInstanceOf(CacheItemPoolInterface::class,
			$cacheItemPool, 'Cache item pool');

		$mappingDriver = new ReflectionDriver(
			[
				__DIR__ . '/../../reference/src'
			]);
		$factory = new ClassMetadataFactory();
		$factory->setMappingDriver($mappingDriver);

		$metadata = $factory->getMetadataFor(User::class);
		$this->assertInstanceOf(ClassMetadata::class, $metadata,
			'Got User class metadata');

		$metadataAgainWithoutCacheButAlreadyLoaded = $factory->getMetadataFor(
			User::class);
		$this->assertInstanceOf(ClassMetadata::class,
			$metadataAgainWithoutCacheButAlreadyLoaded,
			'Got User class metadata once again, without caching');
		$this->assertTrue(
			$metadata === $metadataAgainWithoutCacheButAlreadyLoaded,
			'Both metadata are same object');

		$factory->setCache($cacheItemPool);
		$factory->setCacheRegion(time());
		$factory->clearRuntimeCache();

		$key = $factory->getCacheKey(User::class);
		$item = $factory->getCache()->getItem($key);
		$this->assertFalse($item->isHit(), 'Not yet cached');

		$metadata = $factory->getMetadataFor(User::class);
		$item = $factory->getCache()->getItem($key);
		$this->assertTrue($item->isHit(),
			'Metadata was saved to second persistent cache');

		$metadataFromRuntimeCache = $factory->getMetadataFor(
			User::class);

		$this->assertInstanceOf(ClassMetadata::class,
			$metadataFromRuntimeCache, 'From runtime cache');
		$item = $factory->getCache()->getItem($key);
		$metadataFromPersistentCache = $item->get();
		$this->assertInstanceOf(ClassMetadata::class,
			$metadataFromPersistentCache, 'From persistent cache');
		$this->assertFalse(
			$metadataFromRuntimeCache === $metadataFromPersistentCache,
			'metadata cached in caches differs');
		$this->assertEquals($metadataFromRuntimeCache,
			$metadataFromPersistentCache,
			'Metadata from both caches are equivalent.');
	}

	public function createRedisCache()
	{
		if (!\extension_loaded('redis'))
			return;
		try
		{
			$redis = new Redis();
			$redis->connect('127.0.0.1');
			if (\class_exists(RedisAdapter::class))
				return new RedisAdapter($redis);
		}
		catch (\Exception $e)
		{}
	}
}
