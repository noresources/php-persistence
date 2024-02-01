<?php

/**
 * Copyright © 2023 - 2024 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package Persistence
 */
namespace NoreSources\Persistence;

use Doctrine\Common\EventManager;
use Doctrine\Persistence\ObjectRepository;
use Doctrine\Persistence\Mapping\ClassMetadataFactory;
use Doctrine\Persistence\Mapping\Driver\MappingDriver;
use NoreSources\Persistence\Cache\CacheItemPoolAwareInterface;
use NoreSources\Persistence\Cache\CacheItemPoolProviderInterface;
use NoreSources\Persistence\Cache\Traits\CacheItemPoolReferenceTrait;
use NoreSources\Persistence\Mapping\GenericClassMetadataFactory;
use NoreSources\Persistence\Mapping\Driver\MappingDriverProviderInterface;
use Psr\Container\ContainerInterface;
use ArrayAccess;

/**
 * Object manager and repository configuration
 */
class Configuration implements MappingDriverProviderInterface,
	CacheItemPoolAwareInterface, CacheItemPoolProviderInterface,
	ObjectListenerResolverProviderInterface
{

	use CacheItemPoolReferenceTrait;

	/**
	 *
	 * @return \Doctrine\Common\EventManager
	 */
	public function getEventManager()
	{
		if (!isset($this->eventManager))
			$this->eventManager = new EventManager();
		return $this->eventManager;
	}

	/**
	 *
	 * @return mixed $this->objectListenerResolver))
	 */
	public function getObjectListenerResolver()
	{
		if (!isset($this->objectListenerResolver))
			$this->objectListenerResolver = new ObjectListenerResolver();
		return $this->objectListenerResolver;
	}

	/**
	 *
	 * @param ObjectListenerResolver|ContainerInterface|ArrayAccess|array $resolver
	 *        	Object listener resolver
	 */
	public function setObjectListenerResolver($resolver)
	{
		$this->objectListenerResolver = $resolver;
	}

	public function getMappingDriver()
	{
		return $this->mappingDriver;
	}

	public function setMappingDriver(MappingDriver $driver)
	{
		$this->mappingDriver = $driver;
	}

	/**
	 *
	 * @return ClassMetadataFactory
	 */
	public function getMetadataFactory()
	{
		if (!isset($this->metadataFactory))
		{
			if (isset($this->mappingDriver))
			{
				$this->metadataFactory = new GenericClassMetadataFactory(
					$this->mappingDriver);
			}
		}

		return $this->metadataFactory;
	}

	public function setMetadataFactory(ClassMetadataFactory $factory)
	{
		$this->metadataFactory = $factory;
	}

	/**
	 *
	 * @var EventManager
	 */
	private $eventManager;

	/**
	 *
	 * @var ContainerInterface|ArrayAccess|array
	 */
	private $objectListenerResolver;

	/**
	 *
	 * @var ObjectRepository
	 */
	private $objectRepositoryFactory;

	/**
	 *
	 * @var MappingDriver
	 */
	private $mappingDriver;

	/**
	 *
	 * @var ClassMetadataFactory
	 */
	private $metadataFactory;
}
