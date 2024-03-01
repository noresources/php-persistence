<?php

/**
 * Copyright © 2024 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package Persistence
 */
namespace NoreSources\Persistence;

class DefaultObjectRuntimeIdGenerator implements
	ObjectRuntimeIdGeneratorInterface
{

	public function __construct()
	{
		$this->oidFunction = '\spl_object_hash';
		if (\function_exists('\spl_object_id'))
			$this->oidFunction = '\spl_object_id';
	}

	public function __invoke($object)
	{
		return $this->getObjectRuntimeIdentifier($object);
	}

	public function getObjectRuntimeIdentifier($object)
	{
		return \call_user_func($this->oidFunction, $object);
	}

	/**
	 *
	 * @var callable
	 */
	private $oidFunction;
}
