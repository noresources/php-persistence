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
 * @persistent-entity table=Test_CustomIdEntity; schema=Tests
 * @persistent-lifecycle-callbacks pre-persist=prePersistTask
 * @persistent-entity-listener class="\\NoreSources\\Persistence\\TestUtility\\TestEntityListener"
 *
 */
class CustomIdEntity
{

	/**
	 * The entity unique ID
	 *
	 * @persistent-id generator=custom; custom-id-generator-class="NoreSources\\Persistence\\TestUtility\\TestUnixTimestampIdGenerator"
	 *
	 * @var integer
	 */
	public $id;

	/**
	 * The entity name
	 *
	 * @persistent-field
	 * @var string
	 */
	public $name;

	/**
	 *
	 * @persistent-field
	 * @var string|NULL
	 */
	public $nickname;

	/**
	 * A property which won't be mapped to DB
	 *
	 * @var unknown
	 */
	public $notMapped;

	public function setSecret(\DateTimeInterface $d)
	{
		$this->secret = $d;
	}

	/**
	 * The date of the beginning of the master plan to rule the world.
	 *
	 * @persistent-field
	 * @var \DateTimeInterface
	 */
	private $secret;
}
