<?php

/**
 * Copyright © 2023 - 2024 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package Persistence
 */
namespace NoreSources\Persistence\Mapping;

use Doctrine\Persistence\Mapping\ClassMetadata;
use NoreSources\Persistence\Event\Event;
use NoreSources\Persistence\Mapping\Traits\IdGeneratorTypeClassnameTrait;
use NoreSources\Persistence\Mapping\Traits\ReflectionServiceClassMetadataTrait;
use NoreSources\Reflection\ReflectionDocComment;
use NoreSources\Reflection\ReflectionFile;
use NoreSources\Text\Text;
use NoreSources\Type\TypeDescription;
use ReflectionProperty;

/**
 * A basic impementation of ClassMetadata that use Reflection to implements ClassMetadata interface.
 * This class should only be used in tests or development phase.
 */
class ReflectionClassMetadata implements ClassMetadata
{

	use IdGeneratorTypeClassnameTrait;
	use ReflectionServiceClassMetadataTrait;

	/**
	 * Embed parent fields and associations
	 *
	 * @var integer
	 */
	const EMBED_PARENT = 0x04;

	const DEFAULT_ID_FIELD_NAME = 'id';

	const ASSOCIATION_FIELD_NAME_SUFFIX = 'Id';

	const COLLECTION_ASSOCIATION_FIELD_NAME_SUFFIX = 'Ids';

	public $lifecycleCallbacks;

	public $isMappedSuperclass = false;

	/**
	 *
	 * @param string|object $class
	 *        	Object or class name
	 */
	public function __construct($class, $flags = 0)
	{
		if (\is_object($class))
			$class = \get_class($class);
		$this->reflectionMappingFlags = $flags;
		if (!\is_string($class))
			throw new \InvalidArgumentException(
				'$class must be a class name or class instance. Got ' .
				TypeDescription::getName($class));
		$this->className = $class;
		$this->lifecycleCallbacks = [
			Event::prePersist => [],
			Event::postPersist => [],
			Event::preUpdate => [],
			Event::postUpdate => [],
			Event::preRemove => [],
			Event::postRemove => []
		];

		foreach ($this->lifecycleCallbacks as $eventName => $_unused)
		{
			if (\method_exists($this->className, $eventName))
				$this->lifecycleCallbacks[$eventName] = [
					$eventName
				];
			$this->isMappedSuperclass = $this->getReflectionClass()->isAbstract();
		}
	}

	public function getClassNameBasedIdentifierFieldname()
	{
		if (!isset($this->classNameBasedIdentifierFieldName))
		{
			$shortName = $this->getReflectionClass()->getShortName();
			$this->classNameBasedIdentifierFieldName = Text::toCamelCase(
				$shortName) . 'Id';
		}

		return $this->classNameBasedIdentifierFieldName;
	}

	/**
	 *
	 * @return array
	 */
	public function getFieldMappings()
	{
		if (!isset($this->fieldMappings))
		{
			$embed = ($this->reflectionMappingFlags & self::EMBED_PARENT) ==
				self::EMBED_PARENT;
			$this->fieldMappings = [];
			$cls = $this->getReflectionClass();

			foreach ($cls->getProperties() as $p)
			{
				if (!$embed && $this->isParentProperty($p))
					continue;
				$this->fieldMappings[$p->getName()] = [
					self::KEY_PROPERTY => $this->getReflectionService()->getAccessibleProperty(
						$cls->getName(), $p->getName())
				];
			}

			if ($embed)
			{
				$base = $cls;
				while (($base = $base->getParentClass()))
				{
					foreach ($base->getProperties() as $p)
					{
						if (\array_key_exists($p->getName(),
							$this->reflectionMappingFlags))
							continue;
						$this->fieldMappings[$p->getName()] = [
							self::KEY_PROPERTY => $this->getReflectionService()->getAccessibleProperty(
								$cls->getName(), $p->getName())
						];
					}
				}
			}
		}
		return $this->fieldMappings;
	}

	public function getReflectionProperties()
	{
		return \array_map(
			function ($e) {
				return $e[self::KEY_PROPERTY];
			}, $this->getFieldMappings());
	}

	public function isIdentifier($fieldName)
	{
		if ($fieldName == static::DEFAULT_ID_FIELD_NAME)
			return true;
		if ($fieldName == $this->getClassNameBasedIdentifierFieldname())
			return true;
		return false;
	}

	public function getName()
	{
		return $this->className;
	}

	public function getTypeOfField($fieldName)
	{
		$fieldMappings = $this->getFieldMappings();
		if (!\array_key_exists($fieldName, $fieldMappings))
			throw new \InvalidArgumentException(
				$fieldName . ' is not a field of ' . $this->className);
		$f = $fieldMappings[$fieldName];
		if (isset($f[self::KEY_TYPE]))
			return $f[self::KEY_TYPE];

		/**
		 *
		 * @var ReflectionProperty $p
		 */
		$p = $fieldMappings[$fieldName][self::KEY_PROPERTY];
		if (\method_exists($p, 'getType'))
		{
			$reflectionType = $p->getType();
			if (!empty($reflectionType) &&
				\strcasecmp($reflectionType, 'null') != 0)
			{
				$f[self::KEY_TYPE] = $reflectionType;
				return $f[self::KEY_TYPE];
			}
		}

		$doc = new ReflectionDocComment($p->getDocComment());
		$var = $doc->getVariable();
		$f[self::KEY_TYPE] = 'string';
		if (!$var)
			return $f[self::KEY_TYPE];

		foreach ($var['types'] as $type)
		{
			if (\strcasecmp($type, 'null') == 0)
				continue;
			$properties = ReflectionDocComment::getTypeDeclarationProperties(
				$type);
			if (\array_key_exists('value', $properties))
			{
				$f[self::KEY_TYPE] = 'array';
				break;
			}
			$type = $properties['type'];

			$family = TypeDescription::getTypenameFamily($type);
			if ($family == TypeDescription::FAMILY_PSEUDO_TYPE)
				continue;
			if ($family == TypeDescription::FAMILY_PRIMITIVE)
			{
				$f[self::KEY_TYPE] = $type;
				break;
			}

			$file = $this->getReflectionFile();
			$type = $file->getQualifiedName($type);
			$f[self::KEY_TYPE] = $type;
			return $f[self::KEY_TYPE];
		}

		return $f[self::KEY_TYPE];
	}

	public function getAssociationMappedByTargetField($associationName)
	{
		return null;
	}

	public function getFieldNames()
	{
		return \array_keys($this->getFieldMappings());
	}

	public function getIdentifierFieldNames()
	{
		return \array_filter($this->getFieldNames(),
			[
				$this,
				'isIdentifier'
			]);
	}

	public function getAssociationNames()
	{
		/**
		 *
		 * @todo ?
		 */
		return \array_filter($this->getFieldNames(),
			function ($name) {
				if ($this->isIdentifier($name))
					return false;
				if ($name == static::DEFAULT_ID_FIELD_NAME ||
				$name == static::ASSOCIATION_FIELD_NAME_SUFFIX ||
				$name == static::COLLECTION_ASSOCIATION_FIELD_NAME_SUFFIX)
					return false;
				if (\str_ends_with($name,
					static::COLLECTION_ASSOCIATION_FIELD_NAME_SUFFIX))
					return true;
				if (\str_ends_with($name,
					static::ASSOCIATION_FIELD_NAME_SUFFIX))
					return true;
				return false;
			});
	}

	public function getIdentifier()
	{
		return $this->getIdentifierFieldNames();
	}

	public function getIdentifierValues($object)
	{
		$names = $this->getIdentifierFieldNames();
		$values = [];
		$fieldMappings = $this->getFieldMappings();
		foreach ($names as $name)
		{
			$values[$name] = $fieldMappings[$name][self::KEY_PROPERTY]->getValue(
				$object);
		}
		return $values;
	}

	public function hasAssociation($fieldName)
	{
		return \in_array($fieldName, $this->getAssociationNames());
	}

	public function isCollectionValuedAssociation($fieldName)
	{
		return $this->hasAssociation($fieldName) &&
			\str_ends_with($fieldName,
				static::COLLECTION_ASSOCIATION_FIELD_NAME_SUFFIX);
	}

	public function getReflectionClass()
	{
		return $this->getReflectionService()->getClass($this->className);
	}

	public function getReflectionFile()
	{
		if (!isset($this->reflectionFile))
		{
			$cls = $this->getReflectionClass();
			$this->reflectionFile = new ReflectionFile(
				$cls->getFileName(), ReflectionFile::LOADED);
		}
		return $this->reflectionFile;
	}

	public function hasField($fieldName)
	{
		return \array_key_exists($fieldName, $this->getFieldMappings());
	}

	public function isSingleValuedAssociation($fieldName)
	{
		return $this->hasAssociation($fieldName) &&
			\str_ends_with($fieldName,
				self::ASSOCIATION_FIELD_NAME_SUFFIX);
	}

	public function getAssociationTargetClass($associationName)
	{
		/**
		 *
		 * @todo use ReflectionDocComment
		 */
		$length = \strlen($associationName);
		$name = \substr($associationName, 0,
			$length -
			\strlen(self::COLLECTION_ASSOCIATION_FIELD_NAME_SUFFIX));
		return Text::toPascalCase($name);
	}

	public function isAssociationInverseSide($associationName)
	{
		/**
		 *
		 * @todo ?
		 */
		return false;
	}

	/**
	 *
	 * @param \ReflectionProperty $property
	 * @return boolean
	 */
	private function isParentProperty(\ReflectionProperty $property)
	{
		return \strcasecmp($this->className,
			$property->getDeclaringClass()->getName()) != 0;
	}

	/**
	 * Qualified class name
	 *
	 * @var string
	 */
	private $className;

	/**
	 * The default fiend name for object ID constructed by concatenation of short class name and
	 * 'Id'.
	 *
	 * Example: \Foo\Bar\MyClass -> myClassId
	 *
	 * @var string
	 */
	private $classNameBasedIdentifierFieldName;

	/**
	 *
	 * @var ReflectionFile
	 */
	private $reflectionFile;

	const KEY_PROPERTY = 'property';

	const KEY_TYPE = 'type';

	/**
	 *
	 * @var array
	 */
	private $fieldMappings;

	/**
	 *
	 * @var integer
	 */
	private $reflectionMappingFlags = 0;
}
