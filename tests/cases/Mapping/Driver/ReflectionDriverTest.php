<?php

/**
 * Copyright © 2023 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package Persistence
 */
namespace NoreSources\Persistence\TestCase\Mapping\Driver;

use Doctrine\ORM\Configuration;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\Driver\XmlDriver;
use Doctrine\Persistence\Mapping\RuntimeReflectionService;
use NoreSources\Persistence\Mapping\ClassMetadataAdapter;
use NoreSources\Persistence\Mapping\ClassMetadataReflectionPropertyMapper;
use NoreSources\Persistence\Mapping\GenericClassMetadata;
use NoreSources\Persistence\Mapping\Driver\ReflectionDriver;
use NoreSources\Persistence\TestData\BasicEntity;
use NoreSources\Persistence\TestData\Bug;
use NoreSources\Persistence\TestData\CollectionEntity;
use NoreSources\Persistence\TestData\CustomIdEntity;
use NoreSources\Persistence\TestData\EmbeddedObjectProperty;
use NoreSources\Persistence\TestData\Product;
use NoreSources\Persistence\TestData\User;
use NoreSources\Persistence\TestData\Abstracts\ParentEntity;
use NoreSources\Persistence\TestUtility\ResultComparisonTrait;
use NoreSources\Persistence\TestUtility\TestDBALTypeExtensionTrait;
use NoreSources\Persistence\TestUtility\TestEntityListener;
use NoreSources\Persistence\TestUtility\TestEntityManagerFactoryTrait;
use NoreSources\Test\DerivedFileTestTrait;
use NoreSources\Type\TypeDescription;

class ReflectionDriverTest extends \PHPUnit\Framework\TestCase
{

	use DerivedFileTestTrait;
	use TestEntityManagerFactoryTrait;
	use ResultComparisonTrait;
	use TestDBALTypeExtensionTrait;

	public function setUp(): void
	{
		$this->setupDBALExtensions();
		$this->setUpDerivedFileTestTrait(__DIR__ . '/../../..');
	}

	public function tearDown(): void
	{
		$this->tearDownDerivedFileTestTrait();
	}

	public function testparentEntity()
	{
		$reflectionService = new RuntimeReflectionService();
		$className = ParentEntity::class;
		$flags = 0;
		$reflectionDriver = new ReflectionDriver(
			[
				$this->getReferenceFileDirectory() . '/src'
			], $flags);

		$ormMeta = new ClassMetadata($className);
		$genericMeta = new GenericClassMetadata($className);
		$ormMeta->wakeupReflection($reflectionService);

		$reflectionDriver->loadMetadataForClass($className, $genericMeta);
		$reflectionDriver->loadMetadataForClass($className, $ormMeta);

		$localName = TypeDescription::getLocalName($className, true);
		$isMappedSuperclass = $ormMeta->isMappedSuperclass;
		$this->assertTrue($isMappedSuperclass,
			$localName . ' isMappedSuperclass (ORM ClassMetadata)');

		$this->assertTrue(
			ClassMetadataAdapter::retrieveMetadataProperty(
				$isMappedSuperclass, $genericMeta, 'isMappedSuperclass'),
			'Generic class metadata has $isMappedSuperclass');
		$this->assertTrue($isMappedSuperclass,
			$localName . ' isMappedSuperclass (Generic class metadata)');
	}

	public function testBasicEntity()
	{
		$reflectionService = new RuntimeReflectionService();
		$className = BasicEntity::class;
		$flags = ReflectionDriver::ASSOCIATION_TARGET_SHORT_NAME;
		$reflectionDriver = new ReflectionDriver(
			[
				$this->getReferenceFileDirectory() . '/src'
			], $flags);

		$ormMeta = new ClassMetadata($className);
		$genericMeta = new GenericClassMetadata($className);
		$reflectionDriver->loadMetadataForClass($className, $genericMeta);
		$ormMeta->wakeupReflection($reflectionService);

		$xmlDriver = new XmlDriver(
			[
				$this->getReferenceFileDirectory() . '/dcm/'
			], XmlDriver::DEFAULT_FILE_EXTENSION);
		$xmlDriver->loadMetadataForClass($className, $ormMeta);

		$this->compareImplementation(
			[
				'getName',
				'getFieldNames',
				'isIdentifier' => [
					'id'
				],
				'getTypeOfField' => [
					'id'
				]
			], $ormMeta, $genericMeta, $className);

		$directAccessIdGenerator = $genericMeta->idGenerator;
		$this->assertNotNull($directAccessIdGenerator,
			'ID generator (direct access)');
		$adapterIdGenerator = ClassMetadataAdapter::getIdGenerator(
			$genericMeta);
		$this->assertNotNull($adapterIdGenerator,
			'ID generator from adapter method');

		$mapping = $genericMeta->getFieldMapping('name');
		$this->assertIsArray($mapping, 'Field mapping');
		$this->assertArrayHasKey('extra', $mapping, 'Mapping extra');
		$extra = $mapping['extra'];
		$this->assertArrayHasKey('parameterCase', $extra, 'Extra extra');
		$this->assertEquals($extra['userDefined'], 'option',
			'User-defined extra property');
		//////////////////////////////////////////////////
	}

	public function testCollectionEntity()
	{
		$method = __METHOD__;
		$className = CollectionEntity::class;

		$xmlDriver = new XmlDriver(
			[
				$this->getReferenceFileDirectory() . '/dcm'
			]);

		$xmlMetadata = new ClassMetadata($className);
		$xmlDriver->loadMetadataForClass($className, $xmlMetadata);

		$json = \json_encode($xmlMetadata->getAssociationMappings(),
			JSON_PRETTY_PRINT);
		$this->assertDerivedFile($json, $method,
			'associations_from_xmldriver', 'json');

		$reflectionDriver = new ReflectionDriver(
			[
				$this->getReferenceFileDirectory() . '/src'
			]);
		$metadata = new ClassMetadata($className);
		$reflectionDriver->loadMetadataForClass($className, $metadata);

		$this->assertTrue($metadata->hasAssociation('otherEntities'),
			$className . ' has association.');

		$json = \json_encode($metadata->getAssociationMappings(),
			JSON_PRETTY_PRINT);
		$this->assertDerivedFile($json, $method,
			'associations_from_reflection', 'json');

		$isDevMode = true;
		$configuration = ORMSetup::createConfiguration($isDevMode);

		$configuration->setMetadataDriverImpl($reflectionDriver);
		//$configuration->setMetadataDriverImpl($xmlDriver);

		$suffix = '';
		$extension = 'sqlite';
		$databasePath = $this->getDerivedFilename($method, $suffix,
			$extension);
		$this->assertCreateFileDirectoryPath($databasePath,
			'Database path');
		$em = $this->createEntityManager($configuration, $databasePath,
			[
				BasicEntity::class,
				CollectionEntity::class
			]);
	}

	public function testUserAndBugs()
	{
		$reflectionService = new RuntimeReflectionService();
		$flags = ReflectionDriver::ASSOCIATION_TARGET_SHORT_NAME;
		$reflectionDriver = new ReflectionDriver(
			[
				$this->getReferenceFileDirectory() . '/src'
			], $flags);
		$xmlDriver = new XmlDriver(
			[
				$this->getReferenceFileDirectory() . '/dcm/'
			], XmlDriver::DEFAULT_FILE_EXTENSION);

		$className = User::class;

		$ormMeta = new ClassMetadata($className);
		$genericMeta = new GenericClassMetadata($className);
		$reflectionDriver->loadMetadataForClass($className, $genericMeta);
		$xmlDriver->loadMetadataForClass($className, $ormMeta);
		$ormMeta->wakeupReflection($reflectionService);

		$this->compareImplementation(
			[
				'hasAssociation' => [
					'reportedBugs'
				],
				'isAssociationInverseSide' => [
					'reportedBugs'
				],
				'getAssociationMappedByTargetField' => [
					'reportedBugs'
				],
				'getAssociationMappedByTargetField' => [
					'assignedBugs'
				],
				'isAssociationInverseSide' => [
					'assignedBugs'
				]
			], $ormMeta, $genericMeta, 'User metadata');

		$expected = $ormMeta->table;
		\ksort($expected);
		$actual = $genericMeta->table;
		\ksort($actual);

		$expected = \json_encode($expected, JSON_PRETTY_PRINT);
		$actual = \json_encode($actual, JSON_PRETTY_PRINT);

		$this->assertEquals($expected, $actual, 'Primary table');

		/////////////////////////////////////////////////////
		$className = Bug::class;

		$ormMeta = new ClassMetadata($className);
		$genericMeta = new GenericClassMetadata($className);
		$reflectionDriver->loadMetadataForClass($className, $genericMeta);
		$xmlDriver->loadMetadataForClass($className, $ormMeta);
		$ormMeta->wakeupReflection($reflectionService);

		$this->assertDerivedFile(
			\json_encode($ormMeta, JSON_PRETTY_PRINT), __METHOD__,
			'bug-persistent-data', 'json');

		$entity = new Bug();
		$entity->assignToProduct(new Product());
		$entity->setCreated(new \DateTime('@123456'));
		$entity->setEngineer(new User());
		$entity->setId(666);

		$this->compareImplementation(
			[
				'getFieldNames',
				'hasField' => [
					'created'
				],
				'hasField' => [
					'engineer'
				],
				'getIdentifierFieldNames' => [],
				'getAssociationNames',
				'hasAssociation' => [
					'created'
				],
				'hasAssociation' => [
					'engineer'
				],
				'isAssociationInverseSide' => [
					'id'
				],
				'isAssociationInverseSide' => [
					'engineer'
				],
				'getIdentifierValues' => [
					$entity
				],
				'getTypeOfField' => [
					'id'
				],
				'getTypeOfField' => [
					'description'
				],
				'getTypeOfField' => [
					'engineer'
				],
				'getAssociationTargetClass' => [
					'engineer'
				],
				'isSingleValuedAssociation' => [
					'engineer'
				]
			], $ormMeta, $genericMeta, $className);

		$targetClass = $genericMeta->getAssociationTargetClass(
			'engineer');
		$this->assertEquals('User', $targetClass,
			'Association target class name is local');
		$this->assertEquals(User::class,
			ClassMetadataAdapter::getFullyQualifiedClassName(
				$targetClass, $genericMeta),
			ClassMetadataAdapter::class .
			'::getFullyQualifiedClassName()');
		;
	}

	public function testCustomIdEntity()
	{
		$reflectionService = new RuntimeReflectionService();
		$className = CustomIdEntity::class;
		$flags = ReflectionDriver::ASSOCIATION_TARGET_SHORT_NAME;
		$reflectionDriver = new ReflectionDriver(
			[
				$this->getReferenceFileDirectory() . '/src'
			], $flags);

		$genericMeta = new GenericClassMetadata($className);
		$reflectionDriver->loadMetadataForClass($className, $genericMeta);

		$this->assertNotNull($genericMeta->customGeneratorDefinition,
			TypeDescription::getLocalName($genericMeta) . ' using ' .
			TypeDescription::getLocalName($reflectionDriver) . ' ' .
			' Custom generator ');

		$xmlDriver = new XmlDriver(
			[
				$this->getReferenceFileDirectory() . '/dcm/'
			], XmlDriver::DEFAULT_FILE_EXTENSION);
		$ormMeta = new ClassMetadata($className);
		$ormMeta->wakeupReflection($reflectionService);
		$xmlDriver->loadMetadataForClass($className, $ormMeta);

		$this->assertNotNull($ormMeta->customGeneratorDefinition,
			TypeDescription::getLocalName($ormMeta) . ' using ' .
			TypeDescription::getLocalName($xmlDriver) . ' ' .
			' Custom generator ');
	}

	public function testEmbeddedObject()
	{
		$reflectionService = new RuntimeReflectionService();
		$className = EmbeddedObjectProperty::class;
		$flags = ReflectionDriver::ASSOCIATION_TARGET_SHORT_NAME;
		$reflectionDriver = new ReflectionDriver(
			[
				$this->getReferenceFileDirectory() . '/src'
			], $flags);

		$genericMeta = new GenericClassMetadata($className);
		$reflectionDriver->loadMetadataForClass($className, $genericMeta);

		$xmlDriver = new XmlDriver(
			[
				$this->getReferenceFileDirectory() . '/dcm/'
			], XmlDriver::DEFAULT_FILE_EXTENSION);
		$ormMeta = new ClassMetadata($className);
		$ormMeta->wakeupReflection($reflectionService);
		$xmlDriver->loadMetadataForClass($className, $ormMeta);

		$expected = [
			'id',
			'created',
			'embeddedUser'
		];
		$actual = $genericMeta->getFieldNames();
		$this->assertEquals($expected, $actual,
			'ReflectionDriver field names');

		$expected = User::class;
		$actual = $genericMeta->getTypeOfField('embeddedUser');
		$this->assertEquals($expected, $actual,
			'ReflectionDriver type of embedded user');

		$tests = [
			'getFieldNames'
		];

		$this->compareImplementation($tests, $ormMeta, $genericMeta,
			$className);

		$mapper = new ClassMetadataReflectionPropertyMapper(
			$genericMeta);
		$now = new \DateTime('now');
		$nowString = $now->format(\DateTime::ISO8601);
		$user = new User(777, 'Lucky Luke');
		$data = [
			'id' => 1,
			'created' => $nowString,
			'embeddedUser' => \serialize($user)
		];

		$object = new EmbeddedObjectProperty();
		$mapper->assignObjectProperties($object, $data);

		$this->assertEquals(1, $object->id, 'Mapped id');
		$this->assertEquals($user, $object->embeddedUser,
			'Embedded user');
	}

	public function testCompareXMLDriver()
	{
		$className = BasicEntity::class;
		$table = [
			'name' => 'BasicEntities',
			'schema' => 'Tests'
		];

		$xmlDriver = new XmlDriver(
			[
				$this->getReferenceFileDirectory() . '/dcm/'
			], XmlDriver::DEFAULT_FILE_EXTENSION);
		$metadataFromXml = new ClassMetadata($className);
		$xmlDriver->loadMetadataForClass($className, $metadataFromXml);
		$this->assertEquals($className, $metadataFromXml->name);
		$this->assertEquals($table, $metadataFromXml->table,
			'$table property (XML)');

		$flags = ReflectionDriver::ASSOCIATION_TARGET_SHORT_NAME;
		$reflectionDriver = new ReflectionDriver(
			[
				$this->getReferenceFileDirectory() . '/src'
			], $flags);
		$metadataFromReflection = new ClassMetadata($className);
		$reflectionDriver->loadMetadataForClass($className,
			$metadataFromReflection);

		foreach ([
			'name',
			'table'
		] as $property)
		{
			$this->assertTrue(isset($metadataFromXml->$property),
				$property . ' is set for metadata from XML');
			$this->assertTrue(isset($metadataFromReflection->$property),
				$property .
				' is set for metadata from ReflectionDocComment');
			$this->assertEquals($metadataFromXml->$property,
				$metadataFromReflection->$property,
				$property . ' property of class metadata');

			$xcn = $xmlDriver->getAllClassNames();
			sort($xcn);
			$rcn = $reflectionDriver->getAllClassNames();
			sort($rcn);

			$this->assertEquals($xcn, $rcn, 'Class names');
		}
	}

	public function testFeatureClasses()
	{
		$method = __METHOD__;
		$suffix = null;
		$extension = 'sqlite';
		$paths = [
			$this->getReferenceFileDirectory() . '/src'
		];
		$flags = ReflectionDriver::ASSOCIATION_TARGET_SHORT_NAME;
		$driverImpl = new ReflectionDriver($paths, $flags);
		$isDevMode = true;
		$configuration = ORMSetup::createConfiguration($isDevMode);
		$configuration->setMetadataDriverImpl($driverImpl);

		$this->assertInstanceOf(Configuration::class, $configuration);
		$databasePath = $this->getDerivedFilename($method, $suffix,
			$extension);
		$this->assertCreateFileDirectoryPath($databasePath,
			'Database path');
		$em = $this->createEntityManager($configuration, $databasePath,
			[
				BasicEntity::class
			]);

		$bob = $em->find(BasicEntity::class, 1);
		if ($bob)
		{
			$em->remove($bob);
			$em->flush();
		}

		TestEntityListener::clear();

		$bob = new BasicEntity();

		$bob->basicId = 1;
		$bob->name = 'Bob';

		$this->assertNull($bob->nickname, 'Default nickname is null');

		$em->persist($bob);

		$this->assertEquals(1, $bob->basicId, 'Bob ID');

		if (false) // TODO event listeners
		{
			$this->assertEquals('Bob', $bob->nickname,
				'Pre-persist task sets nickname');
			$em->flush();
		}
		else
		{
			$bob->setSecret(new \DateTime('now'));
		}

		$bob->nickname = 'Bobby';
		$em->persist($bob);
		try
		{
			$em->flush();
		}
		catch (\Exception $e)
		{
			$this->assertFalse(true, $e->getMessage());
		}

		$em->remove($bob);
		$em->flush();
	}

	public function testBugTrackerMapping()
	{
		$method = __METHOD__;
		$suffix = null;
		$extension = 'sqlite';

		$flags = ReflectionDriver::ASSOCIATION_TARGET_SHORT_NAME;
		$driverImpl = new ReflectionDriver(
			[
				$this->getReferenceFileDirectory() . '/src'
			], $flags);
		$isDevMode = true;
		$configuration = ORMSetup::createConfiguration($isDevMode);
		$configuration->setMetadataDriverImpl($driverImpl);

		$this->assertInstanceOf(Configuration::class, $configuration);
		$databasePath = $this->getDerivedFilename($method, $suffix,
			$extension);
		$this->assertCreateFileDirectoryPath($databasePath,
			'Database path');
		$em = $this->createEntityManager($configuration, $databasePath,
			[
				User::class,
				Bug::class,
				Product::class
			]);

		$e = new Product();
		$em->persist($e);

		$this->assertEquals('NULL', \gettype($e->getId()),
			'Product ID not set after persist');

		// Update
		$e->setName('Almighty software');
		$em->persist($e);
		$em->flush();
		$this->assertEquals('integer', \gettype($e->getId()),
			'Product ID set after flush');
	}
}
