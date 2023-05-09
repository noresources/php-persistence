<?php

/**
 * Copyright © 2023 - 2024 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package Persistence
 */
namespace NoreSources\Persistence;

/**
 * Represents on class that can expose a PropertyMapper
 */
interface PropertyMappingProviderInterface
{

	/**
	 *
	 * @return PropertyMappingInterface
	 */
	function getPropertyMapper();
}
