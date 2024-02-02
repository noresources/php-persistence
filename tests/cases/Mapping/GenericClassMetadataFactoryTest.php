<?php

/**
 * Copyright © 2024 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package Persistence
 */
namespace NoreSources\Persistence\TestCase\Mapping;

use Doctrine\ORM\ORMSetup;
use NoreSources\Persistence\Mapping\GenericClassMetadata;
use NoreSources\Persistence\Mapping\GenericClassMetadataFactory;
use NoreSources\Persistence\Mapping\Driver\ReflectionDriver;
use NoreSources\Persistence\TestData\BasicEntity;
use NoreSources\Persistence\TestData\Bug;
use NoreSources\Persistence\TestData\ChildEntity;
use NoreSources\Persistence\TestData\Product;
use NoreSources\Persistence\TestData\User;
use NoreSources\Persistence\TestData\Abstracts\ParentEntity;
use NoreSources\Persistence\TestUtility\ResultComparisonTrait;
use NoreSources\Persistence\TestUtility\TestEntityManagerFactoryTrait;
use NoreSources\Test\DerivedFileTestTrait;
use NoreSources\Type\TypeDescription;

class GenericClassMetadataFactoryTest extends \PHPUnit\Framework\TestCase
{
	use DerivedFileTestTrait;
	use TestEntityManagerFactoryTrait;
	use ResultComparisonTrait;

	public function setUp(): void
	{
		$this->setUpDerivedFileTestTrait(__DIR__ . '/../..');
	}

	public function tearDown(): void
	{
		$this->tearDownDerivedFileTestTrait();
	}

	public function testCompareAgainstORMClassMetadataFactory()
	{
		$method = __METHOD__;
		$classes = [
			User::class,
			Bug::class,
			Product::class,
			BasicEntity::class,
			ParentEntity::class,
			ChildEntity::class
		];

		$ormFactory = $this->createORMClassMetadataFactory($method,
			$classes);
		$genericFactory = $this->createGenericFactory();

		foreach ($classes as $className)
		{
			$this->compareImplementation(
				[
					'isTransient' => [
						$className
					]
				], $ormFactory, $genericFactory,
				'Metadata factory method concerning ' .
				TypeDescription::getLocalName($className, true));

			$ormMetadata = $ormFactory->getMetadataFor($className);
			$genericMetadata = $genericFactory->getMetadataFor(
				$className);

			$this->compareImplementation(
				[
					'name',
					'getFieldNames',
					'getIdentifierFieldNames',
					'getAssociationNames'
				], $ormMetadata, $genericMetadata,
				TypeDescription::getLocalName($className, true));
		}

		$className = ChildEntity::class;
		$ormChild = $ormFactory->getMetadataFor($className);
		$genericChild = $genericFactory->getMetadataFor($className);

		$this->assertTrue($ormChild->hasField('firstName'),
			'Child class has parent field');

		$this->compareImplementation(
			[
				'hasField' => [
					'id'
				],
				'isIdentifier' => [
					'id'
				],
				'getTypeOfField' => [
					'id'
				],
				'hasField' => [
					'firstName'
				],
				'hasField' => [
					'coolness'
				]
			], $ormChild, $genericChild,
			TypeDescription::getLocalName($className, true) .
			'(inheritance)');
	}

	/**
	 * Ensure default behavior of ReflectionDriver matches the Doctrine existing drivers
	 */
	public function createGenericFactory()
	{
		$reflectionDriver = new ReflectionDriver(
			[
				$this->getReferenceFileDirectory() . '/src'
			]);
		$factory = new GenericClassMetadataFactory($reflectionDriver);
		$factory->setMetadataClass(GenericClassMetadata::class);
		return $factory;
	}

	/**
	 *
	 * @param string $method
	 *        	Test method
	 * @param array $classes
	 *        	Table to initialize
	 * @return \Doctrine\ORM\Mapping\ClassMetadataFactory
	 */
	public function createORMClassMetadataFactory($method,
		$classes = array(), $initialize = true)
	{
		$method = __METHOD__;
		$isDevMode = true;
		$suffix = '';
		$extension = 'sqlite';

		$configuration = ORMSetup::createXMLMetadataConfiguration(
			[
				$this->getReferenceFileDirectory() . '/dcm'
			], $isDevMode);

		$databasePath = $this->getDerivedFilename($method, $suffix,
			$extension);
		$this->assertCreateFileDirectoryPath($databasePath,
			'Database path');
		$em = $this->createEntityManager($configuration, $databasePath,
			$classes, $initialize);
		return $em->getMetadataFactory();
	}
}
