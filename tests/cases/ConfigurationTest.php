<?php

/**
 * Copyright © 2023 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package Persistence
 */
namespace NoreSources\Persistence\TestCase;

use Doctrine\Common\EventManager;
use Doctrine\Persistence\Mapping\ClassMetadataFactory;
use NoreSources\Persistence\Configuration;
use NoreSources\Persistence\ObjectListenerResolver;
use NoreSources\Persistence\Mapping\Driver\ReflectionDriver;

class ConfigurationTest extends \PHPUnit\Framework\TestCase
{

	public function testDefaults()
	{
		$configuration = new Configuration();
		foreach ([
			'getEventManager' => EventManager::class,
			'getCache' => null,
			'getObjectListenerResolver' => ObjectListenerResolver::class,
			'getMappingDriver' => null,
			'getMetadataFactory' => null
		] as $method => $expected)
		{
			$actual = \call_user_func([
				$configuration,
				$method
			]);
			if (\class_exists($expected))
				$this->assertInstanceOf($expected, $actual, $method);
			else
				$this->assertEquals($expected, $actual, $method);
		}

		$driver = new ReflectionDriver([]);
		$configuration->setMappingDriver($driver);
		$actual = $configuration->getMetadataFactory();
		$this->assertInstanceOf(ClassMetadataFactory::class, $actual,
			'Auto-create class metadata factory when mapping driver is set');
	}
}
