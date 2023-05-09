<?php

/**
 * Copyright © 2023 - 2024 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package Persistence
 */
namespace NoreSources\Persistence\Event;

/**
 * For class that provide object inokers.
 */
interface ListenerInvokerProviderInterface
{

	/**
	 *
	 * @return ListenerInvoker
	 */
	function getListenerInvoker();
}
