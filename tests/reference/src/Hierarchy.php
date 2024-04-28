<?php

/**
 * Copyright © 2024 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package Persistence
 */
namespace NoreSources\Persistence\TestData;

/**
 *
 * @persistent-entity
 */
class Hierarchy implements \JsonSerializable
{

	/**
	 *
	 * @persistent-id
	 * @var string
	 */
	public $id = 'none';

	/**
	 *
	 * @persistent-many-to-one
	 * @var Hierarchy
	 */
	public $parent;

	public function __construct($id, Hierarchy $parent = null)
	{
		$this->id = $id;
		$this->parent = $parent;
	}

	#[\ReturnTypeWillChange]
	public function jsonSerialize()
	{
		return [
			'id' => $this->id,
			'parent' => ($this->parent ? $this->parent->id : null)
		];
	}
}
