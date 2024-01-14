<?php

/**
 * Copyright © 2023 - 2024 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package Persistence
 */
namespace NoreSources\Persistence\Event\Traits;

use Doctrine\Common\EventManager;

/**
 * Reference implementation of EventManagerAwareInterface
 */
trait EventManagerAwareTrait
{

	public function setEventManager(EventManager $evm)
	{
		$this->evm = $evm;
	}

	public function getEventManager()
	{
		return $this->evm;
	}

	/**
	 *
	 * @var EventManager
	 */
	private $evm;
}
