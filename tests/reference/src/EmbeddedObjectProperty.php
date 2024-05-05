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
 * @persistent-object
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
	 * @persistent-property
	 * @var \DateTimeInterface
	 */
	public $created;

	/**
	 *
	 * @persistent-property
	 * @var User
	 */
	public $embeddedUser;
}
