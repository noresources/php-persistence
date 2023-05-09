<?php

/**
 * Copyright © 2023 - 2024 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package Persistence
 */
namespace NoreSources\Persistence\Mapping\Traits;

use Doctrine\Persistence\Mapping\ClassMetadata;
use Doctrine\Persistence\Mapping\Driver\MappingDriver;
use NoreSources\Persistence\Cache\Traits\CacheItemPoolReferenceTrait;
use NoreSources\Type\TypeConversion;
use NoreSources\Type\TypeDescription;

/**
 * Implements ClassMetadataFactory interface
 * based on a MappingDriver
 */
trait MappingDriverClassMetadataFactoryTrait
{
	use CacheItemPoolReferenceTrait;

	public function getAllMetadata()
	{
		$list = [];
		foreach ($this->mappingDriver->getAllClassNames() as $className)
		{
			$list[] = $this->getMetadataFor($className);
		}
		return $list;
	}

	public function getMetadataFor($className)
	{
		if (isset($this->runtimeCache) &&
			\array_key_exists($className, $this->runtimeCache))
			return $this->runtimeCache[$className];

		$cache = $this->getCache();
		$item = null;
		if ($cache)
		{
			$key = $this->getCacheKey($className);
			$item = $cache->getItem($key);
			if ($item->isHit())
			{
				$value = $item->get();
				$this->runtimeCache[$className] = $value;
				return $value;
			}
		}

		if (!isset($this->classMetadataClass))
			throw new \RuntimeException('No class metadata class set.');
		$metadata = $this->classMetadataClass->newInstance($className);
		$this->mappingDriver->loadMetadataForClass($className, $metadata);
		$this->setMetadataFor($className, $metadata);
		$this->postInitializeMetadata($metadata);
		if ($item)
		{
			$item->set($metadata);
			$cache->saveDeferred($item);
		}
		return $metadata;
	}

	public function isTransient($className)
	{
		return $this->mappingDriver->isTransient($className);
	}

	public function hasMetadataFor($className)
	{
		if (!isset($this->mappingDriver))
			throw new \RuntimeException(
				MappingDriver::class . ' not set.');
		return \in_array($className,
			$this->mappingDriver->getAllClassNames());
	}

	public function setMetadataFor($className, $class)
	{
		if (!isset($this->runtimeCache))
			$this->runtimeCache = [];

		$cache = $this->getCache();
		if ($cache)
		{}

		$this->runtimeCache[$className] = $class;
	}

	/**
	 *
	 * @param \ReflectionClass|string $class
	 *        	Concrete ClassMetadata class
	 */
	public function setMetadataClass($class)
	{
		if (\is_string($class))
			$class = new \ReflectionClass($class);
		if (!\is_object($class))
			throw new \InvalidArgumentException(
				\ReflectionClass::class . ' or class name expected');

		if (!($class instanceof \ReflectionClass))
		{
			$c = new \ReflectionClass(\get_class($class));
			if (!($c instanceof \ReflectionClass))
				throw new \InvalidArgumentException(
					TypeDescription::getName($class) . ' is not a ' .
					\ReflectionClass::class);
			$class = $c;
		}

		$this->classMetadataClass = $class;
	}

	/**
	 * Metadata post-creation task
	 *
	 * @param unknown $metadata
	 */
	public function postInitializeMetadata($metadata)
	{}

	public function getMappingDriver()
	{
		return $this->mappingDriver;
	}

	/**
	 *
	 * @param MappingDriver $mappingDriver
	 */
	public function setMappingDriver(MappingDriver $mappingDriver)
	{
		$this->mappingDriver = $mappingDriver;
	}

	public function getCacheKey($className)
	{
		$cacheRegion = (isset($this->cacheRegion)) ? $this->cacheRegion : static::class;
		$metadataClass = ClassMetadata::class;
		if (isset($this->classMetadataClass))
			$metadataClass = $this->classMetadataClass->getName();
		$key = $cacheRegion . '__' . $metadataClass . '__' . $className;
		$key = \str_replace('\\', '_', $key);
		return $key;
	}

	public function setCacheRegion($cacheRegion)
	{
		$this->cacheRegion = TypeConversion::toString($cacheRegion);
	}

	public function clearRuntimeCache()
	{
		$this->runtimeCache = [];
	}

	/**
	 *
	 * @varstring
	 */
	private $cacheRegion;

	/**
	 *
	 * @var \ReflectionClass
	 */
	private $classMetadataClass;

	/**
	 *
	 * @var MappingDriver
	 */
	private $mappingDriver;

	/**
	 *
	 * @var ClassMetadata[]
	 */
	private $runtimeCache;
}
