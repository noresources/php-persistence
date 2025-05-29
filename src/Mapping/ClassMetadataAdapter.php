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

	const MAPPING_ID = 'id';

	const MAPPING_FIELD_NAME = 'fieldName';

	/**
	 * Field or association type
	 *
	 * @var string
	 */
	const MAPPING_TYPE = 'type';

	const MAPPING_ONE_TO_ONE = 0x01;

	const MAPPING_MANY_TO_ONE = 0x02;

	const MAPPING_ONE_TO_MANY = 0x04;

	const MAPPING_MANY_TO_MANY = 0x08;

	const MAPPING_TO_ONE = self::MAPPING_ONE_TO_ONE |
		self::MAPPING_MANY_TO_ONE;

	const MAPPING_TO_MANY = self::MAPPING_ONE_TO_MANY |
		self::MAPPING_MANY_TO_MANY;

	const MAPPING_TARGET_CLASS = 'targetEntity';

	/**
	 *
	 * @param string $className
	 *        	Object class name
	 * @param ClassMetadata $fromMetadata
	 * @return string Qualified object class name
	 */
	public static function getFullyQualifiedClassName($className,
		?ClassMetadata $fromMetadata = null)
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

	/**
	 * Get ClassMetadata element or return default value
	 *
	 * @param ClassMetadata $metadata
	 * @param string $name
	 *        	Element name
	 * @param mixed $dflt
	 *        	Default value
	 * @return mixed
	 */
	public static function getMetadataElement(ClassMetadata $metadata,
		$name, $dflt = null)
	{
		$value = $dflt;
		$arguments = [];
		if (self::retrieveMetadataElement($value, $metadata, $name,
			...$arguments))
			return $value;
		return $dflt;
	}

	public static function retrieveMetadataProperty(&$value,
		ClassMetadata $metadata, $name)
	{
		try
		{
			$value = Container::keyValue($metadata, $name, $value);
			return true;
		}
		catch (\Exception $e)
		{}
		return false;
	}

	/**
	 * Attempt to read ClassMetadata element by invoking a getter method or by getting a class
	 * property
	 *
	 * @param mixed $value
	 *        	Output value
	 * @param ClassMetadata $metadata
	 * @param string $name
	 *        	Method, property or array offset to get
	 * @param mixed[] ...$arguments
	 *        	Method argument(s)
	 * @return boolean TRUE if value wasobtained
	 */
	public static function retrieveMetadataElement(&$value,
		ClassMetadata $metadata, $name, ...$arguments)
	{
		if (self::invokeMetadataMethod($value, $metadata, $name,
			...$arguments))
			return true;

		if (Container::count($arguments) != 0)
			return false;

		return self::retrieveMetadataProperty($value, $metadata, $name);
	}

	public static function invokeMetadataMethod(&$returned,
		ClassMetadata $metadata, $name, ...$arguments)
	{
		if (!\method_exists($metadata, $name))
			return false;
		$returned = \call_user_func_array([
			$metadata,
			$name
		], $arguments);
		return true;
	}

	/**
	 * Attempt to set metadata element by invoking a method or by setting a property
	 *
	 * @param ClassMetadata $metadata
	 * @param string $name
	 *        	Metadata method, property or array offset
	 * @param mixed[] ...$arguments
	 *        	Method argument(s)
	 * @return boolean TRUE if a method was successfully called or a property was set
	 */
	public static function assignMetadataElement(
		ClassMetadata $metadata, $name, ...$arguments)
	{
		$returned = null;
		if (self::invokeMetadataMethod($returned, $metadata, $name,
			...$arguments))
			return true;

		if (Container::count($arguments) != 1)
			return false;

		try
		{
			Container::setValue($metadata, $name,
				Container::firstValue($arguments));
			return true;
		}
		catch (\Exception $e)
		{}
		return false;
	}
}
