<?php

/**
 * Copyright © 2023 - 2024 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package Persistence
 */
namespace NoreSources\Persistence\Mapping;

/**
 * For classes that may be configured with a user-defined ClassMetadata class
 */
interface ClassMetadataAwareInterface
{

	/**
	 *
	 * @param string $class
	 *        	Concrete ClassMetadata class
	 */
	function setMetadataClass($class);
}
