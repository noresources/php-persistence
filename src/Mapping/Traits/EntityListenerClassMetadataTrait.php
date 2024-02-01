<?php

/**
 * Copyright © 2024 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package Persistence
 */
namespace NoreSources\Persistence\Mapping\Traits;

trait EntityListenerClassMetadataTrait
{

	public function addEntityListener($event, $className, $method)
	{
		if (!isset($this->eventListeners[$event]))
			$this->eventListeners[$event] = [];
		$this->eventListeners[$event][] = [
			$className,
			$method
		];
	}

	*
	* @var array
	*/
	private
$eventListeners = [];
}
