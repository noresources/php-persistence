<?php

/**
 * Copyright © 2024 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package Persistence
 */
namespace NoreSources\Persistence\TestCase\Mapping;

use NoreSources\Persistence\Mapping\GenericClassMetadata;

class GenericClassMetadataTest extends \PHPUnit\Framework\TestCase
{

	public $fieldName = 'Generic';

	/**
	 *
	 * @var integer
	 */
	private $idField;

	public function testGet()
	{
		$foo = new GenericClassMetadata('Foo');
		$this->assertEquals('Foo', $foo->name,
			'Class::$name magic value');
		$this->expectException(\InvalidArgumentException::class);
		$v = $foo->anything;
	}

	public function testSet()
	{
		$foo = new GenericClassMetadata('Foo');
		$foo->isMappedSuperClass = true;
		$this->assertTrue($foo->isMappedSuperClass,
			'Set magic method add key to ArrayObject');
	}

	public function testIdentifierValues()
	{
		$foo = new GenericClassMetadata(self::class);
		$foo->mapField(
			[
				'fieldName' => 'idField',
				'id' => true,
				'type' => 'integer'
			]);
		$foo->mapField([
			'fieldName' => 'fIeldName'
		]);

		$this->assertTrue($foo->hasField('idField'), 'Has IdField');
		$this->assertTrue($foo->isIdentifier('idField'),
			'idField is identifier');

		$this->assertEquals([
			'idField' => null
		], $foo->getIdentifierValues($this), 'Initial ID field');

		$foo->setIdentifierValues($this, [
			'idField' => '!D'
		]);
		$this->assertEquals([
			'idField' => '!D'
		], $foo->getIdentifierValues($this));
	}
}
