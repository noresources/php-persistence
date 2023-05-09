<?php

/**
 * Copyright © 2023 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package Persistence
 */
namespace NoreSources\Persistence\TestUtility;

use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\Persistence\Mapping\ClassMetadataFactory;
use Doctrine\Persistence\Mapping\RuntimeReflectionService;
use NoreSources\Persistence\Mapping\ClassMetadataAwareInterface;
use NoreSources\Persistence\Mapping\Traits\MappingDriverClassMetadataFactoryTrait;

class TestMappingDriverClassMetadataFactory implements
	ClassMetadataFactory, ClassMetadataAwareInterface
{
	use MappingDriverClassMetadataFactoryTrait;

	public function postInitializeMetadata($metadata)
	{
		if ($metadata instanceof ClassMetadataInfo)
		{
			if (!isset($this->reflectionService))
				$this->reflectionService = new RuntimeReflectionService();

			$metadata->wakeupReflection($this->reflectionService);
		}
	}

	private $reflectionService;
}
