<?php

/**
 * Copyright © 2023 - 2024 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package Persistence
 */
namespace NoreSources\Persistence\Sorting;

use Doctrine\Persistence\Mapping\ClassMetadata;
use NoreSources\ComparableInterface;
use NoreSources\Persistence\Mapping\Traits\ReflectionServiceReferenceTrait;
use NoreSources\Reflection\ReflectionServiceInterface;

/**
 * Default object sorter implementation
 */
class DefaultObjectSorter implements ObjectSorterInterface
{
	use ReflectionServiceReferenceTrait;

	public function getReflectionFieldFlags()
	{
		return ReflectionServiceInterface::ALLOW_READ_METHOD;
	}

	public function __construct(ClassMetadata $metadata)
	{
		$this->metadata = $metadata;
	}

	public function sortObjects(&$list, $orderBy)
	{
		usort($list,
			function ($a, $b) use ($orderBy) {
				return $this->sortFunction($a, $b, $orderBy);
			});
	}

	public function sortFunction($a, $b, $orderBy)
	{
		$className = $this->metadata->getName();
		$reflectionService = $this->getReflectionService();
		foreach ($orderBy as $name => $orientation)
		{
			$field = $reflectionService->getAccessibleProperty(
				$className, $name);
			$va = $field->getValue($a);
			$vb = $field->getValue($b);
			$c = $this->compareField($va, $vb);
			if ($c == 0)
				continue;
			if ($orientation == self::DESC)
				return -$c;
			return $c;
		}
		return 0;
	}

	public function compareField($a, $b)
	{
		if ($a instanceof ComparableInterface)
			return $a->compare($b);
		if ($a == $b)
			return 0;
		return ($a < $b) ? -1 : 1;
	}

	/**
	 *
	 * @var ClassMetadata
	 */
	private $metadata;
}
