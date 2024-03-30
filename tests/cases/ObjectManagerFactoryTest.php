<?php

/**
 * Copyright © 2024 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package Persistence
 */
namespace NoreSources\Persistence\TestCase;

use NoreSources\Container\KeyNotFoundException;
use NoreSources\Persistence\ObjectManagerFactoryRegistry;

class ObjectManagerFactoryTest extends \PHPUnit\Framework\TestCase
{

	public function testForNothing()
	{
		$registry = new ObjectManagerFactoryRegistry();
		$this->assertFalse($registry->has('my-id'), 'Not exists');
		$this->expectException(KeyNotFoundException::class,
			'Exception on invalid get');
		$registry->createObjectManager('foo', [
			'bar' => 'baz'
		]);
	}
}
