<?php

/**
 * Copyright © 2024 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package Persistence
 */
namespace NoreSources\Persistence\TestCase;

use NoreSources\Persistence\Index;

class IndexTest extends \PHPUnit\Framework\TestCase
{

	public function testAPI()
	{
		$index = new Index();
		$index->append(1, 'one');
		$index->append(1, 'un');
		$index->append(2, 'two');

		$this->assertContains('one', $index->get(1), 'one in 1');
		$this->assertTrue($index->contains(1, 'un'), 'Initial contains');

		$this->assertNotContains('two', $index->get(1));
		$this->assertTrue($index->has(2));
		$this->assertFalse($index->has(3));

		$index->remove(1, 'un');
		$this->assertFalse($index->contains(1, 'un'), 'Removed "un"');
		$index->move(1, 2, 'one');
		$this->assertEquals([
			'two',
			'one'
		], $index->get(2), 'one moved to 2');
		$this->assertFalse($index->contains(1, 'one'), 'one moved to 2');
	}
}
