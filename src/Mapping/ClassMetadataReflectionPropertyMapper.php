<?php

/**
 * Copyright © 2023 - 2024 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package Persistence
 */
namespace NoreSources\Persistence\Mapping;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Instantiator\Instantiator;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Persistence\Mapping\ClassMetadata;
use NoreSources\DateTime;
use NoreSources\Container\Container;
use NoreSources\Persistence\ObjectManagerAwareInterface;
use NoreSources\Persistence\ObjectManagerProviderInterface;
use NoreSources\Persistence\Mapping\Traits\ReflectionServiceReferenceTrait;
use NoreSources\Persistence\Traits\ObjectManagerReferenceTrait;
use NoreSources\Type\TypeConversion;

/**
 * Implements PropertyMappingInterface using Reflection
 */
class ClassMetadataReflectionPropertyMapper implements
	PropertyMappingInterface, ObjectManagerAwareInterface,
	ObjectManagerProviderInterface
{
	use ReflectionServiceReferenceTrait;
	use ObjectManagerReferenceTrait;

	/**
	 *
	 * @return \Doctrine\Persistence\Mapping\ClassMetadata
	 */
	public function getClassMetadata()
	{
		return $this->metadata;
	}

	/**
	 *
	 * @return \Doctrine\Instantiator\Instantiator
	 */
	public function getInstantiator()
	{
		if (!isset($this->instanciator))
			$this->instanciator = new Instantiator();
		return $this->instanciator;
	}

	public function __construct(ClassMetadata $metadata)
	{
		$this->metadata = $metadata;
	}

	public function assignObjectProperties($object, $data)
	{
		$fieldNames = $this->metadata->getFieldNames();
		$associations = $this->metadata->getAssociationNames();

		$propertyNames = \array_merge($fieldNames, $associations);

		if (\is_object($data))
		{
			if (!\is_a($data, $this->metadata->getName()))
			{
				foreach ($propertyNames as $name)
				{
					$field = $this->getReflectionService()->getAccessibleProperty(
						$this->metadata->getName(), $name);
					$field->setValue($object, $field->getValue($data));
				}
				return;
			}

			$validData = [];
			foreach ($propertyNames as $name)
			{
				$field = $this->getReflectionService()->getAccessibleProperty(
					$this->metadata->getName(), $name);
				$validData[$name] = $field->getValue($data);
			}
			$data = $validData;
		}

		foreach ($data as $name => $value)
		{
			if (\in_array($name, $fieldNames))
				$this->assignObjectProperty($object, $name, $value);
			elseif (\in_array($name, $associations))
				$this->assignObjectAssociationProperty($object, $name,
					$value);
		}
	}

	public function fetchObjectProperties(&$data, $object)
	{
		$ids = $this->metadata->getIdentifierValues($object);
		$fieldNames = $this->metadata->getFieldNames();

		foreach ($fieldNames as $name)
			$data[$name] = $this->fetchObjectProperty($object, $name);

		$associationNames = $this->metadata->getAssociationNames();
		foreach ($associationNames as $name)
			$data[$name] = $this->fetchObjectAssociationProperty(
				$object, $name);

		foreach ($ids as $key => $value)
			$data[$key] = $value;
	}

	protected function assignObjectProperty($object, $name, $value)
	{
		$field = $this->getReflectionService()->getAccessibleProperty(
			$this->metadata->getName(), $name);
		$fieldTypename = $this->metadata->getTypeOfField($name);

		if (!\is_string($fieldTypename))
		{
			$field->setValue($object, $value);
			return;
		}

		/**
		 *
		 * @todo A cleaner solution
		 */

		$isClass = \class_exists($fieldTypename);

		if ((\strpos(\mb_strtolower($fieldTypename), 'datetime') === 0) ||
			($isClass &&
			\is_a($fieldTypename, \DateTimeInterface::class, true)))
		{
			if (\is_array($value))
				$value = DateTime::createFromArray($value);
			elseif (\is_string($value))
				$value = new \DateTime($value);
		}
		elseif ($isClass)
			$value = $this->unserializeEmbeddedObject($value, $name,
				$fieldTypename);

		$field->setValue($object, $value);
	}

	protected function unserializeEmbeddedObject($value, $fieldName,
		$expectedClassName)
	{
		if ($value === null)
		{
			$metadata = $this->getClassMetadata();
			$mapping = null;
			if (ClassMetadataAdapter::invokeMetadataMethod($mapping,
				$metadata, 'getFieldMapping', $fieldName) &&
				Container::keyValue($mapping, 'nullable', false))
			{
				return null;
			}

			return $this->getInstantiator()->instantiate(
				$expectedClassName);
		}

		if (\is_object($value) && \is_a($value, $expectedClassName))
			return $value;

		try
		{
			$u = @\unserialize($value);
			if (\is_a($u, $expectedClassName))
				return $u;
		}
		catch (\Exception $e)
		{}

		return TypeConversion::to($expectedClassName, $value,
			[
				TypeConversion::OPTION_FLAGS => (TypeConversion::OPTION_FLAG_OBJECT_CONSTRUCTOR |
				TypeConversion::OPTION_FLAG_OBJECT_FACTORY)
			]);
	}

	protected function assignObjectAssociationPropertyAsIs($object,
		$name, $value)
	{
		$field = $this->getReflectionService()->getAccessibleProperty(
			$this->metadata->getName(), $name);
		if ($this->metadata->isSingleValuedAssociation($name))
		{
			$field->setValue($object, $value);
			return;
		}
		$collection = new ArrayCollection();
		foreach ($value as $offset => $v)
			$collection->offsetSet($offset, $v);
		$field->setValue($object, $collection);
	}

	protected function assignObjectAssociationProperty($object, $name,
		$value)
	{
		$field = $this->getReflectionService()->getAccessibleProperty(
			$this->metadata->getName(), $name);
		$associationClassName = $this->metadata->getAssociationTargetClass(
			$name);
		$manager = $this->getObjectManager();
		if (!($associationClassName && $manager))
			return $this->assignObjectAssociationPropertyAsIs($object,
				$name, $value);

		$associationClassName = ClassMetadataAdapter::getFullyQualifiedClassName(
			$associationClassName, $this->metadata);

		if ($this->metadata->isSingleValuedAssociation($name))
		{
			if (!(\is_object($value) && \is_a($value, $associationCl)))
			{
				$value = $this->findObject($manager,
					$manager->getClassMetadata($associationClassName),
					$value);
			}

			$field->setValue($object, $value);
			return;
		}

		$collection = new ArrayCollection();
		$associationMetadata = $manager->getClassMetadata(
			$associationClassName);
		foreach ($value as $offset => $v)
		{
			if (!(\is_object($v) && \is_a($v, $associationClassName)))
				$v = $this->findObject($manager, $associationMetadata,
					$v);
			$collection->offsetSet($offset, $v);
		}
		$field->setValue($object, $collection);
	}

	protected function fetchObjectProperty($object, $fieldName)
	{
		$field = $this->getReflectionService()->getAccessibleProperty(
			$this->metadata->getName(), $fieldName);
		$metadata = $this->metadata;
		$type = $metadata->getTypeOfField($fieldName);
		$value = $field->getValue($object);

		if ($value instanceof \DateTimeInterface)
			$value = $this->serializeObjectDataTimeProperty($value,
				$fieldName);
		elseif (\is_string($type) && \class_exists($type))
			$value = $this->serializeObjectEmbeddedObjectProperty(
				$value, $fieldName, $type);
		return $value;
	}

	protected function serializeObjectDataTimeProperty(
		\DateTimeInterface $value, $fieldName)
	{
		return $value->format(\DateTIme::ISO8601);
	}

	protected function serializeObjectEmbeddedObjectProperty($value,
		$fieldName, $type)
	{
		if (\is_object($value) && \is_a($value, $type))
			return \serialize($value);
		return $value;
	}

	protected function fetchObjectAssociationProperty($object,
		$fieldName)
	{
		return $this->fetchObjectProperty($object, $fieldName);
	}

	protected function findObject(ObjectManager $objectManager,
		ClassMetadata $metadata, $data)
	{
		try
		{
			$id = $data;
			return $objectManager->find($metadata->getName(), $id);
		}
		catch (\Exception $e)
		{
			throw $e;
			return $data;
		}
	}

	/**
	 *
	 * @var Instantiator
	 */
	private $instanciator;

	/**
	 *
	 * @var ClassMetadata
	 */
	private $metadata;
}
