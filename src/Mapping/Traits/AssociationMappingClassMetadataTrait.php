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

trait AssociationMappingClassMetadataTrait
{

	/**
	 * Get association names.
	 *
	 * Implements ClassMetadata interface.
	 *
	 * @return string[]
	 */
	public function getAssociationNames()
	{
		return Container::keys($this->associationMappings);
	}

	public function isSingleValuedAssociation($fieldName)
	{
		$m = Container::keyValue($this->associationMappings, $fieldName);
		if (!$m)
			return false;
		return ($m[ClassMetadataAdapter::MAPPING_TYPE] &
			ClassMetadataAdapter::MAPPING_TO_ONE) != 0;
	}

	public function isCollectionValuedAssociation($fieldName)
	{
		$m = Container::keyValue($this->associationMappings, $fieldName);
		if (!$m)
			return false;
		return ($m[ClassMetadataAdapter::MAPPING_TYPE] &
			ClassMetadataAdapter::MAPPING_TO_MANY) != 0;
	}

	/**
	 * Implements ClassMetadata interface.
	 *
	 * @param string $associationName
	 *        	Association name
	 * @return boolean
	 */
	public function isAssociationInverseSide($associationName)
	{
		/**
		 *
		 * @todo ?
		 */
		return false;
	}

	public function mapManyToMany($mapping)
	{
		$k = $mapping[ClassMetadataAdapter::MAPPING_FIELD_NAME];
		$this->defaultMapAssociation($mapping);
		$this->associationMappings[$k][ClassMetadataAdapter::MAPPING_TYPE] = ClassMetadataAdapter::MAPPING_MANY_TO_MANY;
	}

	public function mapManyToOne($mapping)
	{
		$k = $mapping[ClassMetadataAdapter::MAPPING_FIELD_NAME];
		$this->defaultMapAssociation($mapping);
		$this->associationMappings[$k][ClassMetadataAdapter::MAPPING_TYPE] = ClassMetadataAdapter::MAPPING_MANY_TO_ONE;
	}

	public function mapOneToMany($mapping)
	{
		$k = $mapping[ClassMetadataAdapter::MAPPING_FIELD_NAME];
		$this->defaultMapAssociation($mapping);
		$this->associationMappings[$k][ClassMetadataAdapter::MAPPING_TYPE] = ClassMetadataAdapter::MAPPING_ONE_TO_MANY;
	}

	public function mapOneToOne($mapping)
	{
		$k = $mapping[ClassMetadataAdapter::MAPPING_FIELD_NAME];
		$this->defaultMapAssociation($mapping);
		$this->associationMappings[$k][ClassMetadataAdapter::MAPPING_TYPE] = ClassMetadataAdapter::ONE_TO_ONE;
	}

	/**
	 *
	 * @param string $fieldName
	 *        	Association name
	 * @throws MappingException
	 */
	public function getAssociationMapping($fieldName)
	{
		if (!isset($this->associationMappings[$fieldName]))
			throw new MappingException($fieldName . ' is not mapped');
		return $this->associationMappings[$fieldName];
	}

	public function hasAssociation($fieldName)
	{
		return Container::keyExists($this->associationMappings,
			$fieldName);
	}

	public function getAssociationTargetClass($associationName)
	{
		return Container::treeValue($this->associationMappings,
			[
				$associationName,
				ClassMetadataAdapter::MAPPING_TARGET_CLASS
			]);
	}

	public function getAssociationMappings()
	{
		return $this->associationMappings;
	}

	public function getAssociationMappedByTargetField($associationName)
	{
	/**
	 *
	 * @todo ?
	 */
	}

	/**
	 * Set class field mapping
	 *
	 * @param array $mapping
	 *        	Field mapping
	 */
	public function addInheritedAssociationMapping(array $mapping)
	{
		$this->defaultAddInheritedAssociationMapping($mapping);
	}

	protected function defaultMapAssociation($mapping)
	{
		$k = $mapping[ClassMetadataAdapter::MAPPING_FIELD_NAME];
		$this->associationMappings[$k] = $mapping;
	}

	protected function defaultAddInheritedAssociationMapping(
		array $mapping)
	{
		$key = $mapping[ClassMetadataAdapter::MAPPING_FIELD_NAME];
		if (Container::keyExists($this->associationMappings, $key))
			throw MappingException::duplicateAssociationMapping(
				Container::keyValue($this, 'name', 'Object'), $key);
	}

	/**
	 * Association mappings
	 *
	 * @var array
	 */
	private $associationMappings = [];
}
