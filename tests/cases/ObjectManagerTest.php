<?php

/**
 * Copyright © 2023 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package Persistence
 */
namespace NoreSources\Persistence\TestCase;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\Driver\XmlDriver;
use Doctrine\Persistence\Mapping\ClassMetadataFactory;
use NoreSources\Data\Serialization\JsonSerializer;
use NoreSources\Persistence\TestData\User;
use NoreSources\Persistence\TestUtility\TestMappingDriverClassMetadataFactory;
use NoreSources\Test\DerivedFileTestTrait;

class ObjectManagerTest extends \PHPUnit\Framework\TestCase
{

	use DerivedFileTestTrait;

	public function setUp(): void
	{
		$this->setUpDerivedFileTestTrait(__DIR__ . '/..');
	}

	public function tearDown(): void
	{
		$this->tearDownDerivedFileTestTrait();
	}

	public function testTraits()
	{
		$mappingDriver = $this->createXmlDriver();
		$className = User::class;

		$unserializer = new JsonSerializer();

		$factory = new TestMappingDriverClassMetadataFactory();
		$factory->setMappingDriver($mappingDriver);
		$factory->setMetadataClass(ClassMetadata::class);
		$this->assertInstanceOf(ClassMetadataFactory::class, $factory);

		$classMetadata = $factory->getMetadataFor($className);
		$base = __DIR__ . '/../../derived';
	}

	protected function createXmlDriver()
	{
		return new XmlDriver([
			__DIR__ . '/../reference/dcm'
		]);
	}
}
