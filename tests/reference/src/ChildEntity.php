<?php

/**
 * Copyright © 2024 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package Persistence
 */
namespace NoreSources\Persistence\TestData;

use NoreSources\Persistence\TestData\Abstracts\ParentEntity;

/**
 *
 * @persistent-object
 *
 */
class ChildEntity extends ParentEntity
{

	/**
	 *
	 * @persistent-id
	 * @var integer
	 */
	public $id;

	public function getKind()
	{
		return $this->kind;
	}

	/**
	 *
	 * @persistent-field
	 * @var string
	 */
	private $kind;
}
