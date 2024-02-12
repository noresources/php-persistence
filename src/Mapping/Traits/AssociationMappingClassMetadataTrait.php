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

	/**
	 * Indicates if assiciation targets a single value.
	 *
	 * Implements ClassMetadata interface.
	 *
	 * @param string $fieldName
	 *        	Association name
	 * @return boolean
	 */
	public function isSingleValuedAssociation($fieldName)
	{
		$m = Container::keyValue($this->associationMappings, $fieldName);
		if (!$m)
			return false;
		return ($m[ClassMetadataAdapter::MAPPING_TYPE] &
			ClassMetadataAdapter::MAPPING_TO_ONE) != 0;
	}

	/**
	 * Indicates is association targets multiple values.
	 *
	 * Implements ClassMetadata interface.
	 *
	 * @param string $fieldName
	 *        	Association name
	 * @return boolean
	 */
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
	 * "inspired" by Doctrin ORM implementation.
	 *
	 * @param string $associationName
	 *        	Association name
	 * @return boolean
	 */
	public function isAssociationInverseSide($associationName)
	{
		return !Container::treeValue($this->associationMappings,
			[
				$associationName,
				'isOwningSide'
			], true);
	}

	/**
	 * MDoctrine ORM compatibility.
	 *
	 * @param array $mapping
	 *        	Association mapping
	 */
	public function mapManyToMany($mapping)
	{
		$this->defaultMapAssociation($mapping);
		$k = $mapping[ClassMetadataAdapter::MAPPING_FIELD_NAME];
		$this->associationMappings[$k][ClassMetadataAdapter::MAPPING_TYPE] = ClassMetadataAdapter::MAPPING_MANY_TO_MANY;
	}

	/**
	 * Doctrine ORM compatibility.
	 *
	 * @param array $mapping
	 *        	Association mapping
	 */
	public function mapManyToOne($mapping)
	{
		$this->defaultMapAssociation($mapping);
		$k = $mapping[ClassMetadataAdapter::MAPPING_FIELD_NAME];
		$this->associationMappings[$k][ClassMetadataAdapter::MAPPING_TYPE] = ClassMetadataAdapter::MAPPING_MANY_TO_ONE;
	}

	/**
	 * Doctrine ORM compatibility.
	 *
	 * @param array $mapping
	 *        	Association mapping
	 */
	public function mapOneToMany($mapping)
	{
		$this->defaultMapAssociation($mapping);
		$k = $mapping[ClassMetadataAdapter::MAPPING_FIELD_NAME];
		$this->associationMappings[$k][ClassMetadataAdapter::MAPPING_TYPE] = ClassMetadataAdapter::MAPPING_ONE_TO_MANY;
	}

	/**
	 *
	 * Doctrine ORM compatibility.
	 *
	 * @param array $mapping
	 *        	Association mapping
	 */
	public function mapOneToOne($mapping)
	{
		$this->defaultMapAssociation($mapping);
		$k = $mapping[ClassMetadataAdapter::MAPPING_FIELD_NAME];
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

	/**
	 * Indicates if the given field has an association with another entity field.
	 *
	 * Implements ClassMetadata interface.
	 *
	 * @param string $fieldName
	 *        	Field name
	 * @return boolean
	 */
	public function hasAssociation($fieldName)
	{
		return Container::keyExists($this->associationMappings,
			$fieldName);
	}

	/**
	 * Get associated field class.
	 *
	 * Implements ClassMetadata interface.
	 *
	 * @param string $associationName
	 *        	Association name
	 * @return mixed|string|array|\Traversable
	 */
	public function getAssociationTargetClass($associationName)
	{
		return Container::treeValue($this->associationMappings,
			[
				$associationName,
				ClassMetadataAdapter::MAPPING_TARGET_CLASS
			]);
	}

	/**
	 *
	 * @return array
	 */
	public function getAssociationMappings()
	{
		return $this->associationMappings;
	}

	/**
	 * Get association mapped target.
	 *
	 * Implements ClassMetadata interface.
	 *
	 * @param string $associationName
	 *        	Association name
	 * @return string
	 */
	public function getAssociationMappedByTargetField($associationName)
	{
		return Container::treeValue($this->associationMappings,
			[
				$associationName,
				'mappedBy'
			]);
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

		$isOwningSide = true;
		if (Container::keyValue($mapping, 'mappedBy'))
			$isOwningSide = false;

		$this->associationMappings[$k]['isOwningSide'] = $isOwningSide;
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
