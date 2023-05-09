<?php

/**
 * Copyright © 2024 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package Persistence
 */
namespace NoreSources\Persistence\Traits;

use Doctrine\Persistence\ObjectManager;

/**
 * Implements ObjectManagerProviderInterface and ObjectManagerAwareInterface
 */
trait ObjectManagerReferenceTrait
{

	/**
	 *
	 * @return ObjectManager
	 */
	public function getObjectManager()
	{
		return $this->objectManager;
	}

	/**
	 *
	 * @param ObjectManager $objectManager
	 */
	public function setObjectManager(ObjectManager $objectManager)
	{
		$this->objectManager = $objectManager;
	}

	/**
	 *
	 * @var ObjectManager
	 */
	private $objectManager = null;
}
