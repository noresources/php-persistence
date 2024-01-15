<?php

/**
 * Copyright © 2024 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package Persistence
 */
namespace NoreSources\Persistence\Mapping\Traits;

use Doctrine\Persistence\Mapping\MappingException;

trait FieldMappingTrait
{

	/**
	 * Set class field mapping
	 *
	 * @param array $mapping
	 */
	public function mapField($mapping)
	{
		$this->defaultMapField($mapping);
	}

	/**
	 * Base implementation of a mapField function
	 *
	 * @param array $mapping
	 *        	Field mapping
	 */
	public function defaultMapField($mapping)
	{
		$this->fieldMappings[$mapping['fieldName']] = $mapping;
	}

	/**
	 * Get raw field mapping
	 *
	 * @param string $fieldName
	 *        	Field name
	 * @throws MappingException
	 * @return array
	 */
	public function getFieldMapping($fieldName)
	{
		if (!isset($this->fieldMappings[$fieldName]))
			throw new MappingException($fieldName . ' is not mapped');
		return $this->fieldMappings[$fieldName];
	}

	private $fieldMappings = [];
}
