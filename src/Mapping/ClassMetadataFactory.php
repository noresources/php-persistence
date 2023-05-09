<?php

/**
 * Copyright © 2023 - 2024 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package Persistence
 */
namespace NoreSources\Persistence\Mapping;

use Doctrine\Persistence\Mapping\Driver\MappingDriver;
use NoreSources\Persistence\Mapping\Driver\MappingDriverProviderInterface;
use NoreSources\Persistence\Mapping\Traits\MappingDriverClassMetadataFactoryTrait;

/**
 * Default implementation of a metadata factory based on Mapping driver
 */
class ClassMetadataFactory implements
	\Doctrine\Persistence\Mapping\ClassMetadataFactory,
	MappingDriverProviderInterface, ClassMetadataAwareInterface
{
	use MappingDriverClassMetadataFactoryTrait;

	/**
	 *
	 * @param MappingDriver $driver
	 *        	Mapping driver to use
	 */
	public function __construct(MappingDriver $driver = null)
	{
		if ($driver)
			$this->setMappingDriver($driver);
		$this->setMetadataClass(BasicClassMetadata::class);
	}
}
