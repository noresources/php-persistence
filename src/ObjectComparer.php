<?php

/**
 * Copyright © 2024 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package Persistence
 */
namespace NoreSources\Persistence;

use Doctrine\Persistence\Mapping\ClassMetadata;
use Doctrine\Persistence\Mapping\ReflectionService;

class ObjectComparer
{

	/**
	 *
	 * @param ClassMetadata $metadata
	 * @param ReflectionService $reflectionService
	 * @param object $original
	 * @param object $actual
	 * @return array
	 */
	public static function computeChangeSet(ClassMetadata $metadata,
		ReflectionService $reflectionService, $original, $actual)
	{
		$changeSet = [];
		$fieldNames = $metadata->getFieldNames();
		foreach ($fieldNames as $name)
		{
			if ($metadata->isIdentifier($name))
				continue;
			$property = $reflectionService->getAccessibleProperty(
				$metadata->getName(), $name);
			$o = $property->getValue($original);
			$a = $property->getValue($actual);
			if ($o == $a)
				continue;
			$changeSet[$name] = [
				$o,
				$a
			];
		}

		return $changeSet;
	}
}
