<?php

/**
 * Copyright © 2024 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package Persistence
 */
namespace NoreSources\Persistence;

interface ObjectFieldIndexRepositoryInterface
{

	/**
	 *
	 * @param string $fieldName
	 *        	Object field name
	 * @return Index Index for the given field
	 */
	function getFieldIndex($fieldName);

	/**
	 *
	 * @return array List of object field that are indexed
	 */
	function getIndexedFieldNames();

	/**
	 * Rebuild all field indexes from persisted objects
	 */
	function refreshFieldIndexes();
}
