<?php

/**
 * Copyright © 2023 - 2024 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package Persistence
 */
namespace NoreSources\Persistence\Event;

use Doctrine\Common\EventArgs;
use Doctrine\Common\EventManager;
use Doctrine\Persistence\Mapping\ClassMetadata;
use NoreSources\Container\Container;
use Psr\Container\ContainerInterface;
use ArrayAccess;

/**
 * Invoke object life cycle callback, object listeners end event listeners
 *
 * See also Doctrin ORM ListenerInvoker
 */
class ListenerInvoker
{

	public const INVOKE_NONE = 0x00;

	public const INVOKE_LISTENERS = 0x01;

	public const INVOKE_CALLBACKS = 0x02;

	public const INVOKE_MANAGER = 0x04;

	public const INVOKE_ALL = 0x07;

	/**
	 *
	 * @param EventManager $evm
	 *        	Event manager
	 * @param ContainerInterface|ArrayAccess|array $listenerResolver
	 *        	A listener resolver that provide a class instance from a class name.
	 */
	public function __construct(?EventManager $evm = null,
		$listenerResolver = null)

	{
		$this->eventManager = $evm;
		$this->listenerResolver = $listenerResolver;
	}

	public function getEventManager()
	{
		return $this->eventManager;
	}

	/**
	 *
	 * @param string $eventName
	 *        	Event name
	 * @param integer $invoke
	 *        	Type of invokers
	 * @return boolean
	 */
	public function hasListenerFor(ClassMetadata $metadata, $eventName,
		$invoke = self::INVOKE_ALL)
	{
		if ($invoke & self::INVOKE_CALLBACKS &&
			($lifecycleCallbacks = Container::keyValue($metadata,
				'lifecycleCallbacks')) &&
			($lifecycleCallbacks = Container::keyValue(
				$lifecycleCallbacks, $eventName)))
		{

			if (\is_string($lifecycleCallbacks))
				return true;
			elseif (\count($lifecycleCallbacks))
				return true;
		}

		if ($invoke & self::INVOKE_LISTENERS &&
			(($objectListeners = Container::keyValue($metadata,
				'entityListeners') ||
			($objectListeners = Container::keyValue($metadata,
				'objectListeners')))) &&
			($objectListeners = Container::keyValue($objectListeners,
				$eventName)))
		{
			if (\count($objectListeners))
				return true;
		}

		return ($invoke & self::INVOKE_MANAGER && $this->eventManager) &&
			$this->eventManager->hasListeners($eventName);
	}

	/**
	 * Dispatches the lifecycle event of the given object.
	 *
	 * @param ClassMetadata $metadata
	 *        	The object metadata.
	 * @param string $eventName
	 *        	The object lifecycle event.
	 * @param object $object
	 *        	The Object on which the event occurred.
	 * @param EventArgs $event
	 *        	The Event args.
	 * @param int $invoke
	 *        	Bitmask to invoke listeners.
	 * @psalm-param int-mask-of<self::INVOKE_*> $invoke
	 *
	 * @return void
	 */
	public function invoke(ClassMetadata $metadata, $eventName, $object,
		EventArgs $event, $invoke = self::INVOKE_ALL)
	{
		if ($invoke & self::INVOKE_CALLBACKS &&
			($lifecycleCallbacks = Container::keyValue($metadata,
				'lifecycleCallbacks')) &&
			($lifecycleCallbacks = Container::keyValue(
				$lifecycleCallbacks, $eventName)))
		{

			if (\is_string($lifecycleCallbacks))
				\call_user_func([
					$object,
					$lifecycleCallbacks
				], $event);
			else
				foreach ($lifecycleCallbacks as $callback)
				{
					\call_user_func([
						$object,
						$callback
					], $event);
				}
		}

		if ($invoke & self::INVOKE_LISTENERS &&
			(($objectListeners = Container::keyValue($metadata,
				'entityListeners') ||
			($objectListeners = Container::keyValue($metadata,
				'objectListeners')))) &&
			($objectListeners = Container::keyValue($objectListeners,
				$eventName)))
		{
			foreach ($objectListeners as $listener)
			{
				$class = $listener['class'];
				$method = $listener['method'];

				if (isset($this->listenerResolver) &&
					($instance = Container::keyValue(
						$this->listenerResolver, $class)))
					call_user_func([
						$instance,
						$method
					], $event);
				else
					call_user_func([
						$class,
						$method
					], $event);
			}
		}

		if ($invoke & self::INVOKE_MANAGER && $this->eventManager)
		{
			$this->eventManager->dispatchEvent($eventName, $event);
		}
	}

	/**
	 * The EventManager used for dispatching events.
	 *
	 * @var EventManager
	 */
	private $eventManager;

	/**
	 *
	 * @var ContainerInterface|ArrayAccess|array
	 */
	private $listenerResolver;
}
