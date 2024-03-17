<?php

/**
 * Copyright © 2023 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package Persistence
 */
namespace NoreSources\Persistence\TestCase;

use Doctrine\Common\Collections\Criteria;
use NoreSources\Persistence\Sorting\ObjectSorterInterface;

class DoctrineCollectionAPICompatibilityTest extends \PHPUnit\Framework\TestCase
{

	public function testOrderingConstants()
	{
		foreach ([
			ObjectSorterInterface::ASC => Criteria::ASC,
			ObjectSorterInterface::DESC => Criteria::DESC
		] as $actual => $expected)
		{
			$this->assertEquals($expected, $actual);
		}
	}
}
