<?php

/**
 * Copyright © 2024 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package Persistence
 */
namespace NoreSources\Persistence\TestCase\Mapping;

use NoreSources\Persistence\TestUtility\ResultComparisonTrait;

class ReflectionServiceTest extends \PHPUnit\Framework\TestCase
{
	use ResultComparisonTrait;

	public function testCompareDoctrinePersistenceReflectionService()
	{
		$doctrine = new \Doctrine\Persistence\Mapping\RuntimeReflectionService();
		$noresources = \NoreSources\Persistence\Mapping\ReflectionService::getInstance();

		$this->compareImplementation(
			[
				'getParentClasses' => [
					__CLASS__
				],
				[
					'hasPublicMethod',
					__CLASS__,
					'foo'
				],
				[
					'hasPublicMethod',
					__CLASS__,
					'aPrivateMethod'
				],
				[
					'hasPublicMethod',
					__CLASS__,
					'testCompareDoctrinePersistenceReflectionService'
				]
			], $doctrine, $noresources,
			'ReflectionService implementation');

		$a = $doctrine->getAccessibleProperty(__CLASS__, 'aTestProperty');
		$b = $noresources->getAccessibleProperty(__CLASS__,
			'aTestProperty');

		$this->compareImplementation([
			'getValue' => [
				$this
			]
		], $a, $b, 'Accessible property value');
	}

	private $aTestProperty = 'Hello';

	private function aPrivateMethod()
	{}
}
