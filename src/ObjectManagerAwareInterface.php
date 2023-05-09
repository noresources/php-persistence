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
 * Represents an object that keep a reference to an ObjectManager
 */
interface ObjectManagerAwareInterface
{

	/**
	 * Set the object manager
	 *
	 * @param ObjectManager $objectManager
	 */
	function setObjectManager(ObjectManager $objectManager);
}
