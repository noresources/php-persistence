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
 * Represents an object that provides a reference to an ObjectManager
 */
interface ObjectManagerProviderInterface
{

	/**
	 * Get object manager
	 *
	 * @return ObjectManager
	 */
	function getObjectManager();
}
