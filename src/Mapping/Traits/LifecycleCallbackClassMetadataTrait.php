<?php

/**
 * Copyright © 2024 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package Persistence
 */
namespace NoreSources\Persistence\Mapping\Traits;

trait LifecycleCallbackClassMetadataTrait
{

	/**
	 *
	 * @var array Array of array of method names
	 */
	public $lifecycleCallbacks = [];

	/**
	 * Indicates if object has a lifecycle callback for the given event name
	 *
	 * @param string $event
	 *        	Event name
	 * @return boolean
	 */
	public function hasLifecycleCallbacks($event)
	{
		return \array_key_exists($event, $this->lifecycleCallbacks) &&
			\count($this->lifecycleCallbacks[$event]);
	}

	/**
	 * Set the object lifecycle callback for ghe given event.
	 *
	 * @param string $event
	 *        	Event name
	 * @param string $method
	 *        	Method name
	 */
	public function addLifecycleCallback($event, $method)
	{
		if (!\array_key_exists($event, $this->lifecycleCallbacks))
			$this->lifecycleCallbacks[$event] = [];
		$this->lifecycleCallbacks[$event][] = $method;
	}
}
