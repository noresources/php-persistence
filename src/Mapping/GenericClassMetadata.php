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
use NoreSources\Persistence\Mapping\Traits\AssociationMappingClassMetadataTrait;
use NoreSources\Persistence\Mapping\Traits\FieldMappingClassMetadataTrait;
use NoreSources\Persistence\Mapping\Traits\IdGeneratorTypeClassnameTrait;
use NoreSources\Persistence\Mapping\Traits\LifecycleCallbackClassMetadataTrait;
use NoreSources\Persistence\Mapping\Traits\ReflectionServiceClassMetadataTrait;

/**
 * Minimalistice ClassMetadata implementation
 */
class GenericClassMetadata extends \ArrayObject implements
	ClassMetadata
{

	use IdGeneratorTypeClassnameTrait;
	use ReflectionServiceClassMetadataTrait;
	use FieldMappingClassMetadataTrait;
	use AssociationMappingClassMetadataTrait;
	use LifecycleCallbackClassMetadataTrait;

	/**
	 *
	 * @param string $className
	 *        	Object class name
	 */
	public function __construct($className)
	{
		$this->className = $className;
	}

	/**
	 * Store key value to the ArrayObject
	 *
	 * @param string $key
	 *        	Mapping information key
	 * @param mixed $value
	 *        	Value
	 */
	public function __set($key, $value)
	{
		$this->offsetSet($key, $value);
	}

	/**
	 *
	 * @param string $key
	 *        	Mapping information key
	 * @return string|mixed
	 */
	public function __get($key)
	{
		return $this->offsetGet($key);
	}

	/**
	 * Add several restriction on keys
	 *
	 * {@inheritdoc}
	 * @see ArrayObject::offsetSet()
	 */
	#[\ReturnTypeWillChange]
	public function offsetSet($index, $value)
	{
		if (\is_numeric($index))
			throw new \InvalidArgumentException('String key expected');
		switch ($index)
		{
			case 'name':
				throw new \RuntimeException(
					'Read-only property ' . $index);
			break;
			default:
				parent::offsetSet($index, $value);
			break;
		}
	}

	/**
	 * Adds some restrictions on keys
	 *
	 * {@inheritdoc}
	 * @see ArrayObject::offsetGet()
	 */
	#[\ReturnTypeWillChange]
	public function offsetGet($index)
	{
		if (\is_numeric($index))
			throw new \InvalidArgumentException('String key expected');
		switch ($index)
		{
			case 'name':
				return $this->className;
			break;
		}

		if (!Container::keyExists($this, $index))
			throw new \InvalidArgumentException(
				$index . ' is not a member of ' . static::class);

		return parent::offsetGet($index);
	}

	/**
	 * Silently do nothing
	 *
	 * @param string $method
	 *        	Method name
	 * @param array $arguments
	 *        	Method arguments
	 */
	public function __call($method, $arguments)
	{
		//\trigger_error($method . '() is not supported', E_USER_NOTICE);
	}

	/**
	 *
	 * {@inheritdoc}
	 * @see \Doctrine\Persistence\Mapping\ClassMetadata::getName()
	 */
	public function getName()
	{
		return $this->className;
	}

	/**
	 *
	 * {@inheritdoc}
	 * @see \Doctrine\Persistence\Mapping\ClassMetadata::getReflectionClass()
	 */
	public function getReflectionClass()
	{
		return $this->getReflectionService()->getReflectionClass(
			$this->className);
	}

	/**
	 *
	 * @var string
	 */
	private $className;
}
