<?php

/**
 * Copyright © 2024 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package Persistence
 */
namespace NoreSources\Persistence;

interface ObjectRuntimeIdGeneratorInterface
{

	/**
	 * Return an ID for the given object that will be unique during the execution of the program.
	 *
	 * @param object $object
	 *        	Input object
	 * @return mixed Input object runtime ID
	 */
	function getObjectRuntimeIdentifier($object);
}
