<?php

/**
 * Copyright © 2024 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package Persistence
 */
namespace NoreSources\Persistence\TestData\Abstracts;

use NoreSources\Persistence\TestData\Traits\CoolnessTrait;

/**
 *
 * This class is declared as an entity for consistency with XML driver requirements.
 *
 * Becoause this class is abstract, it will be considered by default as a mapped superclass
 *
 * @persistent-object
 *
 */
abstract class ParentEntity
{

	use CoolnessTrait;

	/**
	 *
	 * @persistent-property
	 * @var string
	 */
	public $firstName = 'Juste';

	/**
	 *
	 * @return string
	 */
	abstract function getKind();

	/**
	 *
	 * @return string
	 */
	public function getName()
	{
		return $this->firstName . ' ' . $this->lastName;
	}

	/**
	 *
	 * The instance name
	 *
	 * This field will not be visible by child class ReflectionClass
	 *
	 * @persistent-property
	 * @var string
	 */
	private $lastName = 'Leblanc';
}
