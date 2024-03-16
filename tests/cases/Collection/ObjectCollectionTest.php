<?php

/**
 * Copyright © 2024 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package Persistence
 */
namespace NoreSources\Persistence\TestCase\Collection;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Persistence\Mapping\RuntimeReflectionService;
use NoreSources\Container\Container;
use NoreSources\Persistence\Collection\ObjectCollection;
use NoreSources\Persistence\Mapping\GenericClassMetadata;
use NoreSources\Persistence\Mapping\Driver\ReflectionDriver;
use NoreSources\Persistence\TestData\Hierarchy;
use NoreSources\Test\DerivedFileTestTrait;

class ObjectCollectionTest extends \PHPUnit\Framework\TestCase
{

	use DerivedFileTestTrait;

	public function setUp(): void
	{
		$this->setUpDerivedFileTestTrait(__DIR__ . '/../..');
	}

	public function tearDown(): void
	{
		$this->tearDownDerivedFileTestTrait();
	}

	public function testIdentifiers()
	{
		$method = __METHOD__;
		$extension = 'json';
		$suffix = '';
		$metadata = $this->createMetadata();
		$collection = $this->createHierarchyCollection();
		$collection = new ObjectCollection($collection, $metadata);
		$ids = $collection->getIdentifiers();
		$ids = Container::implodeValues($ids, "\n",
			[
				Container::class,
				'firstValue'
			]);
		$this->assertDerivedFile($ids, $method, $suffix, $extension);
	}

	public function testDictionary()
	{
		$method = __METHOD__;
		$extension = 'json';
		$suffix = '';
		$metadata = $this->createMetadata();
		$collection = $this->createHierarchyCollection();
		$collection = new ObjectCollection($collection, $metadata);

		$dictionary = $collection->getDictionary();
		if (\extension_loaded('json'))
		{
			$data = \json_encode($dictionary, JSON_PRETTY_PRINT);
			$this->assertDerivedFile($data, $method, $suffix, $extension);
		}
		else
		{
			$data = \array_keys($dictionary);
			$this->assertDerivedFile($data, $method, $suffix . '-keys',
				'txt');
		}
	}

	public function testTree()
	{
		$method = __METHOD__;
		$extension = 'json';
		$suffix = '';
		$metadata = $this->createMetadata();
		$collection = $this->createHierarchyCollection();
		$collection = new ObjectCollection($collection, $metadata);
		$tree = $collection->getHierarchyFromParentAssociation('parent');
		$this->assertIsArray($tree);
		if (\extension_loaded('json'))
		{
			$data = \json_encode($tree, JSON_PRETTY_PRINT);
			$this->assertDerivedFile($data, $method, $suffix, $extension);
		}
	}

	private function createMetadata($className = Hierarchy::class)
	{
		$reflectionService = new RuntimeReflectionService();
		$flags = 0;
		$reflectionDriver = new ReflectionDriver(
			[
				$this->getReferenceFileDirectory() . '/src'
			], $flags);
		$metadata = new GenericClassMetadata($className);
		$metadata->initializeReflection($reflectionService);
		$reflectionDriver->loadMetadataForClass($className, $metadata);
		return $metadata;
	}

	private function createHierarchyCollection()
	{
		$a = new Hierarchy('a');
		$b = new Hierarchy('b');
		$a2 = new Hierarchy('a.2', $a);

		return new ArrayCollection(
			[
				$a,
				$b,
				new Hierarchy('a.1', $a),
				new Hierarchy('b.1', $b),
				new Hierarchy('b.2', $b),
				new Hierarchy('a.2.1', $a2),
				$a2
			]);
	}
}
