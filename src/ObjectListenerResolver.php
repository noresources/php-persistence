<?php

/**
 * Copyright © 2023 - 2024 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package Persistence
 */
namespace NoreSources\Persistence;

/**
 * Mimics Doctrine ORM EntityListenerResolver
 */
class ObjectListenerResolver extends \ArrayObject
{

	/**
	 *
	 * @param string $class
	 *        	Class name
	 * @return object|null
	 */
	public function resolve($class)
	{
		if ($this->offsetExists($class))
			return $this->offsetGet($class);
		return null;
	}

	/**
	 *
	 * @param string $class
	 *        	Class name
	 * @param object $instance
	 *        	Class instance
	 */
	public function register($class, $instance)
	{
		$this->offsetSet($class, $instance);
	}
}
