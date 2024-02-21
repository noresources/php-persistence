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
 *
 */
class CollectionEntity
{

	/**
	 *
	 * @persistent-id column=collection_id
	 * @var string
	 */
	public $key;

	/**
	 *
	 * @persistent-many-to-one
	 * @var BasicEntity
	 */
	public $mainEntity;

	/**
	 *
	 * @persistent-many-to-many
	 * @var BasicEntity[]
	 */
	public $otherEntities = [];
}
