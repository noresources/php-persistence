<?php

/**
 * Copyright © 2023 - 2024 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package Persistence
 */
namespace NoreSources\Persistence;

use Doctrine\Persistence\Mapping\ClassMetadata;
use NoreSources\ComparableInterface;
use NoreSources\Persistence\Traits\ReflectionMetadataFieldTrait;
use NoreSources\Reflection\ReflectionServiceInterface;

/**
 * Default object sorter implementation
 */
class DefaultObjectSorter implements ObjectSorterInterface

{
	use ReflectionMetadataFieldTrait;

	public function getReflectionFieldFlags()
	{
		return ReflectionServiceInterface::ALLOW_READ_METHOD;
	}

	public function __construct(ClassMetadata $metadata)
	{
		$this->setClassMetadata($metadata);
	}

	public function sortObjects(&$list, $orderBy)
	{
		uasort($list,
			function ($a, $b) use ($orderBy) {
				foreach ($orderBy as $name => $orientation)
				{
					$field = $this->getReflectionField($name);
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
			});
	}

	public function compareField($a, $b)
	{
		if ($a instanceof ComparableInterface)
			return $a->compare($b);
		if ($a == $b)
			return 0;
		return ($a < $b) ? -1 : 1;
	}
}
