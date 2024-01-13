<?php

/**
 * Copyright © 2023 - 2024 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package Persistence
 */
namespace NoreSources\Persistence\Mapping\Driver;

use Doctrine\Instantiator\Instantiator;
use Doctrine\Persistence\Mapping\ClassMetadata;
use Doctrine\Persistence\Mapping\MappingException;
use Doctrine\Persistence\Mapping\Driver\MappingDriver;
use NoreSources\Container\Container;
use NoreSources\Http\ParameterMapSerializer;
use NoreSources\Persistence\Event\Event;
use NoreSources\Reflection\ReflectionConstant;
use NoreSources\Reflection\ReflectionDocComment;
use NoreSources\Reflection\ReflectionFile;
use NoreSources\Text\Text;
use NoreSources\Type\TypeConversion;
use NoreSources\Type\TypeDescription;
use ReflectionClass;

/**
 * A mapping driver that use Reflection and DocComments to generate class metadata
 *
 * This driver is compatible with Doctrine ORM ClassMetadata with some missing feature support.
 */
class ReflectionDriver implements MappingDriver
{

	/**
	 * Map all public properties as fields
	 */
	const PUBLIC_PROPERTY_AUTO_MAPPING = 0x01;

	/**
	 * Map method with a name corresponding to a lifecycle event
	 * as a lifecycle callback
	 */
	const LIFECYCLE_METHOD_AUTO_MAPPING = 0x02;

	/**
	 *
	 * @param string[] $paths
	 *        	Class paths
	 * @param integer $flags
	 *        	Driver flags
	 */
	public function __construct($paths, $flags = 0)
	{
		if (TypeDescription::getName($paths) == 'string')
			$paths = [
				$paths
			];
		foreach ($paths as $p)
			assert(\kis_dir($p));
		$this->paths = \array_map(
			function ($p) {
				return \realpath($p) . '/';
			}, $paths);
		$this->driverFlags = $flags;
	}

	/**
	 * The PHPDoc tag prefix used in PHP source files
	 */
	public $docBlockTagPrefix = 'persistent-';

	/**
	 *
	 * @return ReflectionClass
	 */
	public function getEventConstantsClass()
	{
		if (!isset($this->eventConstantsClass))
			$this->eventConstantsClass = Event::class;
		if (\is_string($this->eventConstantsClass))
			$this->eventConstantsClass = new \ReflectionClass(
				$this->eventConstantsClass);
		return $this->eventConstantsClass;
	}

	public static function parseIdGeneratorType($v,
		ClassMetadata $metadata)
	{
		// Doctrine ORM ClassMetadata like
		$t = self::getClassMetadataClassConstant($metadata,
			'GENERATOR_TYPE_', \strtoupper($v));
		if ($t !== null)
			return \intval($t);

		return $v;
	}

	/**
	 * Set the class containing the list of available events
	 *
	 * @param string|ReflectionClass $class
	 */
	public function setEventConstantClass($class)
	{
		$this->eventConstantsClass = $class;
	}

	/**
	 *
	 * @param ClassMetadata $metadata
	 *        	Target metadata
	 */
	public function loadMetadataForClass($className, $metadata)
	{
		$metadataClassName = \get_class($metadata);
		$instantiator = new Instantiator();
		$cls = new \ReflectionClass($className);
		$defaultInstance = $instantiator->instantiate($className);
		$filename = $cls->getFileName();
		assert($this->isInPaths($filename));

		$file = new ReflectionFile($filename);
		$block = new ReflectionDocComment($cls->getDocComment());

		if (!$this->hasTag($block, 'entity'))
			return;

		$entity = $this->getTag($block, 'entity');
		$entityParameters = self::getEntityTagParametersDescriptor();
		$entityOptions = [];

		self::parseParameters($entityOptions, $entity, $metadata,
			self::getEntityTagParametersDescriptor());

		if (($repositoryClass = Container::keyValue($entityOptions,
			'repositoryClass')))
			self::invokeClassMetadataMethod($metadata,
				"setCustomRepositoryClass", $repositoryClass);
		if (Container::keyValue($entityOptions, 'readOnly', false) ===
			true)
			self::invokeClassMetadataMethod($metadata, "markReadOnly");

		/**
		 *
		 * @todo mapped-superclass
		 * @todo embeddable
		 * @todo cacch
		 * @todo named-query
		 * @todo result-set-mappings
		 * @todo inheritance-type
		 * @todo indexes
		 * @todo unique-constraints
		 */

		$lifeCycleCallbacks = $this->getTags($block,
			'lifecycle-callbacks');

		foreach ($lifeCycleCallbacks as $text)
		{
			$parameters = [];
			ParameterMapSerializer::unserializeParameters($parameters,
				$text);

			if (Container::count($lifeCycleCallbacks) == 1 &&
				Container::count($parameters) == 0)
			{
				$class = $cls->getName();
				$parameters = $this->getEventParameters();
				foreach ($parameters as $parameter => $event)
				{
					if (\method_exists($class, $event))
						self::invokeClassMetadataMethod($metadata,
							"addLifecycleCallback", $event, $event);
				}
				continue;
			}

			foreach ($this->getEventParameters() as $parameter => $event)
			{
				$callback = Container::keyValue($parameters, $parameter);

				if (!$callback)
					continue;

				self::invokeClassMetadataMethod($metadata,
					"addLifecycleCallback", $callback, $event);
			}
		}

		if ($this->driverFlags & self::LIFECYCLE_METHOD_AUTO_MAPPING)
		{
			foreach ($this->getEventParameters() as $parameter => $event)
			{
				if (self::invokeClassMetadataMethod($metadata,
					"hasLifecycleCallbacks", $event))
					continue;
				if ($cls->hasMethod($event) &&
					($method = $cls->getMethod($event)) &&
					(!$method->isStatic() && $method->isPublic()))
				{
					self::invokeClassMetadataMethod($metadata,
						"addLifecycleCallback", $event, $event);
				}
			}
		}

		foreach ($this->getTags($block, 'entity-listener') as $text)
		{
			$parameters = [];
			ParameterMapSerializer::unserializeParameters($parameters,
				$text);

			$listenerClassName = Container::keyValue($parameters,
				'class');
			if (!$listenerClassName)
				throw MappingException::entityListenerClassNotFound(
					null, null);

			$listenerClassName = $this->resolveClassname(
				$listenerClassName, $file, $cls->getNamespaceName());

			unset($parameters['class']);
			if (Container::count($parameters) == 0)
			{
				/**
				 *
				 * @todo
				 */
				//EntityListenerBuilder::bindEntityListener($metadata, $listenerClassName);
				continue;
			}

			if (!\class_exists($listenerClassName))
				throw MappingException::entityListenerClassNotFound(
					$listenerClassName, $listenerClassName);

			foreach ($this->getEventParameters() as $parameter => $event)
			{
				$method = Container::keyValue($parameters, $parameter);
				if (!$method)
					continue;

				self::invokeClassMetadataMethod($metadata,
					"addEntityListener", $event, $listenerClassName,
					$method);
			}
		} // entity-listener

		$primaryTable = [];
		if (($table = Container::keyValue($entityOptions, 'table')))
		{
			$primaryTable['name'] = $table;
			if (($schema = Container::keyValue($entityOptions, 'schema')))
				$primaryTable['schema'] = $schema;
		}

		self::invokeClassMetadataMethod($metadata, "setPrimaryTable",
			$primaryTable);

		$propertyTagParameters = self::getPropertyTagParametersDescriptor();

		$associations = [
			'many-to-many' => [
				self::class,
				'getManyToManyTagParametersDescriptor'
			],
			'many-to-one' => [
				self::class,
				'getManyToOneTagParametersDescriptor'
			],
			'one-to-many' => [
				self::class,
				'getOneToManyTagParametersDescriptor'
			],
			'one-to-one' => [
				self::class,
				'getOneToOneTagParametersDescriptor'
			]
		];

		foreach ($cls->getProperties() as $property)
		{
			/**
			 *
			 * @todo attribute-overrides
			 * @todo association-overrides
			 */

			$text = $property->getDocComment();
			$block = new ReflectionDocComment($text);
			$name = $property->getName();
			$mapping = [
				'fieldName' => $name
			];
			$mapFunction = null;

			if ($this->hasTag($block, 'field'))
			{
				$mapFunction = 'mapField';
				if (($field = $this->getTag($block, 'field')) !== null)
					$this->parseParameters($mapping, $field, $metadata,
						\array_merge($propertyTagParameters,
							self::getFieldTagParametersDescriptor(
								$metadata)));
			} // field

			if ($this->hasTag($block, 'id'))
			{
				$mapFunction = 'mapField';
				if (($id = $this->getTag($block, 'id')) !== null)
					$this->parseParameters($mapping, $id, $metadata,
						self::getIdTagParametersDescriptor($metadata));
				$mapping['id'] = true;

				if (isset($mapping['generator']))
				{
					$type = $mapping['generator'];
					unset($mapping['generator']);
					$type = self::setSpecialGenerator($type, $metadata,
						$mapping);
					self::invokeClassMetadataMethod($metadata,
						"setIdGeneratorType", $type);
				}
			} // id

			foreach ($associations as $tagname => $f)
			{
				$tag = $this->getTag($block, $tagname);

				if (\is_null($tag))
					continue;

				$mapFunction = 'map' . Text::toPascalCase($tagname);

				$this->parseParameters($mapping, $tag, $metadata,
					call_user_func($f, $metadata));

				if ($this->hasTag($block, 'id'))
					$mapping['id'] = true;

				if (!isset($mapping['targetEntity']))
				{
					$variable = $block->getVariable();
					if ($variable === null)
						throw MappingException::missingTargetEntity(
							$mapping['fieldName'], $tagname);

					$mapping['targetEntity'] = $this->getTargetEntityFromVariableDeclaration(
						$variable, $file, $property, $tagname);
					if (!$mapping['targetEntity'])
						throw MappingException::missingTargetEntity(
							$mapping['fieldName'], $tagname);
				}

				if (!isset($mapping['joinColumns']))
					$mapping['joinColumns'] = [];
			} // each associations

			if ($mapFunction === null && $property->isPublic() &&
				($this->driverFlags & self::PUBLIC_PROPERTY_AUTO_MAPPING))
			{
				$mapFunction = 'mapField';
				$mapping['fieldName'] = $property->getName();
			}

			if ($mapFunction == 'mapField')
				$this->setAutomaticMapping($mapping, $file, $property,
					$block, $defaultInstance);

			if (isset($mapping['version']))
			{
				self::invokeClassMetadataMethod($metadata,
					"setVersionMapping", $mapping);
				unset($mapping['version']);
			}
			if (!$mapFunction)
				continue;

			self::invokeClassMetadataMethod($metadata, $mapFunction,
				$mapping);
		} // Properties
	}

	public function getAllClassNames()
	{
		if (isset($this->classNames))
			return $this->classNames;

		$this->classNames = [];

		foreach ($this->paths as $path)
		{
			$iterator = new \RecursiveDirectoryIterator($path);
			/**
			 *
			 * @var \SplFileInfo $item
			 */
			foreach ($iterator as $item)
			{
				if (!$item->isFile())
					continue;

				if (\strcasecmp($item->getExtension(), 'php') != 0)
					continue;

				$file = new ReflectionFile($item->getRealPath());

				$all = $file->getClassNames();
				$entities = [];
				foreach ($all as $name)
				{
					$cls = $file->getClass($name);
					if (!($cls instanceof ReflectionClass))
						continue;
					$text = $cls->getDocComment();
					$block = new ReflectionDocComment($text);
					if (!$this->hasTag($block, 'entity'))
						continue;
					$entities[] = $name;
				}

				$this->classNames = \array_merge($this->classNames,
					$entities);
			}
		}

		return $this->classNames;
	}

	public function isTransient($className)
	{
		if (isset($this->classNames))
			if (Container::valueExists($this->classNames, $className))
				return true;

		return \in_array($className, $this->getAllClassNames());
	}

	public static function stringToBoolean($v)
	{
		return (\strcasecmp($v, 'true') === 0);
	}

	public static function invertBoolean($v)
	{
		return !$value;
	}

	public static function getClassMetadataClassConstant(
		$metadataOrmetadataClassName, $prefix, $suffix)
	{
		try
		{
			$class = (\is_object($metadataOrmetadataClassName) ? \get_class(
				$metadataOrmetadataClassName) : $metadataOrmetadataClassName);
			$name = \strtoupper($prefix) . \strtoupper($suffix);
			$fullname = $class . '::' . $name;
			return @\constant($fullname);
		}
		catch (\Exception $e)
		{}
		catch (\Error $e)
		{}
	}

	/**
	 * Invoke class metadata instance method only if it exists.
	 *
	 * @param ClassMetadata $metadata
	 *        	Class metadata
	 * @param string $method
	 *        	Class metadata method name
	 * @param ... ...$arguments
	 *        	Method arguments
	 * @return void|mixed
	 */
	public static function invokeClassMetadataMethod($metadata, $method,
		...$arguments)
	{
		if (!\method_exists($metadata, $method))
			return;
		return \call_user_func_array([
			$metadata,
			$method
		], $arguments);
	}

	protected function parseParameters(&$output, $text,
		ClassMetadata $metadata, $parametersProperties)
	{
		$parameters = [];
		ParameterMapSerializer::unserializeParameters($parameters, $text);

		foreach ($parametersProperties as $key => $parameterProperties)
		{
			if (\is_integer($key) && \is_string($parameterProperties))
			{
				$key = $parameterProperties;
				$parameterProperties = [];
			}
			$okey = Container::keyValue($parameterProperties, 'key',
				Text::toCamelCase($key));
			$dflt = Container::keyValue($parameterProperties, 'default',
				null);
			$type = Container::keyValue($parameterProperties, 'type',
				'string');

			if (!Container::keyExists($parameters, $key))
			{
				if ($dflt !== null)
					$output[$okey] = $dflt;
				continue;
			}
			$value = $parameters[$key];
			if (($f = Container::keyValue($parameterProperties,
				'pre-set')))
				$value = \call_user_func($f, $value, $metadata);

			$value = TypeConversion::to($value, $type, $value);

			if (($f = Container::keyValue($parameterProperties,
				'post-set')))
				$value = \call_user_func($f, $value, $metadata);
			$output[$okey] = $value;
		}
	}

	protected function getTargetEntityFromVariableDeclaration($variable,

		$file, $property, $associationTagName)
	{
		$arrayAssociation = \preg_match('/many$/', $associationTagName);
		$namespace = $property->getDeclaringClass()->getNamespaceName();
		foreach ($variable['types'] as $declaration)
		{
			$type = ReflectionDocComment::getTypeDeclarationProperties(
				$declaration);
			$typeName = Container::keyValue($type, 'type');
			$family = TypeDescription::getTypenameFamily($typeName);

			if ($arrayAssociation)
			{
				if ($typeName != 'array')
					continue;
				$typeName = Container::keyValue($type, 'value');
				$family = TypeDescription::getTypenameFamily($typeName);
			}

			if (\in_array($family,
				[
					TypeDescription::FAMILY_PSEUDO_TYPE,
					TypeDescription::FAMILY_PRIMITIVE
				]))
				continue;

			$className = $this->resolveClassname($typeName, $file,
				$namespace);

			if (!$className)
				continue;

			$targetNamespace = \implode('\\',
				TypeDescription::getNamespaces($className, true));

			if ($targetNamespace == $namespace)
				$className = TypeDescription::getLocalName($className,
					true);

			return $className;
		}

		return null;
	}

	/**
	 * Call generator type-specific metadata methods.
	 *
	 * Mostly for Doctrine ORM ClassMetadata.
	 *
	 * @param mixed $type
	 *        	Generator type
	 * @param ClassMetadata $metadata
	 *        	Class metadata
	 * @param array $mapping
	 *        	Current mapping
	 */
	protected static function setSpecialGenerator($type, $metadata,
		&$mapping)
	{
		$metadataClassName = \get_class($metadata);

		if (\strcasecmp($type, 'custom') == 0)
		{
			self::invokeClassMetadataMethod($metadata,
				"setCustomGeneratorDefinition",
				[
					'class' => $mapping['customIdGeneratorClass']
				]);
			unset($mapping['customIdGeneratorClass']);
		}
		elseif (\strcasecmp($type, 'sequence') == 0)
		{
			self::invokeClassMetadataMethod($metadata,
				"setSequenceGeneratorDefinition", $mapping);
		}

		return self::parseIdGeneratorType($type, $metadata);
	}

	/**
	 *
	 * @param array $mapping
	 * @param \RefleconProperty $property
	 * @param ReflectionDocComment $block
	 */
	protected function setAutomaticMapping(&$mapping,
		ReflectionFile $file, \ReflectionProperty $property,
		ReflectionDocComment $block, $defaultInstance = null)
	{
		$nullable = false;
		if (!isset($mapping['type']))
		{
			$var = $block->getVariable();
			if (!\is_null($var))
				$this->setTypeAutomaticMapping($mapping, $file,
					$property, $var['types']);
		}

		if (!(Container::keyExists($mapping, 'options') &&
			Container::keyExists($mapping['options'], 'default')))
		{
			$this->setDefaultAutomaticMapping($mapping, $file, $property,
				$defaultInstance);
		}

		if (!(Container::keyExists($mapping, 'options') &&
			Container::keyExists($mapping['options'], 'comment')))
		{
			$value = Container::keyValue($block->getLines(), 0);
			if ($value != null && (\strpos($value, '@') !== 0))
			{
				if (!Container::keyExists($mapping, 'options'))
					$mapping['opptions'] = [];
				$mapping['options']['comment'] = $value;
			}
		}
	}

	protected function retrievePropertyDefaultValue(&$value,
		\ReflectionProperty $property, $defaultInstance = null)
	{
		if (\method_exists($property, 'hasDefaultValue') &&
			!$property->hasDefaultValue())
			return false;

		if (\method_exists($property, 'getDefaultValue'))
		{
			$value = $property->getDefaultValue();
			return true;
		}

		if (!$defaultInstance)
			return false;

		$public = $property->isPublic();
		if (!$public)
			$property->setAccessible(true);
		$value = $property->getValue($defaultInstance);
		$property->setAccessible($public);
		return true;
	}

	protected function setDefaultAutomaticMapping(&$mapping,
		ReflectionFile $file, \ReflectionProperty $property,
		$defaultInstance = null)
	{
		$value = null;
		if (!$this->retrievePropertyDefaultValue($value, $property,
			$defaultInstance))
			return;

		if (\is_object($value))
		{
			$type = Container::keyValue($mapping, 'type');
			if (\strcasecmp($type, 'json') === 0)
				$value = \json_encode($value, true);
			elseif (TypeDescription::hasStringRepresentation($value))
				$value = TypeConversion::toString($value);
			else
				return;
		}
		elseif (\is_array($value))
		{
			$type = Container::keyValue($mapping, 'type');
			if (\strcasecmp($type, 'json') === 0)
				$value = \json_encode($value, true);
			else
				return;
		}

		$nullable = Container::keyValue($mapping, 'nullable', false);
		if (\is_null($value))
			if ($nullable)
				return;

		if (\is_string($value) && empty($value))
			return;

		if (!Container::keyExists($mapping, 'options'))
			$mapping['opptions'] = [];
		$mapping['options']['default'] = $value;
	}

	/**
	 *
	 * @param unknown $mapping
	 * @param ReflectionFile $file
	 * @param \ReflectionProperty $property
	 * @param unknown $types
	 *        	List of type names from @var tag
	 */
	protected function setTypeAutomaticMapping(&$mapping,
		ReflectionFile $file, \ReflectionProperty $property, $types)
	{
		$assigned = false;
		$namespace = $property->getDeclaringClass()->getNamespaceName();
		foreach ($types as $declaration)
		{
			$type = ReflectionDocComment::getTypeDeclarationProperties(
				$declaration);
			$family = TypeDescription::getTypenameFamily($type['type']);

			if ($family == TypeDescription::FAMILY_PSEUDO_TYPE)
				continue;

			if ($family == TypeDescription::FAMILY_PRIMITIVE)
			{
				if (\strcasecmp($type['type'], 'null') === 0)
				{
					if (!isset($mapping['nullable']))
						$mapping['nullable'] = true;
					continue;
				}

				/**
				 *
				 * @todo if array of object
				 */

				if (!isset($mapping['type']))
					$mapping['type'] = $type['type'];
				continue;
			}

			if (isset($mapping['type']))
				continue;

			$name = $type['type'];
			$className = null;

			$className = $this->resolveClassname($name, $file,
				$namespace);

			if (!$className)
				continue;
			/**
			 *
			 * @todo more smart conversions
			 */

			$typeName = null;
			if (\in_array($className, $this->getAllClassNames()))
				$typeName = $className;
			elseif (\is_a($className, \DateTimeInterface::class, true))
				$typeName = 'datetime';

			if ($typeName)
				$mapping['type'] = $typeName;
		}
	}

	protected function resolveClassname($className, ReflectionFile $file,
		$namespace)
	{
		if (\class_exists($className))
			return $className;
		return $fqn = $file->getQualifiedName($className, NULL,
			[
				ReflectionFile::LOOKUP_GLOBAL => true,
				ReflectionFile::LOOKUP_NAMESPACES => [
					$namespace
				]
			]);
	}

	protected function isInPaths($filename)
	{
		$f = \realpath($filename);
		foreach ($this->paths as $p)
		{
			if (\strpos($f, $p) === 0)
				return true;
		}
		return false;
	}

	protected function getTag(ReflectionDocComment $block, $name,
		$index = 0)
	{
		$name = $this->docBlockTagPrefix . $name;
		return $block->getTag($name, $index);
	}

	protected function getTags(ReflectionDocComment $block, $name)
	{
		$name = $this->docBlockTagPrefix . $name;
		return $block->getTags($name);
	}

	protected function hasTag(ReflectionDocComment $block, $name)
	{
		$name = $this->docBlockTagPrefix . $name;
		return $block->hasTag($name);
	}

	protected function getEventParameters()
	{
		/**
		 *
		 * @todo
		 */
		return [];
		if (isset($this->lifeCycleEventParameters))
			return $this->lifeCycleEventParameters;
		$cls = $this->getEventConstantsClass();
		$this->lifeCycleEventParameters = [];

		/**
		 *
		 * @var ReflectionConstant $constant
		 */
		foreach ($cls->getConstants() as $name)
		{
			$key = \constant($cls->getName() . '::' . $name);
			$parameter = Text::toKebabCase($key);
			$this->lifeCycleEventParameters[$parameter] = $key;
		}

		return $this->lifeCycleEventParameters;
	}

	public static function getEntityTagParametersDescriptor()
	{
		if (!isset(self::$entityTagParametersDescriptor))
		{
			self::$entityTagParametersDescriptor = [
				'table',
				'schema',
				'read-only' => [
					'type' => 'boolean'
				],
				'repository-class'
			];
		}
		return self::$entityTagParametersDescriptor;
	}

	public static function getPropertyTagParametersDescriptor()
	{
		if (!isset(self::$propertyParametersDescriptor))
		{
			self::$propertyParametersDescriptor = [
				'field' => [
					'key' => 'fieldName'
				],
				'column' => [
					'key' => 'columnName'
				],
				'type',
				'length' => [
					'type' => 'integer'
				],
				'precision' => [
					'type' => 'integer'
				],
				'scale' => [
					'type' => 'integer'
				],
				'enum-type',
				/**
				 *
				 * @todo Move as a @persistent-column-definition tag
				 */
				'definition' => [
					'key' => 'columnDefinition'
				],
				'options'
			];
		}

		return self::$propertyParametersDescriptor;
	}

	public static function getIdTagParametersDescriptor(
		ClassMetadata $metadata)
	{
		if (!isset(self::$idTagParametersDescriptor))
			self::$idTagParametersDescriptor = [];

		$className = \get_class($metadata);
		if (!isset(self::$idTagParametersDescriptor[$className]))
		{
			self::$idTagParametersDescriptor[$className] = \array_merge(
				self::getPropertyTagParametersDescriptor(),
				[
					'generator',
					'sequence-name',
					'sequence-allocation-size',
					'sequence-initial-value',
					'custom-id-generator-class'
				]);
		}
		return self::$idTagParametersDescriptor[$className];
	}

	public static function getFieldTagParametersDescriptor(
		ClassMetadata $metadata)
	{
		if (!isset(self::$fieldTagParametersDescriptor))
			self::$fieldTagParametersDescriptor = [];
		$className = \get_class($metadata);
		if (!isset(self::$fieldTagParametersDescriptor[$className]))
		{
			self::$fieldTagParametersDescriptor[$className] = [
				'version',
				'unique' => [
					'pre-set' => [
						self::class,
						'stringToBoolean'
					],
					'type' => 'boolean'
				],
				'nullable' => [
					'pre-set' => [
						self::class,
						'stringToBoolean'
					],
					'type' => 'boolean'
				],
				'generated' => [
					'pre-set' => [
						self::class,
						'stringToBoolean'
					],
					'type' => 'boolean',
					'pre-set' => function ($v) use ($metadata) {
						return self::getClassMetadataClassConstant(
							$metadata, 'GENERATED_', $v);
					}
				],
				'insertable' => [
					'pre-set' => [
						self::class,
						'stringToBoolean'
					],
					'type' => 'boolean',
					'key' => 'notInsertable',
					'post-set' => [
						self::class,
						'invertBoolean'
					]
				],
				'updatable' => [
					'pre-set' => [
						self::class,
						'stringToBoolean'
					],
					'type' => 'boolean',
					'key' => 'notUpdatable',
					'post-set' => [
						self::class,
						'invertBoolean'
					]
				]
			];
		}
		return self::$fieldTagParametersDescriptor[$className];
	}

	public static function getAssociationTagParametersDescriptor(
		ClassMetadata $metadata)
	{
		if (!isset(self::$associationTagParametersDescriptor))
			self::$associationTagParametersDescriptor = [];

		$className = \get_class($metadata);
		if (!isset(
			self::$associationTagParametersDescriptor[$className]))
		{
			self::$associationTagParametersDescriptor[$className] = [
				'target-entity',
				'field' => [
					'key' => 'fieldName'
				],
				'join-columns',
				'fetch' => [
					'type' => 'integer',
					'pre-set' => function ($v) use ($metadata) {
						return self::getClassMetadataClassConstant(
							$metadata, 'FETCH_', $v);
					}
				]

			/**
			 *
			 * @todo cascade
			 * @todo orphan-removal
			 * @todo cache
			 */
			];
		}
		return self::$associationTagParametersDescriptor[$className];
	}

	public static function getManyToManyTagParametersDescriptor(
		ClassMetadata $metadata)
	{
		return self::getAssociationTagParametersDescriptor($metadata);
	}

	public static function getManyToOneTagParametersDescriptor(
		ClassMetadata $metadata)
	{
		if (!isset(self::$manyToOneTagParametersDescriptor))
			self::$manyToOneTagParametersDescriptor = [];
		$className = \get_class($metadata);
		if (!isset(self::$manyToOneTagParametersDescriptor[$className]))
		{
			self::$manyToOneTagParametersDescriptor[$className] = \array_merge(
				self::getAssociationTagParametersDescriptor($metadata),
				[
					'inversed-by'
				]);
		}
		return self::$manyToOneTagParametersDescriptor[$className];
	}

	public static function getOneToManyTagParametersDescriptor(
		ClassMetadata $metadata)
	{
		if (!isset(self::$oneToManyTagParametersDescriptor))
			self::$oneToManyTagParametersDescriptor = [];
		$className = \get_class($metadata);

		if (!isset(self::$oneToManyTagParametersDescriptor[$className]))
		{
			self::$oneToManyTagParametersDescriptor[$className] = \array_merge(
				self::getAssociationTagParametersDescriptor($metadata),
				[
					'mapped-by'
				]);
		}
		return self::$oneToManyTagParametersDescriptor[$className];
	}

	public static function getOneToOneTagParametersDescriptor(
		ClassMetadata $metadata)
	{
		return self::getAssociationTagParametersDescriptor($metadata);
	}

	/**
	 *
	 * @var \ReflectionClass|string
	 */
	private $eventConstantsClass;

	/**
	 *
	 * @var string[]
	 */
	private $classNames;

	/**
	 *
	 * @var string[]
	 */
	private $paths;

	/**
	 *
	 * @var array
	 */
	private $lifeCycleEventParameters;

	/**
	 *
	 * @var integer
	 */
	private $driverFlags = 0;

	/**
	 * Parameters descritpr for the entity tag.
	 *
	 * @var array
	 */
	private static $entityTagParametersDescriptor;

	/**
	 * Common parameters for all kind of properties (field, id, association)
	 *
	 * @var array
	 */
	private static $propertyParametersDescriptor;

	/**
	 *
	 * "field" tag parameters
	 *
	 * @var array
	 */
	private static $fieldTagParametersDescriptor;

	/**
	 *
	 * "id" tag parameters
	 *
	 * @var array
	 */
	private static $idTagParametersDescriptor;

	/**
	 * Common associations tag parameters
	 *
	 * @var array
	 */
	private static $associationTagParametersDescriptor;

	private static $manyToOneTagParametersDescriptor;

	private static $oneToManyTagParametersDescriptor;
}
