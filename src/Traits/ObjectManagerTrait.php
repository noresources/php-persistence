<?php

/**
 * Copyright © 2023 - 2024 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package Persistence
 */
namespace NoreSources\Persistence\Traits;

use Doctrine\Persistence\ObjectRepository;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Doctrine\Persistence\Mapping\ClassMetadata;
use Doctrine\Persistence\Mapping\ClassMetadataFactory;
use NoreSources\Container\Container;
use NoreSources\Persistence\NotManagedException;
use NoreSources\Persistence\ObjectComparer;
use NoreSources\Persistence\ObjectContainerInterface;
use NoreSources\Persistence\ObjectPersisterInterface;
use NoreSources\Persistence\UnitOfWork;
use NoreSources\Persistence\Event\Event;
use NoreSources\Persistence\Event\ListenerInvoker;
use NoreSources\Persistence\Event\ListenerInvokerProviderInterface;
use NoreSources\Persistence\Id\ObjectIdentifier;
use NoreSources\Persistence\Mapping\ClassMetadataAdapter;
use NoreSources\Persistence\Mapping\PropertyMappingInterface;
use NoreSources\Persistence\Mapping\PropertyMappingProviderInterface;
use NoreSources\Persistence\Mapping\ReflectionService;

/**
 * Provide default implementation for ObjectManager.
 *
 * Implements PropertyMappingInterface
 */
trait ObjectManagerTrait
{

	/**
	 *
	 * @param string $className
	 *        	Class name
	 * @param mixed $id
	 *        	Object ID
	 * @return object|NULL
	 */
	public function find($className, $id)
	{
		$repository = $this->getRepository($className);
		if (!$repository)
			throw new \RuntimeException(
				'Failed to get ' . $className . ' repository');
		return $repository->find($id);
	}

	/**
	 *
	 * @param object $object
	 *        	Object to persist
	 */
	public function persist($object)
	{
		if (!isset($this->unitOfWork))
			$this->unitOfWork = $this->createUnitOfWork();

		$insert = $requireIdGeneration = false;
		$metadata = null;
		$className = null;

		$insert = !$this->contains($object);
		$idGenerator = null;

		if ($insert)
		{
			$className = \get_class($object);
			$metadata = $this->getClassMetadata($className);

			$idGenerator = ClassMetadataAdapter::getIdGenerator(
				$metadata);
			$requireIdGeneration = ($idGenerator !== null);
		}

		if (!$insert)
		{
			$this->unitOfWork->update($object);
			return;
		}

		$listenerInvoker = null;
		if ($this instanceof ListenerInvokerProviderInterface)
			$listenerInvoker = $this->getListenerInvoker();

		if ($listenerInvoker)
		{
			$event = new LifecycleEventArgs($object, $this);
			$listenerInvoker->invoke($metadata, Event::prePersist,
				$object, $event);
		}

		if ($requireIdGeneration)
		{
			$id = $idGenerator->generate($this, $object);
			$this->setObjectIdentifierValues($object, $id, $metadata);
		}

		$this->unitOfWork->insert($object);
		$id = $metadata->getIdentifierValues($object);
		if (\count($id))
			$this->unitOfWork->setObjectIdentity($object, $id);
	}

	/**
	 *
	 * @param object $object
	 *        	Object to remove
	 */
	public function remove($object)
	{
		if (!$this->contains($object))
			$this->notManagedException($object);
		if (!$this->unitOfWork)
			$this->unitOfWork = $this->createUnitOfWork();
		$this->unitOfWork->remove($object);
		$className = \get_class($object);
		if ($this->hasRepository($className))
		{
			$repository = $this->getRepository($className);
			if ($repository instanceof ObjectContainerInterface)
				$repository->detach($object);
		}
	}

	/**
	 *
	 * @param object $object
	 *        	Object to merge
	 * @deprecated Kept for interface compatibility. No alternative.
	 * @throws \Exception
	 */
	public function merge($object)
	{
		throw new \Exception('Deprecated');
	}

	/**
	 *
	 * @param string $objectName
	 *        	Unused
	 */
	public function clear($objectName = null)
	{
		if (isset($this->unitOfWork))
			$this->unitOfWork->clear(true);
		if ($objectName)
		{}
		else
			$this->foreachRepository(
				function ($repository) {
					if ($repository instanceof ObjectContainerInterface)
						$repository->detachAll();
				});
	}

	/**
	 *
	 * @param object $object
	 *        	Object to detach
	 */
	public function detach($object)
	{
		$found = false;

		if (($this->unitOfWork instanceof ObjectContainerInterface) &&
			$this->unitOfWork->contains($object))
		{
			$this->unitOfWork->detach($object);
			$found = true;
		}

		$className = \get_class($object);
		if ($this->hasRepository($className))
		{
			$repository = $this->getRepository($className);

			if ($repository instanceof ObjectContainerInterface &&
				$repository->contains($object))
			{
				$repository->detach($object);
				$found = true;
			}
		}

		if (!$found)
			$this->notManagedException($object);
	}

	/**
	 *
	 * @param object $object
	 *        	Object to refresh
	 * @throws \RuntimeException
	 */
	public function refresh($object)
	{
		if (!$this->contains($object))
			$this->notManagedException($object);

		$this->detach($object);

		$className = \get_class($object);
		$class = $this->getClassMetadata($className);
		$id = $class->getIdentifierValues($object);
		$existing = $this->find($className, $id);

		$repository = $this->getRepository($className);
		$populator = null;
		if ($repository instanceof PropertyMappingProviderInterface)
			$populator = $repository->getPropertyMapper();
		if (!isset($populator))
			$populator = $this->defaultObjectSerialization;

		if (!$populator)
			throw new \RuntimeException(
				'No PropertyMappingInterface found for ' . $className);

		$populator->assignObjectProperties($object, $existing);
		if ($repository instanceof ObjectContainerInterface)
			$repository->attach($object);
	}

	/**
	 *
	 * @throws \RuntimeException
	 */
	public function flush()
	{
		if (!isset($this->unitOfWork))
			return;

		/**
		 *
		 * @var ListenerInvoker $listenerInvoker
		 */
		$listenerInvoker = null;
		if ($this instanceof ListenerInvokerProviderInterface)
			$listenerInvoker = $this->getListenerInvoker();

		$tasks = $this->unitOfWork->getTasks();
		foreach ($tasks as $task)
		{
			$object = $task[UnitOfWork::KEY_OBJECT];
			$operation = $task[UnitOfWOrk::KEY_OPERATION];
			$className = \get_class($object);
			$metadata = $this->getClassMetadata($className);
			$persister = $this->getPersister($className);

			if (!$persister)
				throw new \RuntimeException(
					'No persister defined for ' . $className);

			$event = new LifecycleEventArgs($object, $this);

			$initialId = NULL;
			$currentId = null;
			$restorId = null;
			if (($initialId = Container::keyValue($task,
				UnitOfWork::KEY_IDENTITY, NULL)) !== NULL)
			{
				$currentId = $metadata->getIdentifierValues($object);
				if (!ObjectIdentifier::equals($initialId, $currentId))
				{
					$restorId = true;
					$this->setObjectIdentifierValues($object, $initialId,
						$metadata);
				}
			}

			switch ($operation)
			{
				case UnitOfWork::OPERATION_INSERT:

					$persister->persist($object);
					if ($initialId === NULL)
					{
						$id = $metadata->getIdentifierValues($object);
						$this->unitOfWork->setObjectIdentity($object,
							$id);
					}

					$this->unitOfWork->setObjectOriginalCopy($object,
						clone $object);

					if ($listenerInvoker)
						$listenerInvoker->invoke($metadata,
							Event::postPersist, $object, $event);
				break;
				case UnitOfWork::OPERATION_UPDATE:

					$repository = null;
					$original = null;
					if ($this->hasRepository($className))
					{
						$repository = $this->defaultGetRepository(
							$className);
						if ($repository instanceof ObjectContainerInterface)
							$original = $repository->getObjectOriginalCopy(
								$object);
					}

					if (!$original)
						$original = $this->unitOfWork->getObjectOriginalCopy(
							$object);

					$eventName = Event::preUpdate;

					if ($listenerInvoker &&
						$listenerInvoker->hasListenerFor($metadata,
							$eventName))
					{
						$reflectionService = ReflectionService::getInstance();

						$changeSet = ObjectComparer::computeChangeSet(
							$metadata, $reflectionService, $original,
							$object);

						$e = new \Doctrine\Persistence\Event\PreUpdateEventArgs(
							$object, $this, $changeSet);

						$listenerInvoker->invoke($metadata, $eventName,
							$object, $e);
					}

					$persister->persist($object);
					if ($initialId === NULL)
					{
						$id = $metadata->getIdentifierValues($object);
						$this->unitOfWork->setObjectIdentity($object,
							$id);
					}

					if ($repository &&
						($repository instanceof ObjectContainerInterface))
						$repository->setObjectOriginalCopy($object,
							clone $object);

					$this->unitOfWork->setObjectOriginalCopy($object,
						clone $object);

					if ($listenerInvoker)
						$listenerInvoker->invoke($metadata,
							Event::postUpdate, $object, $event);

				break;
				case UnitOfWork::OPERATION_REMOVE:
					if ($listenerInvoker)
						$listenerInvoker->invoke($metadata,
							Event::preRemove, $object, $event);

					$persister->remove($object);

					$this->unitOfWork->setObjectOriginalCopy($object,
						clone $object);

					if ($listenerInvoker)
						$listenerInvoker->invoke($metadata,
							Event::postRemove, $object, $event);
				break;
			}

			if ($restorId)
				$this->setObjectIdentifierValues($object, $currentId);
		}
		$this->unitOfWork->clear(false);
	}

	/**
	 *
	 * @param string $className
	 * @throws NotManagedException::
	 * @return ObjectRepository
	 */
	public function getRepository($className)
	{
		return $this->defaultGetRepository($className);
	}

	/**
	 * Indicates if a repository is defined for the given class name
	 *
	 * @param string $className
	 *        	Class name
	 * @return boolean
	 */
	public function hasRepository($className)
	{
		if (!isset($this->objectRepositories))
			return false;
		return Container::keyExists($this->objectRepositories,
			$className);
	}

	/**
	 *
	 * @param string $className
	 *        	Class name
	 * @return boolean
	 */
	public function hasPersister($className)
	{
		if (!isset($this->objectPersisters))
			return false;
		return Container::keyExists($this->objectPersisters, $className);
	}

	/**
	 *
	 * @param string $className
	 * @return ClassMetadata
	 */
	public function getClassMetadata($className)
	{
		$factory = $this->getMetadataFactory();
		if (!isset($factory))
			throw new \RuntimeException(
				'Metadata factory not configured');
		return $factory->getMetadataFor($className);
	}

	public function foreachPersister($callable, $arguments = array())
	{
		if (!isset($this->objectPersisters))
			return;

		foreach ($this->objectPersisters as $p)
			\call_user_func($callable, $p, ...$arguments);
	}

	public function foreachRepository($callable, $arguments = array())
	{
		if (!isset($this->objectRepositories))
			return;

		foreach ($this->objectRepositories as $r)
			\call_user_func($callable, $r, ...$arguments);
	}

	/**
	 *
	 * @return ClassMetadataFactory
	 */
	/**
	 *
	 * @return ClassMetadataFactory
	 */
	public function getMetadataFactory()
	{
		return $this->metadataFactory;
	}

	/**
	 *
	 * @param object $object
	 *        	Object to initialize
	 */
	public function initializeObject($object)
	{}

	/**
	 *
	 * @param object $object
	 * @return boolean Object
	 */
	public function contains($object)
	{
		if ($this->unitOfWork)
		{
			if ($this->unitOfWork instanceof ObjectContainerInterface &&
				$this->unitOfWork->contains($object))
				return true;
		}

		$className = \get_class($object);
		if ($this->hasRepository($className))
		{
			$repository = $this->getRepository($className);
			if ($repository instanceof ObjectContainerInterface)
				return $repository->contains($object);
		}

		return false;
	}

	/**
	 * Set the object populator to use when refreshing an object
	 * if the object repository is not an PropertyMappingProviderInterface
	 *
	 * @param PropertyMappingInterface $objectSerialization
	 *        	Object populator
	 */
	public function setObjectSerialization(
		PropertyMappingInterface $objectSerialization)
	{
		$this->objectSerialization = $objectSerialization;
	}

	/**
	 *
	 * @return PropertyMappingInterface
	 */
	public function getPropertyMapper()
	{
		return $this->defaultObjectSerialization;
	}

	/**
	 * Set the object repository for the given class
	 *
	 * @param string $className
	 *        	Class name
	 * @param ObjectRepository $repository
	 */
	public function setRepository($className,
		ObjectRepository $repository)
	{
		if (!isset($this->objectRepositories))
			$this->objectRepositories = [];
		Container::setValue($this->objectRepositories, $className,
			$repository);
		if ($repository instanceof ObjectPersisterInterface)
		{
			if (!isset($this->objectPersisters))
				$this->objectPersisters = [];
			if (!Container::keyExists($this->objectPersisters,
				$className))
				Container::setValue($this->objectPersisters, $className,
					$repository);
		}
	}

	/**
	 * Get the object persister for the given class
	 *
	 * @param string $className
	 *        	Class name
	 * @return NULL|ObjectPersisterInterface
	 */
	public function getPersister($className)
	{
		return $this->defaultGetPersister($className);
	}

	/**
	 * Set the object persister for the given class
	 *
	 * @param string $className
	 *        	Class name
	 * @param ObjectPersisterInterface $persister
	 */
	public final function setPersister($className,
		ObjectPersisterInterface $persister)
	{
		if (!isset($this->objectPersisters))
			$this->objectPersisters = [];
		Container::setValue($this->objectPersisters, $className,
			$persister);
	}

	/**
	 *
	 * @param ClassMetadataFactory $factory
	 */
	public final function setMetadataFactory(
		ClassMetadataFactory $factory)
	{
		$this->metadataFactory = $factory;
	}

	/**
	 *
	 * @return \NoreSources\Persistence\UnitOfWork
	 * @deprecated Used for tests only
	 */
	public function getUnitOfWork()
	{
		return $this->unitOfWork;
	}

	/**
	 *
	 * @param object $object
	 *        	Object to test
	 * @return boolean FALSE if object identifier is missing or incomplete
	 */
	protected function isNewObject($object, ClassMetadata $class = null)
	{
		if (!$class)
			$class = $this->getClassMetadata(\get_class($object));
		$idFields = $class->getIdentifierFieldNames();
		$id = $class->getIdentifierValues($object);
		if (\count($id) < \count($idFields))
			return true;
		foreach ($id as $value)
		{
			if (\is_null($value))
				return true;
		}
		return false;
	}

	protected function assignObjectPropertiesId($object)
	{
		$class = $this->getClassMetadata(\get_class($object));
		$fields = $class->getIdentifierFieldNames();
		$id = $class->getIdentifierValues($object);
		foreach ($fields as $field)
		{
			if (isset($id[$field]))
				continue;
			$fieldId = $this->generateObjectId($class, $object, $field);

			/**
			 *
			 * @todo reflection service, get class , set property
			 */
			throw new \Exception('Not implemented');
		}
	}

	protected function generateObjectId(ClassMetadata $class, $object,
		$field)
	{
		/**
		 *
		 * @todo get generator type, etc.
		 */
		throw new \Exception('Not implemented');
	}

	/**
	 *
	 * @return \NoreSources\Persistence\UnitOfWork
	 */
	protected function createUnitOfWork()
	{
		$u = new UnitOfWork();
		return $u;
	}

	protected function setObjectIdentifierValues($object,
		$generatedValues, ClassMetadata $metadata = null)
	{
		$metadata = $this->getClassMetadata(\get_class($object));
		if (\method_exists($metadata, 'setIdentifierValues'))
		{
			$metadata->setIdentifierValues($object, $generatedValues);
			return;
		}

		$names = $metadata->getIdentifierFieldNames();
		$values = $metadata->getIdentifierValues($object);
		foreach ($names as $name)
		{
			if (Container::keyExists($values, $name) &&
				!\is_null($values[$name]))
				continue;
			if (Container::isArray($generatedValues))
				Container::setValue($object, $name,
					Container::keyValue($generatedValues, $name));
			else
				Container::setValue($object, $name, $generatedValues);
		}
	}

	/**
	 *
	 * @param string $className
	 *        	Class name
	 * @return NULL|mixed|array|\ArrayAccess|\Psr\Container\ContainerInterface|\Traversable
	 */
	protected function defaultGetRepository($className)
	{
		if (!isset($this->objectRepositories))
			return null;
		return Container::keyValue($this->objectRepositories, $className,
			NULL);
	}

	/**
	 *
	 * @param string $className
	 *        	Class name
	 * @return NULL|mixed|array|\ArrayAccess|\Psr\Container\ContainerInterface|\Traversable
	 */
	protected function defaultGetPersister($className)
	{
		if (!isset($this->objectPersisters))
			return null;
		return Container::keyValue($this->objectPersisters, $className,
			NULL);
	}

	/**
	 *
	 * @param object|string $classOrObject
	 * @throws NotManagedException
	 */
	protected function notManagedException($classOrObject)
	{
		throw new NotManagedException($classOrObject, $this);
	}

	/**
	 *
	 * @var ObjectRepository[]
	 */
	private $objectRepositories;

	/**
	 *
	 * @var ObjectPersisterInterface
	 */
	private $objectPersisters;

	/**
	 *
	 * @var PropertyMappingInterface
	 */
	private $defaultObjectSerialization;

	/**
	 *
	 * @var ClassMetadataFactory
	 */
	private $metadataFactory;

	/**
	 *
	 * @var UnitOfWork
	 */
	private $unitOfWork;
}
