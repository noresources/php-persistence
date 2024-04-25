<?php

/**
 * Copyright © 2023 - 2024 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package Persistence
 */
namespace NoreSources\Persistence\Mapping\Driver;

use Doctrine\Inflector\Inflector;
use Doctrine\Inflector\InflectorFactory;
use Doctrine\Inflector\Language;
use Doctrine\Instantiator\Instantiator;
use Doctrine\ORM\Mapping\MappingException;
use Doctrine\Persistence\Mapping\ClassMetadata;
use Doctrine\Persistence\Mapping\Driver\MappingDriver;
use NoreSources\Container\Container;
use NoreSources\Http\ParameterMapSerializer;
use NoreSources\Persistence\Event\Event;
use NoreSources\Persistence\Mapping\ClassMetadataAdapter;
use NoreSources\Reflection\ReflectionConstant;
use NoreSources\Reflection\ReflectionDocComment;
use NoreSources\Reflection\ReflectionFile;
use NoreSources\Text\Text;
use NoreSources\Type\TypeConversion;
use NoreSources\Type\TypeDescription;
use Closure;
use ReflectionClass;
use ReflectionProperty;

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
	 * Embed parent fields and associations
	 *
	 * @var integer
	 */
	const EMBED_PARENT = 0x04;

	/**
	 * When possible, keep short class names for association target class.
	 *
	 * This flag is mostly used for compatibility with other Doctrine drivers.
	 *
	 * @var number
	 */
	const ASSOCIATION_TARGET_SHORT_NAME = 0x08;

	const TAG_ENTITY = 'entity';

	const TAG_FIELD = 'field';

	const TAG_ID = 'id';

	const TAG_MANY_TO_MANY = 'many-to-many';

	const TAG_MANY_TO_ONE = 'many-to-one';

	const TAG_ONE_TO_MANY = 'one-to-many';

	const TAG_ONE_TO_ONE = 'one-to-one';

	/**
	 * Field mapping options.
	 *
	 * @var string
	 */
	const TAG_OPTIONS = 'options';

	/**
	 * Extra field metadata that should be used for other purpose than object mapping (ex.
	 * UI attributes).
	 *
	 * @var string
	 */
	const TAG_EXTRA = 'extra';

	/**
	 * The PHPDoc tag prefix used in PHP source files
	 */
	public $docBlockTagPrefix = 'persistent-';

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
			assert(\is_dir($p));
		$this->paths = \array_map(
			function ($p) {
				return \realpath($p) . '/';
			}, $paths);
		$this->driverFlags = $flags;
	}

	public function getInflector()
	{
		if (!isset($this->inflector))
		{
			$f = InflectorFactory::createForLanguage(Language::ENGLISH);
			$this->inflector = $f->build();
		}

		return $this->inflector;
	}

	public function setInflector(Inflector $inflector)
	{
		$this->inflector = $inflector;
	}

	public function getAllClassNames()
	{
		if (isset($this->classNames))
			return $this->classNames;

		$this->classNames = [];

		foreach ($this->paths as $path)
		{
			$iterator = new \RecursiveDirectoryIterator($path);
			$iterator = new \RecursiveIteratorIterator($iterator);
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
					$reflectionClass = $file->getClass($name);
					if (!($reflectionClass instanceof ReflectionClass))
						continue;
					$text = $reflectionClass->getDocComment();
					$block = new ReflectionDocComment($text);
					if (!$this->hasTag($block, self::TAG_ENTITY))
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
		$names = $this->getAllClassNames();
		return !Container::valueExists($names, $className);
	}

	/**
	 *
	 * @param ClassMetadata $metadata
	 *        	Target metadata
	 */
	public function loadMetadataForClass($className, $metadata)
	{
		$initialMetadata = clone $metadata;
		$reflectionClass = new \ReflectionClass($className);
		$this->cacheMetadataForClass($className, $metadata);

		$this->postprocessAssociationMappings(
			$this->metadataMappingCache[$className][self::MAPPING_ASSOCIATIONS],
			[
				'class' => $reflectionClass,
				'metadata' => $initialMetadata
			]);

		$fields = $this->metadataMappingCache[$className][self::MAPPING_FIELDS];
		$associations = $this->metadataMappingCache[$className][self::MAPPING_ASSOCIATIONS];
		$this->mapFields($metadata, $fields);
		$this->mapAssociations($metadata, $associations);
	}

	protected function postprocessAssociationMappings(&$associations,
		$context = array())
	{
		foreach ($associations as $name => $data)
		{
			$associations[$name]['mapping'] = $this->postprocessAssociationMapping(
				$data['mapping'], $data['mapFunction'], $context);
		}
	}

	protected function postprocessAssociationMapping($mapping,
		$mapFunction, $context = array())
	{
		$reflectionClass = $context['class'];

		$fieldName = $mapping['fieldName'];
		$columnName = Container::keyValue($mapping, 'columnName',
			$fieldName);

		$targetClassName = $this->getQualifiedClassName(
			$mapping['targetEntity'],
			$reflectionClass->getNamespaceName());

		if ($this->isTransient($targetClassName))
			return $mapping;

		$className = $reflectionClass->getName();
		$metadata = $context['metadata'];
		$sourceId = $this->getIdentifierMapping($className,
			clone $metadata);
		if (!$sourceId)
		{
			/**
			 *
			 * @todo error
			 */
			return $mapping;
		}

		$sourceIdName = $sourceId['fieldName'];
		$sourceIdColumnName = Container::keyValue($sourceId,
			'columnName', $sourceIdName);

		$targetFieldName = Container::keyValue($mapping,
			'referencedFieldName');
		$targetColumnName = '';
		if (empty($targetFieldName))
		{
			$m = clone $metadata;
			$targetId = $this->getIdentifierMapping($targetClassName, $m);
			if (!$targetId)
			{
				/**
				 *
				 * @todo error
				 */
				return $mapping;
			}

			$targetFieldName = $targetId['fieldName'];
			$targetColumnName = Container::keyValue($targetId,
				'columnName', $targetFieldName);
		}
		if (empty($targetColumnName))
			$targetColumnName = $targetFieldName;

		if ($mapFunction == 'mapManyToOne')
		{
			$mapping['joinColumns'] = [
				[
					// 'name' => $targetFieldName,
					'referencedColumnName' => $targetColumnName
				]
			];
		}
		elseif ($mapFunction == 'mapManyToMany')
		{
			$tableName = $this->getTableNameFromClassName($className);
			$tableName .= '_' . $columnName;
			$tableName .= '_' .
				$this->getTableNameFromClassName($targetClassName);

			$mapping['joinTable'] = [
				'name' => $tableName,
				'joinColumns' => [
					[
						//'name' => $sourceIdName,
						'referencedColumnName' => $sourceIdColumnName
					]
				],
				'inverseJoinColumns' => [
					[
						//'name' => $targetFieldName,
						'referencedColumnName' => $targetColumnName
					]
				]
			];
		}
		return $mapping;
	}

	protected function getIdentifierMapping($className, $metadata)
	{
		$cache = Container::keyValue($this->metadataMappingCache,
			$className);
		if (!$cache)
		{
			$this->cacheMetadataForClass($className, $metadata);
			$cache = $this->metadataMappingCache[$className];
		}
		$fields = $cache[self::MAPPING_FIELDS];

		foreach ($fields as $mapping)
		{
			if (Container::keyValue($mapping, 'id'))
				return $mapping;
		}
		return null;
	}

	protected function cacheMetadataForClass($className, $metadata)
	{
		$reflectionClass = new \ReflectionClass($className);

		ClassMetadataAdapter::assignMetadataElement($metadata,
			'namespace', $reflectionClass->getNamespaceName());

		$filename = $reflectionClass->getFileName();
		$file = new ReflectionFile($filename);
		if (!$this->isInPaths($filename))
			throw new MappingException('Out of path class');

		$block = new ReflectionDocComment(
			$reflectionClass->getDocComment());

		if (!$this->hasTag($block, self::TAG_ENTITY))
		{
			$isMappedSuperclass = $reflectionClass->isAbstract();
			ClassMetadataAdapter::assignMetadataElement($metadata,
				'isMappedSuperclass', $isMappedSuperclass);
			return;
		}

		$defaultInstance = null;
		if (!$reflectionClass->isAbstract())
		{
			$instantiator = new Instantiator();
			$defaultInstance = $instantiator->instantiate($className);
		}
		$entity = $this->getTag($block, self::TAG_ENTITY);
		$entityParameters = self::getEntityTagParametersDescriptor();
		$entityOptions = [];

		if (!empty($entity))
		{
			$entityParameters = self::getEntityTagParametersDescriptor();
			self::parseParameters($entityOptions, $entity, $metadata,
				self::getEntityTagParametersDescriptor());
			if (($repositoryClass = Container::keyValue($entityOptions,
				'repositoryClass')))
				self::invokeClassMetadataMethod($metadata,
					"setCustomRepositoryClass", $repositoryClass);
			if (Container::keyValue($entityOptions, 'readOnly', false) ===
				true)
				self::invokeClassMetadataMethod($metadata,
					"markReadOnly");
		}

		$isMappedSuperclass = false;
		if (($mappedSuperclass = Container::keyValue($entityOptions,
			'mappedSuperclass')) !== null)
			$isMappedSuperclass = $mappedSuperclass;
		else
			$isMappedSuperclass = $reflectionClass->isAbstract();

		ClassMetadataAdapter::assignMetadataElement($metadata,
			'isMappedSuperclass', $isMappedSuperclass);

		/**
		 *
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
				$class = $reflectionClass->getName();
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
				if ($reflectionClass->hasMethod($event) &&
					($method = $reflectionClass->getMethod($event)) &&
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
				$listenerClassName, $file,
				$reflectionClass->getNamespaceName());

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

		// Doctrine ORM compatibility
		{
			$primaryTable = [];
			$tableName = $this->getTableNameFromClassName($className);
			$primaryTable['name'] = Container::keyValue($entityOptions,
				'table', $tableName);
			if (($schema = Container::keyValue($entityOptions, 'schema')))
				$primaryTable['schema'] = $schema;

			self::invokeClassMetadataMethod($metadata, "setPrimaryTable",
				$primaryTable);
		}

		$visited = [];

		$this->processProperties($visited, $metadata, $file,
			$reflectionClass, $defaultInstance);
		if (($this->driverFlags & self::EMBED_PARENT) ==
			self::EMBED_PARENT)
		{
			while (($reflectionClass = $reflectionClass->getParentClass()))
				$this->processProperties($visited, $metadata, $file,
					$reflectionClass, $defaultInstance);
		}
	}

	protected function getTableNameFromClassName($className)
	{
		$name = TypeDescription::getLocalName($className, true);
		return $this->getInflector()->pluralize($name);
	}

	protected function getQualifiedClassName($className, $namespace)
	{
		if (\strpos($className, '\\') === false)
		{
			if (!empty($namespace))
				$className = $namespace . '\\' . $className;
		}
		return ltrim($className, '\\');
	}

	/**
	 *
	 * @return ReflectionClass
	 */
	protected function getEventConstantsClass()
	{
		if (!isset($this->eventConstantsClass))
			$this->eventConstantsClass = Event::class;
		if (\is_string($this->eventConstantsClass))
			$this->eventConstantsClass = new \ReflectionClass(
				$this->eventConstantsClass);
		return $this->eventConstantsClass;
	}

	protected static function parseIdGeneratorType($v,
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
	protected function setEventConstantClass($class)
	{
		$this->eventConstantsClass = $class;
	}

	protected function mapFields(ClassMetadata $metadata, $mappings = [])
	{
		$mapFunction = 'mapField';
		foreach ($mappings as $field => $mapping)
		{
			if (isset($mapping['generator']))
			{
				$type = $mapping['generator'];
				unset($mapping['generator']);
				$type = self::setSpecialGenerator($type, $metadata,
					$mapping);
				self::invokeClassMetadataMethod($metadata,
					"setIdGeneratorType", $type);
			}
			if (isset($mapping['version']))
			{
				self::invokeClassMetadataMethod($metadata,
					"setVersionMapping", $mapping);
				unset($mapping['version']);
			}

			if (isset($mapping['version']))
			{
				self::invokeClassMetadataMethod($metadata,
					"setVersionMapping", $mapping);
				unset($mapping['version']);
			}
			self::invokeClassMetadataMethod($metadata, $mapFunction,
				$mapping);
		}
	}

	protected function mapAssociations(ClassMetadata $metadata,
		$mappings)
	{
		foreach ($mappings as $field => $data)
		{

			$mapFunction = $data['mapFunction'];
			$mapping = $data['mapping'];
			self::invokeClassMetadataMethod($metadata, $mapFunction,
				$mapping);
		}
	}

	protected function processProperties(&$visited,
		ClassMetadata $metadata, ReflectionFile $file,
		\ReflectionClass $reflectionClass, $defaultInstance)
	{
		$className = $reflectionClass->getName();

		if (!Container::keyExists($this->metadataMappingCache,
			$className))
			$this->metadataMappingCache[$className] = [
				self::MAPPING_FIELDS => [],
				self::MAPPING_ASSOCIATIONS => []
			];

		$visited = \array_unique(
			\array_merge($visited,
				Container::keys(
					$this->metadataMappingCache[$className][self::MAPPING_FIELDS]),
				Container::keys(
					$this->metadataMappingCache[$className][self::MAPPING_ASSOCIATIONS])));

		$propertyTagParameters = self::getPropertyTagParametersDescriptor();
		$associations = [
			self::TAG_MANY_TO_MANY => [
				self::class,
				'getManyToManyTagParametersDescriptor'
			],
			self::TAG_MANY_TO_ONE => [
				self::class,
				'getManyToOneTagParametersDescriptor'
			],
			self::TAG_ONE_TO_MANY => [
				self::class,
				'getOneToManyTagParametersDescriptor'
			],
			self::TAG_ONE_TO_ONE => [
				self::class,
				'getOneToOneTagParametersDescriptor'
			]
		];
		foreach ($reflectionClass->getProperties() as $property)
		{
			/**
			 *
			 * @var ReflectionProperty $property /**
			 *
			 * @todo attribute-overrides
			 * @todo association-overrides
			 */

			if (\in_array($property->getName(), $visited))
				continue;

			$source = $property->getDeclaringClass();

			if (\strcasecmp($reflectionClass->getName(),
				$source->getName()))
				continue;

			$text = $property->getDocComment();
			$block = new ReflectionDocComment($text);
			$name = $property->getName();
			$mapping = [
				'fieldName' => $name
			];
			$mapFunction = null;

			if ($this->hasTag($block, self::TAG_FIELD))
			{
				$mapFunction = 'mapField';
				if (($field = $this->getTag($block, self::TAG_FIELD)) !==
					null)
					$this->parseParameters($mapping, $field, $metadata,
						\array_merge($propertyTagParameters,
							self::getFieldTagParametersDescriptor()));
			} // field

			if ($this->hasTag($block, self::TAG_ID))
			{
				$mapFunction = 'mapField';
				if (($id = $this->getTag($block, self::TAG_ID)) !== null)
					$this->parseParameters($mapping, $id, $metadata,
						self::getIdTagParametersDescriptor($metadata));
				$mapping['id'] = true;
			} // id

			foreach ($associations as $tagname => $f)
			{
				$tag = $this->getTag($block, $tagname);

				if (\is_null($tag))
					continue;

				$mapFunction = 'map' . Text::toPascalCase($tagname);

				$this->parseParameters($mapping, $tag, $metadata,
					call_user_func($f, $metadata));

				if ($this->hasTag($block, self::TAG_ID))
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
			} // each associations

			if ($mapFunction === null && $property->isPublic() &&
				($this->driverFlags & self::PUBLIC_PROPERTY_AUTO_MAPPING))
			{
				$mapFunction = 'mapField';
			}

			if (!$mapFunction)
				continue;

			foreach ([
				self::TAG_OPTIONS,
				self::TAG_EXTRA
			] as $tag)
			{
				if (!$this->hasTag($block, $tag))
					continue;
				$text = $this->getTag($block, $tag);
				if (\strlen($text) == 0)
					continue;
				$parameters = [];
				ParameterMapSerializer::unserializeParameters(
					$parameters, $text);
				if (!isset($mapping[$tag]))
					$mapping[$tag] = [];
				foreach ($parameters as $key => $variable)
				{
					$key = Text::toCamelCase($key);
					if (Container::keyExists($mapping[$tag], $key))
						throw new MappingException(
							'Cannot override key ' . $key . ' of ' . $tag);
					$mapping[$tag][$key] = $variable;
				}
			}

			if ($mapFunction == 'mapField')
				$this->setAutomaticMapping($mapping, $file, $property,
					$block, $defaultInstance);

			if ($mapFunction == 'mapField')
				$this->metadataMappingCache[$className][self::MAPPING_FIELDS][$name] = $mapping;
			else
				$this->metadataMappingCache[$className][self::MAPPING_ASSOCIATIONS][$name] = [
					'mapFunction' => $mapFunction,
					'mapping' => $mapping
				];

			$visited[] = $property->getName();
		} // Properties
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
		ClassMetadataAdapter::assignMetadataElement($metadata, $method,
			...$arguments);
	}

	protected function parseParameters(&$output, $text,
		ClassMetadata $metadata, $parametersProperties)
	{
		$parameters = [];
		ParameterMapSerializer::unserializeParameters($parameters, $text);

		$closureThis = (object) [
			'metadata' => $metadata
		];

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
				if ($f instanceof Closure)
					$value = $f->call($closureThis, $value, $metadata);
				else
					$value = \call_user_func($f, $value, $metadata);

			$value = TypeConversion::to($type, $value,
				[
					TypeConversion::OPTION_FALLBACK => $value
				]);

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

			if ($targetNamespace == $namespace &&
				(($this->driverFlags &
				self::ASSOCIATION_TARGET_SHORT_NAME) ==
				self::ASSOCIATION_TARGET_SHORT_NAME))
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
	 * @param array $mapping
	 *        	Property mapping
	 * @param ReflectionFile $file
	 *        	Class file
	 * @param \ReflectionProperty $property
	 *        	Class property
	 * @param array $types
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
		$reflectionClass = $this->getEventConstantsClass();
		$this->lifeCycleEventParameters = [];

		/**
		 *
		 * @var ReflectionConstant $constant
		 */
		foreach ($reflectionClass->getConstants() as $name)
		{
			$key = \constant($reflectionClass->getName() . '::' . $name);
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

	public static function getIdTagParametersDescriptor()
	{
		if (!isset(self::$idTagParametersDescriptor))
		{
			self::$idTagParametersDescriptor = \array_merge(
				self::getPropertyTagParametersDescriptor(),
				[
					'generator',
					'sequence-name',
					'sequence-allocation-size',
					'sequence-initial-value',
					'custom-id-generator-class'
				]);
		}
		return self::$idTagParametersDescriptor;
	}

	public static function getFieldTagParametersDescriptor()
	{
		if (!isset(self::$fieldTagParametersDescriptor))
		{
			self::$fieldTagParametersDescriptor = [
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
					'pre-set' => Closure::fromCallable(
						function ($v) {
							return self::getClassMetadataClassConstant(
								$this->metadata, 'GENERATED_', $v);
						})
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
		return self::$fieldTagParametersDescriptor;
	}

	public static function getAssociationTagParametersDescriptor()
	{
		if (!isset(self::$associationTagParametersDescriptor))
		{
			self::$associationTagParametersDescriptor = [
				'field' => [
					'key' => 'fieldName'
				],
				'target-class' => [
					'key' => 'targetEntity'
				],
				'target-field' => [
					'key' => 'referencedFieldName'
				],
				'target-column' => [
					'key' => 'referencedColumnName'
				],
				'fetch' => [
					'type' => 'integer',
					'pre-set' => Closure::fromCallable(
						function ($v) {
							return self::getClassMetadataClassConstant(
								$this->metadata, 'FETCH_', $v);
						})
				]

			/**
			 *
			 * @todo cascade
			 * @todo orphan-removal
			 * @todo cache
			 */
			];
		}
		return self::$associationTagParametersDescriptor;
	}

	public static function getManyToManyTagParametersDescriptor()
	{
		return self::getAssociationTagParametersDescriptor();
	}

	public static function getManyToOneTagParametersDescriptor()
	{
		if (!isset(self::$manyToOneTagParametersDescriptor))
		{
			self::$manyToOneTagParametersDescriptor = \array_merge(
				self::getAssociationTagParametersDescriptor(),
				[
					'inversed-by'
				]);
		}
		return self::$manyToOneTagParametersDescriptor;
	}

	public static function getOneToManyTagParametersDescriptor()
	{
		if (!isset(self::$oneToManyTagParametersDescriptor))
		{
			self::$oneToManyTagParametersDescriptor = \array_merge(
				self::getAssociationTagParametersDescriptor(),
				[
					'mapped-by'
				]);
		}
		return self::$oneToManyTagParametersDescriptor;
	}

	public static function getOneToOneTagParametersDescriptor()
	{
		return self::getAssociationTagParametersDescriptor();
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
	 *
	 * @var array
	 */
	private $metadataMappingCache = [];

	/**
	 *
	 * @va Inflector
	 */
	private $inflector;

	const MAPPING_FIELDS = 'fields';

	const MAPPING_ASSOCIATIONS = 'associations';

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
