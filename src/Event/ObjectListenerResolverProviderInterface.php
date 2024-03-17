<?php

/**
 * Copyright © 2023 - 2024 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package Persistence
 */
namespace NoreSources\Persistence\Event;

/**
 * Resolve object listeners
 */
interface ObjectListenerResolverProviderInterface
{

	/*
	 * @return mixed An object that resolves object listener class
	 */
	function getObjectListenerResolver();
}
