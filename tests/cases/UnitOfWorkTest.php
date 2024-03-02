<?php

/**
 * Copyright © 2023 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package Persistence
 */
use NoreSources\Persistence\UnitOfWork;

class UnitOfWorkTest extends \PHPUnit\Framework\TestCase
{

	public function testPersist()
	{
		$a = new ArrayObject([
			'foo' => 'bar'
		]);
		$b = new \ArrayObject([
			'pia' => 'poil'
		]);
		$unitOfWork = new UnitOfWork();
		$this->assertFalse($unitOfWork->contains($a),
			'Not contains $a before persist()');
		$unitOfWork->insert($a);
		$this->assertTrue($unitOfWork->contains($a),
			'Contains $a after persist($a)');
		$this->assertFalse($unitOfWork->contains($b),
			'Not contains $b after persist($a)');
	}

	public function testDetach()
	{
		$a = new ArrayObject();
		$b = new ArrayObject();
		$unitOfWork = new UnitOfWork();
		$unitOfWork->insert($a);
		$this->assertTrue($unitOfWork->contains($a), 'Persist');
		$unitOfWork->detach($a);
		$this->assertFalse($unitOfWork->contains($a), 'Detached');
		$unitOfWork->insert($a);
		$unitOfWork->detach($a);
	}

	public function testClear()
	{
		$a = new ArrayObject();
		$b = new ArrayObject();
		$c = new ArrayObject();
		$unitOfWork = new UnitOfWork();
		$unitOfWork->insert($a);
		$unitOfWork->insert($b);
		$unitOfWork->insert($c);
		$this->assertTrue($unitOfWork->contains($a));
		$this->assertTrue($unitOfWork->contains($b));
		$this->assertTrue($unitOfWork->contains($c));
		$unitOfWork->clear(true);
		$this->assertFalse($unitOfWork->contains($a));
		$this->assertFalse($unitOfWork->contains($b));
		$this->assertFalse($unitOfWork->contains($c));
	}

	public function testConflict()
	{
		$a = new ArrayObject();
		$unitOfWOrk = new UnitOfWork();
		$unitOfWOrk->update($a);
		$this->expectException(\InvalidArgumentException::class);
		$unitOfWOrk->remove($a);
	}
}
