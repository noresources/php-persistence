<?php

/**
 * Copyright © 2024 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package Persistence
 */
namespace NoreSources\Persistence\Mapping\Traits;

use Doctrine\Persistence\Mapping\MappingException;
use NoreSources\Container\Container;
use NoreSources\Persistence\Mapping\ClassMetadataAdapter;

/**
 * Field and mapping
 */
trait FieldMappingClassMetadataTrait
{

	/**
	 * Get all field names.
	 *
	 * Implements ClassMetadata interface.
	 *
	 * @return string[]
	 */
	public function getFieldNames()
	{
		return Container::keys($this->fieldMappings);
	}

	/**
	 * Indicates if a field is part of the class.
	 *
	 * Implements ClassMetadata interface.
	 *
	 * @param string $fieldName
	 *        	Field name
	 * @return boolean
	 */
	public function hasField($fieldName)
	{
		return Container::keyExists($this->fieldMappings, $fieldName);
	}

	/**
	 * Get identifier fields.
	 *
	 * Implements interface of ClassMetadata
	 *
	 * @return array|mixed[]
	 */
	public function getIdentifierFieldNames()
	{
		return Container::keys(
			Container::filterValues($this->fieldMappings,
				function ($mapping) {
					return Container::keyValue($mapping,
						ClassMetadataAdapter::MAPPING_ID, false);
				}));
	}

	/**
	 * Get identifier field names.
	 *
	 * Implements ClassMetadata interface.
	 *
	 * @return array|mixed[]
	 */
	public function getIdentifier()
	{
		return $this->getIdentifierFieldNames();
	}

	/**
	 * Indicates if the given field is an identifier field.
	 *
	 * Implements ClassMetadata interface.
	 *
	 * @param string $fieldName
	 *        	Field name
	 */
	public function isIdentifier($fieldName)
	{
		return Container::keyValue($this->fieldMappings, $fieldName,
			false) ? true : fase;
	}

	/**
	 * Get the given field type name
	 *
	 * Implements ClassMetadata interface.
	 *
	 * @param string $fieldName
	 *        	Field name
	 * @return string Type name
	 */
	public function getTypeOfField($fieldName)
	{
		return Container::treeValue($this->fieldMappings,
			[
				$fieldName,
				ClassMetadataAdapter::MAPPING_TYPE
			], null);
	}

	/**
	 * Implements ClassMetadata interface.
	 *
	 * @param array $mapping
	 *        	Field mapping data
	 */
	public function mapField($mapping)
	{
		$this->defaultMapField($mapping);
	}

	/**
	 *
	 * @param array $mapping
	 */
	public function addInheritedFieldMapping(array $mapping)
	{
		$this->defaultAddInheritedFieldMapping($mapping);
	}

	/**
	 * Get raw field mapping
	 *
	 * @param string $fieldName
	 *        	Field name
	 * @throws MappingException
	 * @return array Field or association mapping
	 *
	 */
	public function getFieldMapping($fieldName)
	{
		if (!isset($this->fieldMappings[$fieldName]))
			throw new MappingException($fieldName . ' is not mapped');
		return $this->fieldMappings[$fieldName];
	}

	/**
	 *
	 * @return array Field mappings
	 */
	public function getFieldMappings()
	{
		return $this->fieldMappings;
	}

	/**
	 * Base implementation of a mapField function
	 *
	 * @param array $mapping
	 *        	Field mapping
	 */
	protected function defaultMapField($mapping)
	{
		$k = $mapping[ClassMetadataAdapter::MAPPING_FIELD_NAME];
		$this->fieldMappings[$k] = $mapping;
	}

	protected function defaultAddInheritedFieldMapping(array $mapping)
	{
		$key = $mapping[ClassMetadataAdapter::MAPPING_FIELD_NAME];
		if (Container::keyExists($this->fieldMappings, $key))
			throw MappingException::duplicateAssociationMapping(
				Container::keyValue($this, 'name', 'Object'), $key);
		$this->mapField($mapping);
	}

	/**
	 * Field mappings
	 *
	 * @var array
	 */
	private $fieldMappings = [];
}
