<?php
namespace NoreSources\Persistence\TestData;

use Doctrine\Common\Collections\ArrayCollection;

/**
 *
 * @persistent-object table=users
 */
class User
{

	/**
	 * User ID
	 *
	 * @persistent-id generator=auto
	 * @var string
	 */
	protected $id;

	/**
	 *
	 * @persistent-field indexed=true
	 * @var string
	 */
	protected $name;

	/**
	 *
	 * @persistent-one-to-many mapped-by=reporter
	 * @var Bug[]
	 */
	protected $reportedBugs = null;

	/**
	 *
	 * @persistent-one-to-many mapped-by=engineer
	 * @var Bug[]
	 */
	protected $assignedBugs = null;

	public function __construct($id = null, $name = null)
	{
		$this->id = $id;
		$this->name = $name;
		$this->reportedBugs = new ArrayCollection();
		$this->assignedBugs = new ArrayCollection();
	}

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

	public function addReportedBug($bug)
	{
		$this->reportedBugs[] = $bug;
	}

	public function assignedToBug($bug)
	{
		$this->assignedBugs[] = $bug;
	}
}
