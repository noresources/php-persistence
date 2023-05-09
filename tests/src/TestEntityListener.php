<?php

/**
 * Copyright © 2023 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package Persistence
 */
namespace NoreSources\Persistence\TestUtility;

use NoreSources\Container\Container;

class TestEntityListener
{

	public static $trace = [];

	public static function clear()
	{
		self::$trace = [];
	}

	public static function prePersist()
	{
		self::record('prePersist', func_get_args());
	}

	public static function preUpdate()
	{
		self::record('preUpdate', func_get_args());
	}

	public static function preRemove()
	{
		self::record('preRemove', func_get_args());
	}

	private static function record($event, $args)
	{
		$e = [
			'event' => $event,
			'arguments' => Container::map($args,
				function ($k, $v) {
					return \NoreSources\Type\TypeDescription::getName(
						$v);
				})
		];

		self::$trace[] = $e;
	}
}