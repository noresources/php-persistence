<?php

/**
 * Copyright © 2023 - 2024 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package Persistence
 */
namespace NoreSources\Persistence;

use NoreSources\Type\TypeDescription;

/**
 * Exception raised by object manager when an object is not managed by this manager
 */
class NotManagedException extends \Exception
{

	/**
	 *
	 * @param object|string $classOrObject
	 *        	Object or class name that is not managed
	 * @param object $manager
	 *        	Object manager
	 */
	public function __construct($classOrObject, $manager = null)
	{
		if (!\is_string($classOrObject))
			$classOrObject = TypeDescription::getName($classOrObject);
		parent::__construct(
			$classOrObject . ' is not managed' .
			($manager ? (' by ' . TypeDescription::getName($manager)) : ''));
	}
}
