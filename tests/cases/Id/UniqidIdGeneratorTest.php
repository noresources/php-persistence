<?php

/**
 * Copyright © 2023 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package Persistence
 */
namespace NoreSources\Persistence\TestCase\Id;

use NoreSources\Persistence\Id\UniqidIdGenerator;
use NoreSources\Persistence\TestUtility\TestObjectManager;

class UniqidIdGeneratorTest extends \PHPUnit\Framework\TestCase
{

	public function testReturnType()
	{
		$o = new \ArrayObject();
		$em = new TestObjectManager();
		$g = new UniqidIdGenerator();
		$id = $g->generate($em, $o);
		$this->assertIsString($id, 'Generated ID is a string');
	}
}
