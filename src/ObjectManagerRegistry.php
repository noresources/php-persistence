<?php

/**
 * Copyright © 2024 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package Persistence
 */
namespace NoreSources\Persistence;

use Doctrine\Persistence\ObjectManager;
use Doctrine\Persistence\Mapping\ClassMetadataFactory;
use NoreSources\Container\Container;
use NoreSources\Persistence\Mapping\ObjectManagerRegistryClassMetadataFactory;

class ObjectManagerRegistry implements ObjectManager, \IteratorAggregate
{

	public function __construct()
	{}

	/**
	 *
	 * {@inheritdoc}
	 * @see \Doctrine\Persistence\ObjectManager::initializeObject()
	 */
	public function initializeObject($obj)
	{
		$className = \get_class($obj);
		$objectManager = $this->getObjectManagerForClass($className);
		return $objectManager->initializeObject($obj);
	}

	/**
	 *
	 * {@inheritdoc}
	 * @see \Doctrine\Persistence\ObjectManager::getRepository()
	 */
	public function getRepository($className)
	{
		return $this->getObjectManagerForClass($className)->getRepository(
			$className);
	}

	/**
	 *
	 * {@inheritdoc}
	 * @see \Doctrine\Persistence\ObjectManager::clear()
	 */
	public function clear($objectName = null)
	{
		if ($objectName)
		{
			return $this->getObjectManagerForClass($objectName)->clear(
				$objectName);
		}

		foreach ($this->objectManagers as $objectManager)
			$objectManager->clear();
	}

	/**
	 *
	 * {@inheritdoc}
	 * @see \Doctrine\Persistence\ObjectManager::refresh()
	 */
	public function refresh($object)
	{
		$className = \get_class($object);
		return $this->getObjectManagerForClass($className)->refresh(
			$object);
	}

	/**
	 *
	 * {@inheritdoc}
	 * @see \Doctrine\Persistence\ObjectManager::remove()
	 */
	public function remove($object)
	{
		$className = \get_class($object);
		return $this->getObjectManagerForClass($className)->remove(
			$object);
	}

	/**
	 *
	 * {@inheritdoc}
	 * @see \Doctrine\Persistence\ObjectManager::contains()
	 */
	public function contains($object)
	{
		$className = \get_class($object);
		return $this->getObjectManagerForClass($className)->contains(
			$object);
	}

	/**
	 *
	 * {@inheritdoc}
	 * @see \Doctrine\Persistence\ObjectManager::flush()
	 */
	public function flush()
	{
		foreach ($this->objectManagers as $objectManager)
			$objectManager->flush();
	}

	/**
	 *
	 * {@inheritdoc}
	 * @see \Doctrine\Persistence\ObjectManager::find()
	 */
	public function find($className, $id)
	{
		return $this->getObjectManagerForClass($className)->find(
			$className, $id);
	}

	/**
	 *
	 * {@inheritdoc}
	 * @see \Doctrine\Persistence\ObjectManager::merge()
	 */
	public function merge($object)
	{
		$className = \get_class($object);
		return $this->getObjectManagerForClass($className)->merge(
			$object);
	}

	/**
	 *
	 * {@inheritdoc}
	 * @see \Doctrine\Persistence\ObjectManager::detach()
	 */
	public function detach($object)
	{
		$className = \get_class($object);
		return $this->getObjectManagerForClass($className)->detach(
			$object);
	}

	/**
	 *
	 * {@inheritdoc}
	 * @see \Doctrine\Persistence\ObjectManager::getClassMetadata()
	 */
	public function getClassMetadata($className)
	{
		return $this->getObjectManagerForClass($className)->getClassMetadata(
			$className);
	}

	/**
	 *
	 * {@inheritdoc}
	 * @see \Doctrine\Persistence\ObjectManager::persist()
	 */
	public function persist($object)
	{
		$className = \get_class($object);
		return $this->getObjectManagerForClass($className)->persist(
			$object);
	}

	/**
	 *
	 * {@inheritdoc}
	 * @see \Doctrine\Persistence\ObjectManager::getMetadataFactory()
	 */
	public function getMetadataFactory()
	{
		if (!isset($this->metadataFactory))
			$this->metadataFactory = new ObjectManagerRegistryClassMetadataFactory(
				$this);
		return $this->metadataFactory;
	}

	/**
	 * ObjectManager iterator
	 *
	 * @return \Iterator
	 */
	public function getIterator(): \Iterator
	{
		return new \ArrayIterator($this->objectManagers);
	}

	/**
	 *
	 * @param string $key
	 *        	Object manager key
	 * @return ObjectManager|NULL
	 */
	public function getObjectManager($key)
	{
		return Container::keyValue($this->objectManagers, $key);
	}

	/**
	 * Set, replace or remove an object manager
	 *
	 * @param string $key
	 *        	Object manager key
	 * @param ObjectManager $objectManager
	 *        	Object manager
	 */
	public function setObjectManager($key, ObjectManager $objectManager)
	{
		$existing = Container::keyValue($this->objectManagers, $key);
		if ($existing)
		{
			if ($existing === $objectManager)
				return;
			$this->classManager = \array_filter($this->classManager,
				function ($m) use ($existing) {
					return ($m !== $existing);
				});
		}
		if ($objectManager === null)
		{
			unset($this->objectManagers[$key]);
			return;
		}

		if (isset($this->metadataFactory))
			$this->metadataFactory->onManagerAdded($objectManager);

		$this->objectManagers[$key] = $objectManager;
	}

	/**
	 * Get the object manager that manage the given class
	 *
	 * @param string $className
	 *        	Class name
	 * @return NULL|\Doctrine\Persistence\ObjectManager|mixed|array|\ArrayAccess|\Psr\Container\ContainerInterface|\Traversable
	 */
	public function getObjectManagerForClass($className)
	{
		$objectManager = $this->findObjectManagerForClass($className);
		if ($objectManager === null)
			self::notManagedException($className);
		return $objectManager;
	}

	/**
	 * Find the object manager that manage the given class if any.
	 *
	 * @param string $className
	 *        	Class name
	 * @return mixed|array|\ArrayAccess|\Psr\Container\ContainerInterface|\Traversable|\Doctrine\Persistence\ObjectManager|NULL
	 */
	public function findObjectManagerForClass($className)
	{
		$existing = Container::keyValue($this->classManager, $className);
		if ($existing)
			return $existing;
		foreach ($this->objectManagers as $objectManager)
		{
			/**
			 *
			 * @var ClassMetadataFactory $factory
			 */
			$factory = $objectManager->getMetadataFactory();
			if ($factory->isTransient($className))
				continue;
			$this->classManager[$className] = $objectManager;
			return $objectManager;
		}
		return null;
	}

	/**
	 *
	 * @param string $className
	 *        	Class name
	 * @throws \InvalidArgumentException
	 */
	public static function notManagedException($className)
	{
		throw new \InvalidArgumentException(
			$className . ' is not managed');
	}

	/**
	 * Object managers
	 *
	 * @var ObjectManager[]
	 */
	private $objectManagers = [];

	/**
	 * Class -> object manager mapping cache
	 *
	 * @var ObjectManager[]
	 */
	private $classManager = [];

	/**
	 *
	 * @var ClassMetadataFactory
	 */
	private $metadataFactory;
}
