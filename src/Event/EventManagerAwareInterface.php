<?php

/**
 * Copyright © 2023 - 2024 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package Persistence
 */
namespace NoreSources\Persistence\Event;

use Doctrine\Common\EventManager;

/**
 * Represents an object that holds an event manager
 */
interface EventManagerAwareInterface
{

	/**
	 *
	 * @param EventManager $evm
	 *        	Event manager
	 */
	function setEventManager(EventManager $evm);
}
