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
use NoreSources\Container\Container;
use NoreSources\Persistence\Mapping\BasicClassMetadata;
use NoreSources\Persistence\Mapping\ClassMetadataAdapter;
use NoreSources\Persistence\Mapping\ClassMetadataReflectionPropertyMapper;
use NoreSources\Persistence\Mapping\Driver\ReflectionDriver;
use NoreSources\Persistence\TestData\BasicEntity;
use NoreSources\Persistence\TestData\Bug;
use NoreSources\Persistence\TestData\CustomIdEntity;
use NoreSources\Persistence\TestData\EmbeddedObjectProperty;
use NoreSources\Persistence\TestData\ManyToOneEntity;
use NoreSources\Persistence\TestData\Product;
use NoreSources\Persistence\TestData\User;
use NoreSources\Persistence\TestUtility\TestEntityListener;
use NoreSources\Persistence\TestUtility\TestEntityManagerFactoryTrait;
use NoreSources\Test\DerivedFileTestTrait;
use NoreSources\Type\TypeConversion;
use NoreSources\Type\TypeDescription;

class ReflectionDriverTest extends \PHPUnit\Framework\TestCase
{

	use DerivedFileTestTrait;
	use TestEntityManagerFactoryTrait;

	public function setUp(): void
	{
		$this->setUpDerivedFileTestTrait(__DIR__ . '/../../..');
	}

	public function tearDown(): void
	{
		$this->tearDownDerivedFileTestTrait();
	}

	public function testConstruct()
	{
		$driver = new ReflectionDriver([]);
		$this->assertInstanceOf(ReflectionDriver::class, $driver);
	}

	/**
	 * Compare BasicClassMetadata populated by ReflectionDriver
	 * agains Doctrine ORM ClassMetadata populated by XML driver
	 */
	public function testBasicClassMetadata()
	{
		$reflectionService = new RuntimeReflectionService();
		$className = BasicEntity::class;
		$rdriver = new ReflectionDriver(
			[
				$this->getReferenceFileDirectory() . '/src'
			]);

		$ormMeta = new ClassMetadata($className);
		$basicMeta = new BasicClassMetadata($className);
		$rdriver->loadMetadataForClass($className, $basicMeta);
		$ormMeta->wakeupReflection($reflectionService);

		$xdriver = new XmlDriver(
			[
				$this->getReferenceFileDirectory() . '/dcm/'
			], XmlDriver::DEFAULT_FILE_EXTENSION);
		$xdriver->loadMetadataForClass($className, $ormMeta);

		$this->compareClassMetadata(
			[
				'getName' => [],
				'isIdentifier' => [
					'id'
				],
				'getTypeOfField' => [
					'id'
				]
			], $ormMeta, $basicMeta, $className);

		$className = Bug::class;
		$ormMeta = new ClassMetadata($className);
		$basicMeta = new BasicClassMetadata($className);
		$rdriver->loadMetadataForClass($className, $basicMeta);
		$xdriver->loadMetadataForClass($className, $ormMeta);
		$ormMeta->wakeupReflection($reflectionService);

		$this->assertDerivedFile(
			\json_encode($ormMeta, JSON_PRETTY_PRINT), __METHOD__,
			'bug-persistent-data', 'json');

		$entity = new Bug();
		$entity->assignToProduct(new Product());
		$entity->setCreated(new \DateTime('@123456'));
		$entity->setEngineer(new User());
		$entity->setId(666);

		$this->compareClassMetadata(
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
			], $ormMeta, $basicMeta, $className);

		$targetClass = $basicMeta->getAssociationTargetClass('engineer');
		$this->assertEquals('User', $targetClass,
			'Association target class name is local');
		$this->assertEquals(User::class,
			ClassMetadataAdapter::getFullyQualifiedClassName(
				$targetClass, $basicMeta),
			ClassMetadataAdapter::class .
			'::getFullyQualifiedClassName()');
		;
	}

	public function testCustomIdEntity()
	{
		$reflectionService = new RuntimeReflectionService();
		$className = CustomIdEntity::class;
		$rdriver = new ReflectionDriver(
			[
				$this->getReferenceFileDirectory() . '/src'
			]);

		$basicMeta = new BasicClassMetadata($className);
		$rdriver->loadMetadataForClass($className, $basicMeta);

		$this->assertNotNull($basicMeta->customGeneratorDefinition,
			TypeDescription::getLocalName($basicMeta) . ' using ' .
			TypeDescription::getLocalName($rdriver) . ' ' .
			' Custom generator ');

		$xdriver = new XmlDriver(
			[
				$this->getReferenceFileDirectory() . '/dcm/'
			], XmlDriver::DEFAULT_FILE_EXTENSION);
		$ormMeta = new ClassMetadata($className);
		$ormMeta->wakeupReflection($reflectionService);
		$xdriver->loadMetadataForClass($className, $ormMeta);

		$this->assertNotNull($ormMeta->customGeneratorDefinition,
			TypeDescription::getLocalName($ormMeta) . ' using ' .
			TypeDescription::getLocalName($xdriver) . ' ' .
			' Custom generator ');
	}

	public function testEmbeddedObject()
	{
		$reflectionService = new RuntimeReflectionService();
		$className = EmbeddedObjectProperty::class;
		$rdriver = new ReflectionDriver(
			[
				$this->getReferenceFileDirectory() . '/src'
			]);

		$basicMeta = new BasicClassMetadata($className);
		$rdriver->loadMetadataForClass($className, $basicMeta);

		$xdriver = new XmlDriver(
			[
				$this->getReferenceFileDirectory() . '/dcm/'
			], XmlDriver::DEFAULT_FILE_EXTENSION);
		$ormMeta = new ClassMetadata($className);
		$ormMeta->wakeupReflection($reflectionService);
		$xdriver->loadMetadataForClass($className, $ormMeta);

		$expected = [
			'id',
			'created',
			'embeddedUser'
		];
		$actual = $basicMeta->getFieldNames();
		$this->assertEquals($expected, $actual,
			'ReflectionDriver field names');

		$expected = User::class;
		$actual = $basicMeta->getTypeOfField('embeddedUser');
		$this->assertEquals($expected, $actual,
			'ReflectionDriver type of embedded user');

		$tests = [
			'getFieldNames'
		];

		$this->compareClassMetadata($tests, $ormMeta, $basicMeta,
			$className);

		$mapper = new ClassMetadataReflectionPropertyMapper($basicMeta);
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

	private function compareClassMetadata($tests, $metaA, $metaB,
		$testName)
	{
		foreach ($tests as $method => $arguments)
		{
			if (\is_integer($method) && \is_string($arguments))
			{
				$method = $arguments;
				$arguments = [];
			}

			$label = $method . '(';
			$label .= Container::implodeValues($arguments, ', ',
				function ($a) {
					try
					{
						return TypeConversion::toString($a);
					}
					catch (\Exception $e)
					{
						return TypeDescription::getName($a);
					}
				});
			$label .= ')';

			$callableA = [
				$metaA,
				$method
			];
			$callableB = [
				$metaB,
				$method
			];

			$this->assertIsCallable($callableA,
				TypeDescription::getLocalName($metaA) . '::' . $method);
			$this->assertIsCallable($callableB,
				TypeDescription::getLocalName($metaA) . '::' . $method);

			try
			{
				$expected = \call_user_func_array($callableA, $arguments);
			}
			catch (\Exception $e)
			{
				throw new \Exception(
					TypeDescription::getLocalName($metaA) . ' | ' .
					$label . ': ' . $e->getMessage());
			}
			try
			{
				$actual = \call_user_func_array($callableB, $arguments);
			}
			catch (\Exception $e)
			{
				throw new \Exception(
					TypeDescription::getLocalName($metaB) . ' | ' .
					$label . ': ' . $e->getMessage());
			}

			if (Container::isArray($expected) &&
				Container::isArray($actual) &&
				Container::isIndexed($expected))
			{
				sort($expected);
				sort($actual);
			}

			$this->assertEquals($expected, $actual,
				$testName . ' | ' . $label);
		}
	}

	public function testCompareXMLDriver()
	{
		$className = BasicEntity::class;
		$table = [
			'name' => 'Test_BasicEntity',
			'schema' => 'Tests'
		];

		$xdriver = new XmlDriver(
			[
				$this->getReferenceFileDirectory() . '/dcm/'
			], XmlDriver::DEFAULT_FILE_EXTENSION);
		$xmeta = new ClassMetadata($className);
		$xdriver->loadMetadataForClass($className, $xmeta);
		$this->assertEquals($className, $xmeta->name);
		$this->assertEquals($table, $xmeta->table,
			'$table property (XML)');

		$rdriver = new ReflectionDriver(
			[
				$this->getReferenceFileDirectory() . '/src'
			]);
		$rmeta = new ClassMetadata($className);
		$rdriver->loadMetadataForClass($className, $rmeta);

		foreach ([
			'name',
			'table'
		] as $property)
		{
			$this->assertTrue(isset($xmeta->$property),
				$property . ' is set for metadata from XML');
			$this->assertTrue(isset($rmeta->$property),
				$property .
				' is set for metadata from ReflectionDocComment');
			$this->assertEquals($xmeta->$property, $rmeta->$property,
				$property . ' property of class metadata');

			$xcn = $xdriver->getAllClassNames();
			sort($xcn);
			$rcn = $rdriver->getAllClassNames();
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
		$driverImpl = new ReflectionDriver($paths);
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
				BasicEntity::class,
				ManyToOneEntity::class
			]);

		$bob = $em->find(BasicEntity::class, 1);
		if ($bob)
		{
			$em->remove($bob);
			$em->flush();
		}

		TestEntityListener::clear();

		$bob = new BasicEntity();

		$bob->id = 1;
		$bob->name = 'Bob';

		$this->assertNull($bob->nickname, 'Default nickname is null');

		$em->persist($bob);

		$this->assertEquals(1, $bob->id, 'Bob ID');

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

		$driverImpl = new ReflectionDriver(
			[
				$this->getReferenceFileDirectory() . '/src'
			]);
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
