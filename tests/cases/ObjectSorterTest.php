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
use NoreSources\Persistence\ClosureExpressionVisitorObjectSorter;
use NoreSources\Persistence\DefaultObjectSorter;
use NoreSources\Persistence\ObjectSorterInterface;
use NoreSources\Persistence\TestData\User;
use NoreSources\Type\TypeDescription;
use ArrayObject;

class ObjectSorterTest extends \PHPUnit\Framework\TestCase
{

	public function testDefault()
	{
		$driver = $this->createXmlDriver();
		$this->assertContains(User::class, $driver->getAllClassNames(),
			'Has User class');

		$collection = new ArrayObject(
			[
				($john = new User(0, 'John')),
				($syd = new User(1, 'Syd')),
				($bud = new User(1, 'Bud')),
				($alice = new User(2, 'Alice')),
				($bob = new User(3, 'Bob'))
			]);

		$reference = $collection->getArrayCopy();
		$this->assertEquals($this->downmixUser($syd),
			$this->downmixUser($reference[1]));

		$this->assertEquals(
			$this->downmixUsers($collection->getArrayCopy()),
			$this->downmixUsers($reference), 'getArrayCopy');

		$className = $entityName = User::class;
		$metadata = new ClassMetadata($entityName);
		$driver->loadMetadataForClass($className, $metadata);

		$tests = [
			'no sort' => [
				'orderBy' => [],
				'expected' => $reference
			],
			'by id' => [
				'orderBy' => [
					'id' => ObjectSorterInterface::ASC
				],
				'expected' => $reference
			],
			'by id desc' => [
				'orderBy' => [
					'id' => 'DESC'
				],
				'expected' => \array_reverse($reference),
				'expected-alt' => [
					$bob,
					$alice,
					$syd,
					$bud,
					$john
				]
			],
			'by name' => [
				'orderBy' => [
					'name' => 'anything but DESC'
				],
				'expected' => [
					$alice,
					$bob,
					$bud,
					$john,
					$syd
				]
			],
			'by id and name' => [
				'orderBy' => [
					'id' => 'ASC',
					'name' => 'ASC'
				],
				'expected' => [
					$john,
					$bud,
					$syd,
					$alice,
					$bob
				]
			]
		];

		$sorters = [
			new DefaultObjectSorter($metadata),
			new ClosureExpressionVisitorObjectSorter()
		];

		foreach ($sorters as $sorter)
		{
			$name = TypeDescription::getLocalName($sorter);
			foreach ($tests as $label => $test)
			{
				$actual = $collection->getArrayCopy();
				$sorter->sortObjects($actual, $test['orderBy']);

				$actual = $this->downmixUsers($actual);
				$expected = $this->downmixUsers($test['expected']);

				try
				{
					$this->assertEquals($expected, $actual,
						$name . ' ' . $label);
				}
				catch (\Exception $e)
				{
					$expected = $this->downmixUsers(
						$test['expected-alt']);
					$this->assertEquals($expected, $actual,
						$name . ' ' . $label . ' (Alternative)');
				}
			}
		}
	}

	protected function downmixUsers($users)
	{
		return \implode(', ',
			\array_map([
				$this,
				'downmixUser'
			], $users));
	}

	protected function downmixUser(User $u)
	{
		return \sprintf("%02d %s", $u->getId(), $u->getName());
	}

	protected function createXmlDriver()
	{
		return new XmlDriver([
			__DIR__ . '/../reference/dcm'
		]);
	}
}
