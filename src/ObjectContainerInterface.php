<?php

/**
 * Copyright © 2024 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package Persistence
 */
namespace NoreSources\Persistence;

interface ObjectContainerInterface
{

	/**
	 * Indicates if the interface implementation instance contains the given object
	 *
	 * @param object $object
	 *        	Object to check
	 */
	function contains(object $object);

	/**
	 * Detach all objects
	 */
	function detachAll();

	/**
	 * Make the given object contained in the interface implementation instance.
	 *
	 * @param object $object
	 *        	Object to attach to container
	 */
	function attach(object $object);

	/**
	 * Forget object
	 *
	 * @param object $object
	 *        	Object to forget
	 */
	function detach(object $object);

	/**
	 * Get the original copy of the given object
	 * as it when it was attached
	 *
	 * @param object $object
	 */
	function getObjectOriginalCopy(object $object);

	/**
	 *
	 * @param object $object
	 *        	Object to set the original state
	 * @param object $original
	 *        	Clone of $object considered as the original state.
	 */
	function setObjectOriginalCopy(object $object, object $original);
}
