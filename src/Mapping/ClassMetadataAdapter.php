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
use NoreSources\Persistence\Id\IdGeneratorInterface;

/**
 * Compatibility layer across various ClassMetadata extension implementations.
 */
class ClassMetadataAdapter
{

	/**
	 *
	 * @param string $className
	 *        	Object class name
	 * @param ClassMetadata $fromMetadata
	 * @return unknown|string Qualified object class name
	 */
	public static function getFullyQualifiedClassName($className,
		ClassMetadata $fromMetadata = null)
	{
		if (\strpos($className, '\\') !== false)
			return $className;

		if (!$fromMetadata)
			return $className;

		$p = \strrpos($fromMetadata->getName(), '\\');
		if ($p === false)
			return $className;
		return \substr($fromMetadata->getName(), 0, $p + 1) . $className;
	}

	/**
	 * Indicate if class has a composite identifier
	 *
	 * @param ClassMetadata $metadata
	 *        	Class metadata
	 * @return boolean
	 */
	public static function isIdentifierComposite(
		ClassMetadata $metadata)
	{
		return \count($metadata->getIdentifierFieldNames()) > 1;
	}

	/**
	 * Indicates if class metadata has a valid ID generator
	 *
	 * @param ClassMetadata $metadata
	 *        	Class metadata
	 */
	public static function hasIdGenerator(ClassMetadata $metadata)
	{
		$idGenerator = Container::keyValue($metadata, 'idGenerator');
		return \is_object($idGenerator) &&
			\method_exists($idGenerator, 'generate');
	}

	/**
	 *
	 * @param ClassMetadata $metadata
	 *        	Class metadata
	 * @return IdGeneratorInterface|NULL
	 */
	public static function getIdGenerator(ClassMetadata $metadata)
	{
		$idGenerator = Container::keyValue($metadata, 'idGenerator');
		if (\is_object($idGenerator) &&
			\method_exists($idGenerator, 'generate'))
			return $idGenerator;
		return null;
	}
}
