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
trait ReflectionServiceClassMetadataIdentifierValueTrait
{

	/**
	 *
	 * /**
	 *
	 * @param object $object
	 *        	Object
	 * @param array $generatedValues
	 *        	Identifier values
	 */
	public function setIdentifierValues($object, $generatedValues)
	{
		$names = $this->getIdentifierFieldNames();

		$class = $this->getReflectionClass();
		$flags = ReflectionServiceInterface::EXPOSE_HIDDEN_PROPERTY |
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
