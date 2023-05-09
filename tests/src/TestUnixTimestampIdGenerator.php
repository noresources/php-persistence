<?php

/**
 * Copyright © 2023 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package Persistence
 */
namespace NoreSources\Persistence\TestUtility;

use Doctrine\Persistence\ObjectManager;

class TestUnixTimestampIdGenerator
{

	public function generate(ObjectManager $manager, $object)
	{
		return \time();
	}
}
