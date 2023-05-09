<?php

/**
 * Copyright © 2023 - 2024 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package Persistence
 */
namespace NoreSources\Persistence;

/**
 * Set and get object property value
 */
interface PropertyMappingInterface
{

	/**
	 * Assign object properties from a property map.
	 *
	 * @param object $object
	 *        	Object to feed with $data
	 * @param array|object $data
	 *        	Object data or an object of the same class as $object
	 */
	public function assignObjectProperties($object, $data);

	/**
	 * Retrieve object properties to a property map.
	 *
	 * @param array $data
	 *        	Targer array
	 * @param object $object
	 *        	Input object
	 */
	public function fetchObjectProperties(&$data, $object);
}
