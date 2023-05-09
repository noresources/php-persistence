<?php

/**
 * Copyright © 2023 - 2024 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package Persistence
 */
namespace NoreSources\Persistence\Mapping\Driver;

use Doctrine\Persistence\Mapping\Driver\MappingDriver;

/**
 * Interface for classes that can provide a reference to a MappingDriver
 */
interface MappingDriverProviderInterface
{

	/**
	 *
	 * @return MappingDriver
	 */
	function getMappingDriver();
}
