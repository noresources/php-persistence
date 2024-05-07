<?php

/**
 * Copyright © 2024 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package Persistence
 */
namespace NoreSources\Persistence;

interface ObjectFieldIndexPersisterInterface
{

	/**
	 *
	 * @param string $fieldName
	 *        	Object field name
	 * @param Index $data
	 *        	Index data for the field
	 */
	function persistFieldIndex($fieldName, Index $data);
}
