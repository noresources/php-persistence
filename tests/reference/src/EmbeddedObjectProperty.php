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
class EmbeddedObjectProperty
{

	/**
	 *
	 * @persistent-id
	 * @var integer
	 */
	public $id;

	/**
	 *
	 * @persistent-field
	 * @var \DateTimeInterface
	 */
	public $created;

	/**
	 *
	 * @persistent-field
	 * @var User
	 */
	public $embeddedUser;
}
