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

	public function hasLifecycleCallbacks($event)
	{
		return \array_key_exists($event, $this->lifecycleCallbacks) &&
			\count($this->lifecycleCallbacks[$event]);
	}

	public function addLifecycleCallback($event, $method)
	{
		if (!\array_key_exists($event, $this->lifecycleCallbacks))
			$this->lifecycleCallbacks[$event] = [];
		$this->lifecycleCallbacks[$event][] = $method;
	}
}
