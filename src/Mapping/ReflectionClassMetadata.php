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
use NoreSources\Persistence\Mapping\Traits\ReflectionServiceClassMetadataIdentifierValueTrait;
use NoreSources\Reflection\ReflectionDocComment;
use NoreSources\Reflection\ReflectionFile;
use NoreSources\Reflection\ReflectionService;
use NoreSources\Reflection\ReflectionServiceInterface;
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
	use ReflectionServiceClassMetadataIdentifierValueTrait;

	const DEFAULT_ID_FIELD_NAME = 'id';

	const ASSOCIATION_FIELD_NAME_SUFFIX = 'Id';

	const COLLECTION_ASSOCIATION_FIELD_NAME_SUFFIX = 'Ids';

	public $lifecycleCallbacks;

	/**
	 *
	 * @param string|object $class
	 *        	Object or class name
	 */
	public function __construct($class)
	{
		if (\is_object($class))
			$class = \get_class($class);
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

	public function getReflectionService()
	{
		if (!isset($this->reflectionService))
			$this->reflectionService = ReflectionService::getInstance();
		return $this->reflectionService;
	}

	public function getFields()
	{
		if (!isset($this->fields))
		{
			$this->fields = [];
			$cls = $this->getReflectionClass();
			$flags = ReflectionServiceInterface::EXPOSE_HIDDEN_PROPERTY |
				ReflectionServiceInterface::RW;
			foreach ($cls->getProperties() as $p)
				$this->fields[$p->getName()] = [
					self::KEY_PROPERTY => $this->getReflectionService()->getReflectionProperty(
						$cls, $p->getName(), $flags)
				];
		}
		return $this->fields;
	}

	public function getReflectionProperties()
	{
		if (!isset($this->reflectionProperties))
		{
			$this->reflectionProperties = [];
			$cls = $this->getReflectionClass();
			$flags = ReflectionServiceInterface::EXPOSE_HIDDEN_PROPERTY |
				ReflectionServiceInterface::RW;
			foreach ($cls->getProperties() as $p)
				$this->reflectionProperties[$p->getName()] = $this->getReflectionService()->getReflectionProperty(
					$cls, $p->getName(), $flags);
		}
		return $this->reflectionProperties;
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
		$fields = $this->getFields();
		if (!\array_key_exists($fieldName, $fields))
			throw new \InvalidArgumentException(
				$fieldName . ' is not a field of ' . $this->className);
		$f = $fields[$fieldName];
		if (isset($f[self::KEY_TYPE]))
			return $f[self::KEY_TYPE];

		/**
		 *
		 * @var ReflectionProperty $p
		 */
		$p = $fields[$fieldName][self::KEY_PROPERTY];
		if (\method_exists($p, 'getType'))
		{
			$f[self::KEY_TYPE] = $p->getType();
			return $f[self::KEY_TYPE];
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

	public function getAssociationMappedByTargetField($assocName)
	{
		return null;
	}

	public function getFieldNames()
	{
		return \array_keys($this->getFields());
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
		$fields = $this->getFields();
		foreach ($names as $name)
		{
			$values[$name] = $fields[$name][self::KEY_PROPERTY]->getValue(
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
		return $this->getReflectionService()->getReflectionClass(
			$this->className);
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
		return \array_key_exists($fieldName, $this->getFields());
	}

	public function isSingleValuedAssociation($fieldName)
	{
		return $this->hasAssociation($fieldName) &&
			\str_ends_with($fieldName,
				self::ASSOCIATION_FIELD_NAME_SUFFIX);
	}

	public function getAssociationTargetClass($assocName)
	{
		/**
		 *
		 * @todo use ReflectionDocComment
		 */
		$length = \strlen($assocName);
		$name = \substr($assocName, 0,
			$length -
			\strlen(self::COLLECTION_ASSOCIATION_FIELD_NAME_SUFFIX));
		return Text::toPascalCase($name);
	}

	public function isAssociationInverseSide($assocName)
	{
		/**
		 *
		 * @todo ?
		 */
		return false;
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
	 * @var ReflectionServiceInterface
	 */
	private $reflectionService;

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
	private $fields;
}
