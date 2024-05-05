<?php

/**
 * Copyright © 2024 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package Persistence
 */
namespace NoreSources\Persistence\Mapping\Traits;

trait DBALClassMetadataTrait
{

	/**
	 *
	 * @property-read $table
	 * @var array
	 */
	public $table;

	public function setPrimaryTable($table)
	{
		if (!isset($table['name']))
			throw new \InvalidArgumentException(
				'Missing name attribute');

		if (!isset($table['schema']))
		{
			if (\strpos($table['name'], '.') > 0)
			{
				$x = \explode($table['name']);
				$table['schema'] = $x[0];
				$table['name'] = $x[1];
			}
		}

		if (\substr($table['name'], 0, 1) == '`')
		{
			$table['name'] = \trim($table['name'], '`');
			$table['quoted'] = true;
		}

		$this->table = $table;
	}
}
