<?php

/**
 * Copyright © 2024 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package Persistence
 */
namespace NoreSources\Persistence\Mapping;

use Doctrine\Persistence\Mapping\ClassMetadataFactory;
use NoreSources\Container\Container;
use ArrayAccess;

class ClassMetadataFactoryArray implements ClassMetadataFactory,
	ArrayAccess
{

	public function offsetGet($offset): mixed
	{
		return Container::keyValue($this->metadataFactories, $offset);
	}

	public function offsetExists($offset): bool
	{
		return Container::keyExists($this->metadataFactories, $offset);
	}

	public function setMetadataFor($className, $class)
	{}

	public function offsetUnset($offset): void
	{
		unset($this->metadataFactories[$offset]);
	}

	public function hasMetadataFor($className)
	{
		foreach ($this->metadataFactories as $factory)
		{
			if ($factory->hasMetadataFor($className))
				return true;
		}
		return false;
	}

	public function getAllMetadata()
	{
		$list = [];
		foreach ($this->metadataFactories as $factory)
			$list = \array_merge($list, $factory->getAllMetadata());
		return $list;
	}

	public function isTransient($className)
	{
		foreach ($this->metadataFactories as $factory)
		{
			if ($factory->isTransient($className))
				return true;
		}
		return false;
	}

	public function getMetadataFor($className)
	{
		foreach ($this->metadataFactories as $factory)
		{
			try
			{
				$metadata = $factory->getMetadataFor($className);
				if ($metadata)
					return $metadata;
			}
			catch (\Exception $e)
			{}
		}
		return null;
	}

	public function offsetSet($offset, $factory): void
	{
		$this->metadataFactories[$offset] = $factory;
	}

	/**
	 *
	 * @var \Doctrine\Persistence\Mapping\ClassMetadataFactory[]
	 */
	private $metadataFactories;
}
