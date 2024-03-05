<?php

/**
 * Copyright © 2024 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package Persistence
 */
namespace NoreSources\Persistence\Mapping;

use NoreSources\SingletonTrait;
use NoreSources\Reflection\ReflectionServiceInterface;
use NoreSources\Reflection\Traits\ReflectionServiceBasicNamingConventionTrait;
use NoreSources\Reflection\Traits\ReflectionServicePropertyValueTrait;
use NoreSources\Reflection\Traits\ReflectionServiceReflectionPropertyTrait;
use NoreSources\Type\TypeDescription;

class ReflectionService implements ReflectionServiceInterface,
	\Doctrine\Persistence\Mapping\ReflectionService
{

	use ReflectionServicePropertyValueTrait;
	use ReflectionServiceBasicNamingConventionTrait;
	use ReflectionServiceReflectionPropertyTrait;

	use SingletonTrait;

	public function getClass(string $class)
	{
		return $this->getReflectionClass($class);
	}

	public function getAccessibleProperty(string $class,
		string $property)
	{
		$flags = \NoreSources\Reflection\ReflectionServiceInterface::READABLE |
			\NoreSources\Reflection\ReflectionServiceInterface::EXPOSE_INHERITED_PROPERTY;
		return $this->getReflectionProperty($class, $property, $flags);
	}

	public function hasPublicMethod(string $class, string $method)
	{
		$reflectionClass = $this->getReflectionClass($class);
		if (!$reflectionClass->hasMethod($method))
			return false;
		$method = $reflectionClass->getMethod($method);
		return $method->isPublic();
	}

	public function getParentClasses(string $class)
	{
		$list = [];
		/**
		 *
		 * @var \ReflectionClass $reflectionClass
		 */
		$reflectionClass = $this->getClass($class);
		$parent = $reflectionClass->getParentClass();
		if ($parent)
		{
			$list = $this->getParentClasses($parent->getName());
			$list[$parent->getName()] = $parent->getName();
		}
		return $list;
	}

	public function getClassNamespace(string $class)
	{
		/**
		 *
		 * @var \ReflectionClass $reflectionClass
		 */
		$reflectionClass = $this->getClass($class);
		return $reflectionClass->getNamespaceName();
	}

	public function getClassShortName(string $class)
	{
		return TypeDescription::getLocalName($class, true);
	}

	public function getReflectionClass($classNameOrObject)
	{
		if (\is_object($classNameOrObject))
			$classNameOrObject = \get_class($classNameOrObject);
		return new \ReflectionClass($classNameOrObject);
	}
}