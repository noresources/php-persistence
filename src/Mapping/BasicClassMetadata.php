<?php

/**
 * Copyright © 2023 - 2024 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package Persistence
 */
namespace NoreSources\Persistence\Mapping;

use Doctrine\Persistence\Mapping\ClassMetadata;
use NoreSources\Container\Container;
use NoreSources\Persistence\Mapping\Traits\IdGeneratorTypeClassnameTrait;
use NoreSources\Persistence\Mapping\Traits\ReflectionServiceClassMetadataIdentifierValueTrait;
use NoreSources\Reflection\ReflectionService;
use NoreSources\Reflection\ReflectionServiceInterface;

/**
 * Minimalistice ClassMetadata implementation
 */
class BasicClassMetadata implements ClassMetadata
{

	use IdGeneratorTypeClassnameTrait;
	use ReflectionServiceClassMetadataIdentifierValueTrait;

	/**
	 *
	 * @var array Array of array of method names
	 */
	public $lifecycleCallbacks = [];

	public function __construct($className)
	{
		$this->className = $className;
	}

	public function __call($method, $arguments)
	{
		//\trigger_error($method . '() is not supported', E_USER_NOTICE);
	}

	public function isIdentifier($fieldName)
	{
		$field = Container::keyValue($this->fields, $fieldName);
		if (!$field)
			throw new \InvalidArgumentException(
				$fieldName . ' is not a field of ' . $this->getName());
		$flags = self::FIELD_TYPE_ID;
		return (($field[self::KEY_FIELD_TYPE] & $flags) == $flags);
	}

	public function getName()
	{
		return $this->className;
	}

	public function getTypeOfField($fieldName)
	{
		$field = Container::keyValue($this->fields, $fieldName);
		if (!$field)
			throw new \InvalidArgumentException(
				$fieldName . ' is not a field of ' . $this->getName());
		return Container::keyValue($field, self::KEY_TYPE, null);
	}

	public function getAssociationMappedByTargetField($assocName)
	{
	/**
	 *
	 * @todo ?
	 */
	}

	public function getFieldNames()
	{
		$names = [];
		$flags = self::FIELD_TYPE_ID;

		foreach ($this->fields as $name => $field)
		{
			$type = $field[self::KEY_FIELD_TYPE];
			if ($type == 0 || $type == self::FIELD_TYPE_ID)
				$names[] = $name;
		}
		return $names;
	}

	public function getIdentifierFieldNames()
	{
		$names = [];
		$flags = self::FIELD_TYPE_ID;

		foreach ($this->fields as $name => $field)
		{
			if (($field[self::KEY_FIELD_TYPE] & $flags) == 0)
				continue;
			$names[] = $name;
		}
		return $names;
	}

	public function getAssociationNames()
	{
		$names = [];
		$flags = self::FIELD_TYPE_SINGLE_VALUE_ASSOCIATION |
			self::FIELD_TYPE_COLLECTION_ASSOCIATION;

		foreach ($this->fields as $name => $field)
		{
			if (($field[self::KEY_FIELD_TYPE] & $flags) == 0)
				continue;
			$names[] = $name;
		}
		return $names;
	}

	public function getIdentifier()
	{
		return $this->getIdentifierFieldNames();
	}

	public function getIdentifierValues($object)
	{
		$names = $this->getIdentifierFieldNames();
		$values = [];
		$flags = ReflectionServiceInterface::READABLE |
			ReflectionServiceInterface::EXPOSE_HIDDEN_PROPERTY;
		foreach ($names as $name)
		{
			$values[$name] = $this->getReflectionService()->getPropertyValue(
				$object, $name, $flags);
		}

		return $values;
	}

	public function hasAssociation($fieldName)
	{
		$field = Container::keyValue($this->fields, $fieldName);
		if (!$field)
			throw new \InvalidArgumentException(
				$fieldName . ' is not a field of ' . $this->getName());
		$flags = self::FIELD_TYPE_ASSOCIATION;
		return (($field[self::KEY_FIELD_TYPE] & $flags) != 0);
	}

	public function isCollectionValuedAssociation($fieldName)
	{
		$field = Container::keyValue($this->fields, $fieldName);
		if (!$field)
			throw new \InvalidArgumentException(
				$fieldName . ' is not a field of ' . $this->getName());
		$flags = self::FIELD_TYPE_COLLECTION_ASSOCIATION;
		return (($field[self::KEY_FIELD_TYPE] & $flags) == $flags);
	}

	public function getReflectionClass()
	{
		return $this->getReflectionService()->getReflectionClass(
			$this->className);
	}

	public function hasField($fieldName)
	{
		return \in_array($fieldName, $this->getFieldNames());
	}

	public function isSingleValuedAssociation($fieldName)
	{
		$field = Container::keyValue($this->fields, $fieldName);
		if (!$field)
			throw new \InvalidArgumentException(
				$fieldName . ' is not a field of ' . $this->getName());
		$flags = self::FIELD_TYPE_SINGLE_VALUE_ASSOCIATION;
		return (($field[self::KEY_FIELD_TYPE] & $flags) == $flags);
	}

	public function getAssociationTargetClass($assocName)
	{
		$field = Container::keyValue($this->fields, $assocName);
		if (!$field)
			throw new \InvalidArgumentException(
				$assocName . ' is not a field of ' . $this->getName());
		$flags = self::FIELD_TYPE_ASSOCIATION;
		if (($field[self::KEY_FIELD_TYPE] & $flags) == 0)
			throw new \InvalidArgumentException(
				$assocName . ' is not an association');

		return $field[self::KEY_TARGET_CLASS];
	}

	public function isAssociationInverseSide($assocName)
	{
		/**
		 *
		 * @todo ?
		 */
		return false;
	}

	public function mapField($mapping)
	{
		$fieldType = (Container::keyValue($mapping, 'id', false) ? self::FIELD_TYPE_ID : 0);
		$this->fields[$mapping['fieldName']] = [
			self::KEY_FIELD_TYPE => $fieldType,
			self::KEY_TYPE => Container::keyValue($mapping,
				self::KEY_TYPE, 'string'),
			self::KEY_DEFAULT => Container::keyValue($mapping,
				self::KEY_DEFAULT, null)
		];
	}

	public function mapAssociation($mapping)
	{
		$this->fields[$mapping['fieldName']] = [
			self::KEY_TARGET_CLASS => Container::keyValue($mapping,
				'targetEntity')
		];
	}

	public function mapManyToMany($mapping)
	{
		$this->mapAssociation($mapping);
		$this->fields[$mapping['fieldName']][self::KEY_FIELD_TYPE] = self::FIELD_TYPE_COLLECTION_ASSOCIATION;
	}

	public function mapManyToOne($mapping)
	{
		$this->mapAssociation($mapping);
		$this->fields[$mapping['fieldName']][self::KEY_FIELD_TYPE] = self::FIELD_TYPE_SINGLE_VALUE_ASSOCIATION;
	}

	public function mapOneToMany($mapping)
	{
		$this->mapAssociation($mapping);
		$this->fields[$mapping['fieldName']][self::KEY_FIELD_TYPE] = self::FIELD_TYPE_COLLECTION_ASSOCIATION;
	}

	public function mapOneToOne($mapping)
	{
		$this->mapAssociation($mapping);
		$this->fields[$mapping['fieldName']][self::KEY_FIELD_TYPE] = self::FIELD_TYPE_COLLECTION_ASSOCIATION;
	}

	public function addLifecycleCallback($event, $method)
	{
		if (!\array_key_exists($event, $this->lifecycleCallbacks))
			$this->lifecycleCallbacks[$event] = [];
		$this->lifecycleCallbacks[$event][] = $method;
	}

	public function hasLifecycleCallbacks($event)
	{
		return \array_key_exists($event, $this->lifecycleCallbacks) &&
			\count($this->lifecycleCallbacks[$event]);
	}

	public function addEntityListener($event, $className, $method)
	{
		if (!isset($this->eventListeners[$event]))
			$this->eventListeners[$event] = [];
		$this->eventListeners[$event][] = [
			$className,
			$method
		];
	}

	/**
	 *
	 * @return ReflectionServiceInterface
	 */
	public function getReflectionService()
	{
		if (!isset($this->reflectionService))
			$this->reflectionService = ReflectionService::getInstance();
		return $this->reflectionService;
	}

	/**
	 *
	 * @var string
	 */
	private $className;

	const KEY_FIELD_TYPE = 'fieldType';

	const FIELD_TYPE_ID = 0x01;

	const FIELD_TYPE_SINGLE_VALUE_ASSOCIATION = 0x02;

	const FIELD_TYPE_COLLECTION_ASSOCIATION = 0x04;

	const FIELD_TYPE_ASSOCIATION = self::FIELD_TYPE_COLLECTION_ASSOCIATION |
		self::FIELD_TYPE_SINGLE_VALUE_ASSOCIATION;

	const KEY_TYPE = 'type';

	const KEY_DEFAULT = 'default';

	const KEY_TARGET_CLASS = 'targetEntity';

	/**
	 *
	 * @var array
	 */
	private $fields = [];

	/**
	 *
	 * @var array
	 */
	private $eventListeners = [];

	/**
	 *
	 * @var ReflectionServiceInterface
	 */
	private $reflectionService;
}
