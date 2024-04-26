<?php

/**
 * Copyright © 2024 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package Persistence
 */
namespace NoreSources\Persistence\TestUtility;

use Doctrine\DBAL\Types\DateTimeTzType;

trait TestDBALTypeExtensionTrait
{

	function setupDBALExtensions()
	{
		if (!\array_key_exists(\DateTimeInterface::class,
			\Doctrine\DBAL\Types\Type::getTypesMap()))
			\Doctrine\DBAL\Types\Type::addType(
				\DateTimeInterface::class, DateTimeTzType::class);
	}
}
