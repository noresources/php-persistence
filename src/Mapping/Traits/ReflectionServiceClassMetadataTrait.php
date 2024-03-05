<?php

/**
 * Copyright © 2024 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package Persistence
 */
namespace NoreSources\Persistence\Mapping\Traits;

use Doctrine\Persistence\Mapping\ReflectionService;
use NoreSources\Container\Container;

/**
 * Use ReflectionService to set identifier values in a ClassMetadata
 */
trait ReflectionServiceClassMetadataTrait
{
	use ReflectionServiceReferenceTrait;

	public function initializeReflection(
		ReflectionService $reflectionService)
	{
		$this->setReflection($reflectionService);
	}

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

		$reflectionService = $this->getReflectionService();
		foreach ($names as $name)
		{
			$property = $reflectionService->getAccessibleProperty(
				$this->getName(), $name);
			$values[$name] = $property->getValue($object);
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

		$c = \count($names);
		$reflectionService = $this->getReflectionService();
		for ($i = 0; $i < $c; $i++)
		{
			$name = $names[$i];
			$value = null;
			if (Container::isArray($generatedValues))
			{
				$value = Container::keyValue($generatedValues, $name,
					Container::keyValue($generatedValues, $i,
						Container::firstValue($generatedValues)));
			}
			else
				$value = $generatedValues;

			$property = $reflectionService->getAccessibleProperty(
				$this->getName(), $name);

			$property->setValue($object, $value);
		}
	}
}
