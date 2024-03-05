<?php

/**
 * Copyright © 2024 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package Persistence
 */
namespace NoreSources\Persistence\Mapping\Traits;

use Doctrine\Persistence\Mapping\ReflectionService;

trait ReflectionServiceReferenceTrait
{

	/**
	 *
	 * @return \Doctrine\Persistence\Mapping\ReflectionService
	 */
	public function getReflectionService()
	{
		if (!isset($this->reflectionService))
			$this->reflectionService = \NoreSources\Persistence\Mapping\ReflectionService::getInstance();
		return $this->reflectionService;
	}

	/**
	 *
	 * @param ReflectionService $reflectionService
	 *        	Reflection service
	 */
	public function setReflection(ReflectionService $reflectionService)
	{
		$this->reflectionService = $reflectionService;
	}

	/**
	 *
	 * @var ReflectionService
	 */
	private $reflectionService;
}
