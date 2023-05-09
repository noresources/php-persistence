<?php

/**
 * Copyright © 2023 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package Persistence
 */
namespace NoreSources\Persistence\TestUtility;

use Doctrine\Persistence\ObjectManager;
use NoreSources\Persistence\Traits\ObjectManagerTrait;

class TestObjectManager implements ObjectManager
{
	use ObjectManagerTrait;
}
