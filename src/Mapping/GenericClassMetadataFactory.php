<?php

/**
 * Copyright © 2023 - 2024 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package Persistence
 */
namespace NoreSources\Persistence\Mapping;

use Doctrine\Persistence\Mapping\AbstractClassMetadataFactory;
use Doctrine\Persistence\Mapping\ClassMetadata;
use Doctrine\Persistence\Mapping\ReflectionService;
use Doctrine\Persistence\Mapping\Driver\MappingDriver;
use NoreSources\Container\Container;
use NoreSources\Persistence\Mapping\Driver\MappingDriverProviderInterface;
use NoreSources\Type\TypeDescription;

/**
 * Default implementation of a metadata factory based on Mapping driver
 */
class GenericClassMetadataFactory extends AbstractClassMetadataFactory implements
	MappingDriverProviderInterface, ClassMetadataAwareInterface
{

	/**
	 *
	 * @param MappingDriver $driver
	 *        	Mapping driver to use
	 */
	public function __construct(?MappingDriver $driver = null)
	{
		$this->setMetadataClass(GenericClassMetadata::class);
		if ($driver)
			$this->setMappingDriver($driver);
	}

	protected function isEntity(ClassMetadata $class)
	{
		if (\property_exists($class, 'isMappedSuperclass'))
			return !$class->isMappedSuperclass;
		return false;
	}

	protected function initializeReflection(ClassMetadata $class,
		ReflectionService $reflService)
	{
		$ignored = null;
		ClassMetadataAdapter::invokeMetadataMethod($ignoed, $class,
			'initializeReflection', $reflService);
	}

	protected function getDriver()
	{
		return $this->mappingDriver;
	}

	protected function doLoadMetadata(ClassMetadata $class,
		?ClassMetadata $parent, bool $rootEntityFound,
		array $nonSuperclassParents)
	{
		if ($parent)
			$this->copyParentMetadata($class, $parent);

		$this->getDriver()->loadMetadataForClass($class->getName(),
			$class);

		$this->finalizeMetadataRuntimeLoading($class, $parent,
			$rootEntityFound, $nonSuperclassParents);
	}

	/**
	 * Initialize class metadata by copying parent class metadata properties
	 *
	 * @param ClassMetadata $class
	 *        	Child class metadata
	 * @param ClassMetadata $parent
	 *        	Parent class metadata
	 */
	protected function copyParentMetadata(ClassMetadata $class,
		?ClassMetadata $parent)
	{
		foreach ([
			'inheritanceType',
			'discriminatorColumn',
			'idGeneratorType',
			'lifeCycleCallbacks'
		] as $name => $value)
		{
			$arguments = [];
			if (\is_integer($name))
			{
				$name = $value;
				$value = null;
			}

			if (ClassMetadataAdapter::retrieveMetadataElement($value,
				$parent, $name, ...$arguments))
				ClassMetadataAdapter::assignMetadataElement($class,
					$name, $value);
		}

		foreach ([
			'fields' => [
				'mappingsProperty' => 'fieldMappings',
				'mappingsGetter' => 'getFieldMappings',
				'addMethod' => 'addInheritedFieldMapping',
				'fallbackAddMethod' => 'mapField'
			],
			'associations' => [
				'mappingsProperty' => 'associationMappings',
				'mappingsGetter' => 'getAssociationMappings',
				'addMethod' => 'addInheritedAssociationMapping'
			]
		] as $set)
		{
			$mappings = null;

			if (!ClassMetadataAdapter::invokeMetadataMethod($mappings,
				$parent, $set['mappingsGetter']))
				ClassMetadataAdapter::retrieveMetadataProperty(
					$mappings, $parent, $set['mappingsProperty']);

			if (!Container::isTraversable($mappings))
				continue;

			$ignored = null;
			foreach ($mappings as $mapping)
			{
				$this->addMappingInheritanceInformation($mapping,
					$parent);

				if (ClassMetadataAdapter::invokeMetadataMethod($ignored,
					$class, $set['addMethod'], $mapping))
					continue;

				if (!isset($set['fallbackAddMethod']))
					continue;
				ClassMetadataAdapter::invokeMetadataElement($ignored,
					$class, $set['fallbackAddMethod'], $mapping);
			}
		}
	}

	/**
	 * Class metadata loading post-processing
	 *
	 * @param ClassMetadata $class
	 *        	Class metadata
	 * @param ClassMetadata $parent
	 *        	Parent class metadata
	 * @param bool $rootEntityFound
	 * @param array $nonSuperclassParents
	 */
	protected function finalizeMetadataRuntimeLoading(
		ClassMetadata $class, ?ClassMetadata $parent,
		bool $rootEntityFound, array $nonSuperclassParents)
	{
		/**
		 *
		 * @todo inherit ID generator ?
		 */
		foreach ([
			'parentClasses' => [
				$nonSuperclassParents
			]
		] as $name => $arguments)
		{
			ClassMetadataAdapter::assignMetadataElement($class, $name,
				...$arguments);
		}

		/**
		 *
		 * @todo EVENT
		 */

		$this->validateRuntimeMetadata($class, $parent);
	}

	protected function validateRuntimeMetadata(Classmetadata $class,
		?ClassMetadata $parent = null)
	{
		foreach ([
			'validateIdentifier' => [],
			'validateAssociations' => [],
			'validateLifecycleCallbacks' => [
				$this->getReflectionService()
			]
		] as $name => $arguments)
		{
			$returned = null;
			ClassMetadataAdapter::invokeMetadataMethod($returned, $class,
				$name, ...$arguments);
		}
	}

	protected function initialize()
	{}

	protected function wakeupReflection(ClassMetadata $class,
		ReflectionService $reflService)
	{
		$returned = null;
		ClassMetadataAdapter::invokeMetadataMethod($returned, $class,
			'wakeupReflection', $reflService);
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
	 *
	 * @param MappingDriver $mappingDriver
	 */
	public function setMappingDriver(MappingDriver $mappingDriver)
	{
		$this->mappingDriver = $mappingDriver;
	}

	protected function newClassMetadataInstance(string $className)
	{
		return $this->classMetadataClass->newInstance($className);
	}

	public function getMappingDriver()
	{
		return $this->mappingDriver;
	}

	public function setCacheRegion($region)
	{}

	/**
	 * From Doctrine ORM ClassMetadataFactory
	 *
	 * @param array $mapping
	 * @param ClassMetadata $parentClass
	 */
	private function addMappingInheritanceInformation(array &$mapping,
		ClassMetadata $parentClass): void
	{
		$isMappedSuperclass = false;
		if (!isset($mapping['inherited']) &&
			ClassMetadataAdapter::retrieveMetadataElement(
				$isMappedSuperclass, $parentClass, 'isMappedSuperclass') &&
			$isMappedSuperclass)
		{
			$mapping['inherited'] = $parentClass->name;
		}

		if (!isset($mapping['declared']))
		{
			$mapping['declared'] = $parentClass->name;
		}
	}

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
}
