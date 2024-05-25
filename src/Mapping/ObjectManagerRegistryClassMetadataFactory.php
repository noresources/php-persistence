<?php

/**
 * Copyright © 2024 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package Persistence
 */
namespace NoreSources\Persistence\Mapping;

use Doctrine\Persistence\ObjectManager;
use Doctrine\Persistence\Mapping\ClassMetadataFactory;
use NoreSources\Container\Container;
use NoreSources\Persistence\ObjectManagerRegistry;

class ObjectManagerRegistryClassMetadataFactory implements
	ClassMetadataFactory
{

	public function __construct(ObjectManagerRegistry $registry)
	{
		$this->registry = $registry;
	}

	public function setMetadataFor($className, $class)
	{
		$objectManager = $this->registry->findObjectManagerForClass(
			$className);

		if ($objectManager)
			return $objectManager->getMetadataFactory()->setMetadataFor(
				$className, $class);
		$this->metadata[$className] = $class;
	}

	public function hasMetadataFor($className)
	{
		if (isset($this->metadata[$className]))
			return true;
		$objectManager = $this->registry->findObjectManagerForClass(
			$className);
		if (!$objectManager)
			return false;
		return $objectManager->getMetadataFactory()->hasMetadataFor(
			$className);
	}

	public function getAllMetadata()
	{
		$list = [];
		foreach ($this->registry as $objectManager)
			$list = \array_merge($list,
				$objectManager->getMetadataFactory()->getAllMetadata());
		return \array_merge($list, $this->metadata);
	}

	public function isTransient($className)
	{
		foreach ($this->registry->getIterator() as $manager)
		{
			/**
			 *
			 * @var ObjectManager $manager
			 * @var ClassMetadataFactory $factory
			 */
			$factory = $manager->getMetadataFactory();
			if (!$factory->isTransient($className))
				return false;
		}

		return true;
	}

	public function getMetadataFor($className)
	{
		$metadata = Container::keyValue($this->metadata, $className);
		if ($metadata)
			return $metadata;
		$objectManager = $this->registry->findObjectManagerForClass(
			$className);
		if (!$objectManager)
			return null;
		return $objectManager->getMetadataFactory()->getMetadataFor(
			$className);
	}

	/**
	 *
	 * @internal Used by ObjectManagerRegistry
	 *
	 *           Remove all cached class metadata that are now handled by the object manager
	 *
	 * @param ObjectManager $objectManager
	 *        	The newly added object manager
	 */
	public function onManagerAdded(ObjectManager $objectManager)
	{
		$factory = $objectManager->getMetadataFactory();
		$this->metadata = Container::filterKeys($this->metadata,
			function ($className) use ($factory) {
				return !$factory->hasMetadataFor($className);
			});
	}

	/**
	 *
	 * @var ObjectManagerRegistry
	 */
	private $registry;

	/**
	 *
	 * @var ClassMetadata[]
	 */
	private $metadata = [];
}
