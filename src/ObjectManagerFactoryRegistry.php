<?php

/**
 * Copyright © 2024 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package Persistence
 */
namespace NoreSources\Persistence;

use NoreSources\Container\KeyNotFoundException;
use Psr\Container\ContainerInterface;

/**
 * Manage a set of ObjectManagerFactoryInterface
 */
class ObjectManagerFactoryRegistry implements ContainerInterface
{

	/**
	 *
	 * {@inheritdoc}
	 * @see \Psr\Container\ContainerInterface::has()
	 */
	public function has($id)
	{
		return isset($this->factories[$id]);
	}

	/**
	 *
	 * {@inheritdoc}
	 * @see \Psr\Container\ContainerInterface::get()
	 */
	public function get($id)
	{
		if (!isset($this->factories[$id]))
			throw new KeyNotFoundException($id);
		return $this->factories[$id];
	}

	/**
	 * Register an object manager factory
	 *
	 * @param mixed $id
	 *        	Factory ID
	 * @param ObjectManagerFactoryInterface $factory
	 *        	Object manager factory
	 */
	public function registerFactory($id,
		ObjectManagerFactoryInterface $factory)
	{
		$this->factories[$id] = $factory;
	}

	/**
	 *
	 * @param mixed $id
	 *        	Object manager factory ID
	 * @param array $parameters
	 *        	Object manager parameters
	 * @return \Doctrine\Persistence\ObjectManager
	 */
	public function createObjectManager($id, $parameters)
	{
		$factory = $this->get($id);
		return $factory->createObjectManager($parameters);
	}

	/**
	 *
	 * @var ObjectManagerFactoryInterface[]
	 */
	private $factories = [];
}
