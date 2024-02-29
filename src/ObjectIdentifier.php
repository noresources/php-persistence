<?php

/**
 * Copyright © 2024 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package Persistence
 */
namespace NoreSources\Persistence;

use Doctrine\Persistence\Mapping\ClassMetadata;
use NoreSources\Container\Container;
use NoreSources\Type\TypeDescription;

/**
 * Object identifier utility
 */
class ObjectIdentifier
{

	/**
	 * Indicates if two object identity are equal.
	 *
	 * @param array $a
	 *        	First identifier
	 * @param array $b
	 *        	Second identifier
	 * @return boolean
	 */
	public static function equals($a, $b)
	{
		$ca = \count($a);
		$cb = \count($b);
		if ($ca != $cb)
			return false;
		foreach ($a as $k => $va)
		{
			if (!\array_key_exists($k, $b))
				return false;
			$vb = $b[$k];
			if ($va != $vb)
				return false;
		}

		return true;
	}

	/**
	 * Remove all keys of $id that are not in the list of identifier field names
	 *
	 * @param array $id
	 *        	Object identifier
	 * @param ClassMetadata $metadata
	 *        	Object class metadata
	 * @param boolean $trimNull
	 *        	Remove NULL fields
	 * @return array Trimmed identifier
	 */
	public static function trim($id, ClassMetadata $metadata,
		$trimNull = false)
	{
		$names = $metadata->getIdentifierFieldNames();
		$trimmed = [];
		foreach ($id as $k => $v)
		{
			if (!\in_array($k, $names))
				continue;
			if ($trimNull && $v === null)
				continue;
			$trimmed[$k] = $v;
		}
		return $trimmed;
	}

	public static function normalize($id, ClassMetadata $metadata)
	{
		if (\is_object($id) && \is_a($id, $metadata->getName()))
			return $metadata->getIdentifierValues($id);

		$names = $metadata->getIdentifierFieldNames();
		if (\count($names) == 0)
			throw new \InvalidArgumentException(
				$metadata->getName() . ' does not have identifier fields');
		if (!\is_array($id))
		{
			if (\count($names) > 1 && !Container::isTraversable($id))
				throw new \RuntimeException(
					'Cannot normalize composite identifier from ' .
					TypeDescription::getName($id));
			$key = Container::firstValue($names);
			return [
				$key => $id
			];
		}

		$normalized = [];

		foreach ($names as $name)
			$normalized[$name] = null;

		foreach ($id as $k => $v)
			if (\in_array($k, $names))
				$normalized[$k] = $v;

		return $normalized;
	}
}
