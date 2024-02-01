<?php

/**
 * Copyright © 2024 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package Persistence
 */
namespace NoreSources\Persistence\Mapping\Traits;

use NoreSources\Container\Container;
use NoreSources\Reflection\ReflectionServiceInterface;

/**
 * Use ReflectionService to set identifier values in a ClassMetadata
 */
trait ReflectionServiceIdentifierValueClassMetadataTrait
{

	/**
	 *
	 * @param object $object
	 *        	Object
	 * @return mixed[] Values of ID fields
	 */
	public function getIdentifierValues($object)
	{
		$names = $this->getIdentifierFieldNames();
		$values = [];
		$flags = ReflectionServiceInterface::READABLE |
			ReflectionServiceInterface::EXPOSE_HIDDEN_PROPERTY |
			ReflectionServiceInterface::EXPOSE_INHERITED_PROPERTY;
		foreach ($names as $name)
		{
			$values[$name] = $this->getReflectionService()->getPropertyValue(
				$object, $name, $flags);
		}

		return $values;
	}

	/**
	 *
	 * @param object $object
	 *        	Object
	 * @param mixed[] $generatedValues
	 *        	ID field values
	 */
	public function setIdentifierValues($object, $generatedValues)
	{
		$names = $this->getIdentifierFieldNames();

		$class = $this->getReflectionClass();
		$flags = ReflectionServiceInterface::EXPOSE_HIDDEN_PROPERTY |
			ReflectionServiceInterface::EXPOSE_INHERITED_PROPERTY |
			ReflectionServiceInterface::WRITABLE;

		foreach ($names as $name)
		{
			$property = $this->getReflectionService()->getReflectionProperty(
				$class, $name, $flags);
			if (Container::isArray($generatedValues))
				$property->setValue($object,
					Container::keyValue($generatedValues, $name));
			else
				$property->setValue($object, $generatedValues);
		}
	}
}
