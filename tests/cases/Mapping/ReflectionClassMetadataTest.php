<?php

/**
 * Copyright © 2023 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package Persistence
 */
namespace NoreSources\Persistence\TestCase\Mapping;

use NoreSources\Persistence\Mapping\ReflectionClassMetadata;
use DateTime;

class ReflectionClassMetadataTest extends \PHPUnit\Framework\TestCase
{

	private $reflectionClassMetadataTestId;

	/**
	 *
	 * @var NULL|\DateTime
	 */
	public $someTime;

	/**
	 *
	 * @var string[]
	 */
	private $listOfThings = [];

	/**
	 *
	 * @var integer
	 */
	protected $answer = 42;

	public function testConstruct()
	{
		$expected = ReflectionClassMetadata::class;
		$className = self::class;
		$this->assertInstanceOf($expected,
			new ReflectionClassMetadata($this), 'Construct by object');
		$this->assertInstanceOf($expected,
			new ReflectionClassMetadata($className),
			'construct by string');

		$this->expectException(\InvalidArgumentException::class,
			'Invalid constructor argument');

		new ReflectionClassMetadata(42);
	}

	public function testIdentifierFieldName()
	{
		$metadata = new ReflectionClassMetadata($this);
		$this->assertEquals('reflectionClassMetadataTestId',
			$metadata->getClassNameBasedIdentifierFieldname(),
			'Class name based identifier field name');
	}

	public function testFields()
	{
		$metadata = new ReflectionClassMetadata($this);
		$this->assertTrue($metadata->hasField('listOfThings'),
			'Has private property');
		$this->assertTrue(
			$metadata->hasField(
				$metadata->getClassNameBasedIdentifierFieldname()),
			'Id field is a field');
	}

	public function testIdentifierValue()
	{
		$metadata = new ReflectionClassMetadata($this);
		$this->reflectionClassMetadataTestId = __METHOD__;

		$id = $metadata->getIdentifierValues($this);
		$this->assertEquals(
			[
				$metadata->getClassNameBasedIdentifierFieldname() => __METHOD__
			], $id, 'Identifier values');
	}

	public function testTypeOfField()
	{
		$metadata = new ReflectionClassMetadata($this);
		foreach ([
			'someTime' => DateTime::class,
			'answer' => 'integer',
			'listOfThings' => 'array'
		] as $fieldName => $type)
		{
			$this->assertEquals($type,
				$metadata->getTypeOfField($fieldName),
				$fieldName . ' type name');
		}
	}
}
