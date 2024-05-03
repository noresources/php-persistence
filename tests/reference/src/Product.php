<?php
namespace NoreSources\Persistence\TestData;

/**
 *
 * @persistent-object table=products
 */
class Product
{

	/**
	 *
	 * @persistent-id generator=auto
	 * @var integer
	 */
	protected $id;

	/**
	 *
	 * @persistent-field
	 */
	protected $name;

	public function getId()
	{
		return $this->id;
	}

	public function getName()
	{
		return $this->name;
	}

	public function setName($name)
	{
		$this->name = $name;
	}
}
