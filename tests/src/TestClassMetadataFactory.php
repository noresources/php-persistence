<?php

/**
 * Copyright © 2023 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package Persistence
 */
namespace NoreSources\Persistence\TestUtility;

use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\Persistence\Mapping\ClassMetadata;
use Doctrine\Persistence\Mapping\ReflectionService;
use Doctrine\Persistence\Mapping\RuntimeReflectionService;
use NoreSources\Persistence\Mapping\GenericClassMetadataFactory;

class TestClassMetadataFactory extends GenericClassMetadataFactory
{

	protected function wakeupReflection(ClassMetadata $class,
		ReflectionService $reflService)
	{
		if ($class instanceof ClassMetadataInfo)
		{
			if (!isset($this->reflectionService))
				$this->reflectionService = new RuntimeReflectionService();

			$class->wakeupReflection($this->reflectionService);
		}
	}

	private $reflectionService;
}
