<?php

/**
 * Copyright © 2023 - 2024 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package Persistence
 */
namespace NoreSources\Persistence\Id;

use Doctrine\Persistence\ObjectManager;

/**
 * ID generator interface
 *
 * Compatible with Doctrine ORM ID generator classes
 */
interface IdGeneratorInterface
{

	/**
	 * Generate a unique identifier for the given object.
	 *
	 * @param object $object
	 *        	Object to generate the ID for
	 * @param ObjectManager $manager
	 *        	Object manager.
	 * @return mixed Object ID(s)
	 */
	function generate(ObjectManager $manager, $object);
}