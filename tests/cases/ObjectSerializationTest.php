<?php

/**
 * Copyright © 2023 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package Persistence
 */
namespace NoreSources\Persistence\TestCase;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Instantiator\Instantiator;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\Driver\XmlDriver;
use NoreSources\Persistence\Mapping\ClassMetadataReflectionPropertyMapper;
use NoreSources\Persistence\TestData\Bug;
use NoreSources\Persistence\TestData\User;
use NoreSources\Text\Text;

class ObjectSerializationTest extends \PHPUnit\Framework\TestCase
{

	public function testUser()
	{
		$driver = $this->createXmlDriver();
		$this->assertContains(User::class, $driver->getAllClassNames(),
			'Has User class');

		$bugs = [
			new Bug(),
			new Bug(),
			new Bug(),
			new Bug()
		];

		foreach ($bugs as $i => $bug)
			$bug->setDescription('Bug #' . ($i + 1));

		$data = [
			'id' => 42,
			'name' => 'Deep Thought',
			'reportedBugs' => new ArrayCollection(
				[
					$bugs[0],
					$bugs[1],
					$bugs[2]
				]),
			'assignedBugs' => new ArrayCollection(
				[
					$bugs[1],
					$bugs[2],
					$bugs[3]
				])
		];

		$className = $entityName = User::class;
		$metadata = new ClassMetadata($entityName);
		$driver->loadMetadataForClass($className, $metadata);
		$propertyMapper = new ClassMetadataReflectionPropertyMapper(
			$metadata);

		$instantiator = new Instantiator();
		foreach ([
			'constructed' => new User(),
			'instantiated' => $instantiator->instantiate(User::class)
		] as $mode => $object)
		{
			/**
			 *
			 * @var User $object
			 */

			$propertyMapper->assignObjectProperties($object, $data);
			foreach ($data as $k => $v)
			{
				$actual = null;
				$p = new \ReflectionProperty(User::class, $k);
				$using = 'N/A';
				if ($p->isPublic())
				{
					$using = 'property';
					$actual = $object->$k;
				}
				elseif (($m = 'get' . Text::toPascalCase($k)) &&
					\method_exists($object, $m))

				{
					$using = 'getter';
					$actual = $actual = \call_user_func([
						$object,
						$m
					]);
				}
				else
				{
					$using = 'reflection';
					$p->setAccessible(true);
					$actual = $p->getValue($object);
				}

				$this->assertEquals($v, $actual, '$' . $mode . '->' . $k);
			}
		}
	}

	protected function createXmlDriver()
	{
		return new XmlDriver([
			__DIR__ . '/../reference/dcm'
		]);
	}
}
