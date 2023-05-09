<?php

/**
 * Copyright © 2023 - 2024 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package Persistence
 */
namespace NoreSources\Persistence;

/**
 * Provide object storage manipulation for object manager
 */
interface ObjectPersisterInterface
{

	/**
	 *
	 * @param object $object
	 *        	Object to persist
	 */
	function persist($object);

	/**
	 *
	 * @param object $object
	 *        	Object to remove
	 */
	function remove($object);
}
