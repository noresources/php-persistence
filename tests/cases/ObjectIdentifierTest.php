<?php

/**
 * Copyright © 2024 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package Persistence
 */
namespace NoreSources\Persistence\TestCase;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\Driver\XmlDriver;
use Doctrine\Persistence\Mapping\ClassMetadataFactory;
use NoreSources\Persistence\Id\ObjectIdentifier;
use NoreSources\Persistence\Mapping\GenericClassMetadataFactory;
use NoreSources\Persistence\TestData\User;

class ObjectIdentifierTest extends \PHPUnit\Framework\TestCase
{

	public function testNormalize()
	{
		$mappingDriver = new XmlDriver(
			[
				__DIR__ . '/../reference/dcm'
			]);

		$className = User::class;

		$factory = new GenericClassMetadataFactory();
		$factory->setMappingDriver($mappingDriver);
		$factory->setMetadataClass(ClassMetadata::class);
		$this->assertInstanceOf(ClassMetadataFactory::class, $factory);

		$metadata = $factory->getMetadataFor($className);

		$userId = 'alice';
		$user = new User($userId);

		$expected = [
			'id' => $userId
		];

		$actual = ObjectIdentifier::normalize($user, $metadata);
		$this->assertEquals($expected, $actual, 'From User object');

		$actual = ObjectIdentifier::normalize($userId, $metadata);
		$this->assertEquals($expected, $actual, 'From string');

		$actual = ObjectIdentifier::normalize($expected, $metadata);
		$this->assertEquals($expected, $actual, 'From exact array');

		$actual = ObjectIdentifier::normalize(
			[
				'id' => 'alice',
				'name' => 'Alice'
			], $metadata);
		$this->assertEquals($expected, $actual,
			'From array with more keys');

		$actual = ObjectIdentifier::normalize([], $metadata);
		$this->assertEquals([
			'id' => null
		], $actual, 'From empty');
	}
}
