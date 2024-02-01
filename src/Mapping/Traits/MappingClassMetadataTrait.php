<?php

/**
 * Copyright © 2024 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package Persistence
 */
namespace NoreSources\Persistence\Mapping\Traits;

use NoreSources\Container\Container;
use NoreSources\Persistence\Mapping\ClassMetadataAdapter;

trait MappingClassMetadataTrait
{
	use FieldMappingClassMetadataTrait;
	use AssociationMappingClassMetadataTrait;

	public function getTypeOfField($fieldName)
	{
		if ($this->hasField($fieldName))
		{
			$field = Container::keyValue($this->fieldMappings,
				$fieldName);
			if (!$field)
				throw new \InvalidArgumentException(
					'Could not get ' . $fieldName . ' field mapping');
			return Container::keyValue($field,
				ClassMetadataAdapter::MAPPING_TYPE);
		}

		if ($this->hasAssociation($fieldName))
		{
			$field = Container::keyValue($this->associationMappings,
				$fieldName);
			if (!$field)
				throw new \InvalidArgumentException(
					'Could not get ' . $fieldName .
					' association mapping');
			return Container::keyValue($field,
				ClassMetadataAdapter::MAPPING_TYPE);
		}

		throw new \InvalidArgumentException(
			$fieldName . ' is not a field of ' . $this->getName());
	}
}
