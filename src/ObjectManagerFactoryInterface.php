<?php

/**
 * Copyright © 2024 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package Persistence
 */
namespace NoreSources\Persistence;

use Doctrine\Persistence\ObjectManager;

/**
 * Creates an ObjectManager from a configuration description
 */
interface ObjectManagerFactoryInterface
{

	/**
	 *
	 * @param array $parameters
	 *        	Object manager configuration
	 * @return ObjectManager
	 */
	function createObjectManager($parameters);
}
