<?php

/**
 * Copyright © 2023 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package Persistence
 */
namespace NoreSources\Persistence\TestData;

/**
 *
 * @persistent-entity schema=Tests
 *
 */
class ManyToOneEntity
{

	/**
	 * First entity
	 *
	 * @persistent-id
	 * @persistent-many-to-one mapped-by=id
	 * @var BasicEntity
	 */
	public $firstEntity;

	/**
	 * Sedond entity
	 *
	 * @persistent-id
	 * @persistent-many-to-one mapped-by=id
	 *
	 * @var BasicEntity
	 */
	public $secondEntity;

	/**
	 * The kind of relationship between first and second entity.
	 *
	 * @persistent-field type=string; length=32; nullable=true
	 * @var string
	 */
	public $relationType;
}
