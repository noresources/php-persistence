<?php

/**
 * Copyright © 2023 - 2024 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package Persistence
 */
namespace NoreSources\Persistence;

/**
 * ID generator using PHP uniqid function
 */
class UnitOfWork
{

	const KEY_OPERATION = 0;

	const KEY_OBJECT = 1;

	/**
	 * Store a new object
	 *
	 * @var integer
	 */
	const OPERATION_INSERT = 0;

	/**
	 * Update already existing object
	 *
	 * @var integer
	 */
	const OPERATION_UPDATE = 1;

	const OPERATION_REMOVE = 2;

	public function __construct()
	{
		$this->operationOrder = [];
		$this->operations = [];
		$this->oidFunction = '\spl_object_hash';
		if (\function_exists('\spl_object_id'))
			$this->oidFunction = '\spl_object_id';
	}

	public function insert($object)
	{
		$oid = $this->getObjectOID($object);
		$operation = self::OPERATION_INSERT;
		$this->appendOperation($oid, $object, $operation);
	}

	public function update($object)
	{
		$oid = $this->getObjectOID($object);
		$operation = self::OPERATION_UPDATE;
		$this->appendOperation($oid, $object, $operation);
	}

	public function remove($object)
	{
		$oid = $this->getObjectOID($object);
		$this->appendOperation($oid, $object, self::OPERATION_REMOVE);
	}

	public function detach($object)
	{
		$oid = $this->getObjectOID($object);
		if (!isset($this->operations[$oid]))
			throw new \InvalidArgumentException(
				'Object is not manageed in this unit of work');
		$index = \array_search($oid, $this->operationOrder);
		unset($this->operations[$oid]);
		\array_splice($this->operationOrder, $index, 1);
	}

	public function clear()
	{
		$this->operations = [];
		$this->operationOrder = [];
	}

	public function contains($object)
	{
		$oid = $this->getObjectOID($object);
		return isset($this->operations[$oid]);
	}

	/**
	 *
	 * @internal Used by ObjectManager
	 */
	public function getTasks()
	{
		$tasks = [];
		foreach ($this->operationOrder as $oid)
			$tasks[] = $this->operations[$oid];
		return $tasks;
	}

	private function appendOperation($oid, $object, $operation)
	{
		if (isset($this->operations[$oid]))
		{
			if ($this->operations[$oid][self::KEY_OPERATION] !=
				$operation)
				throw new \InvalidArgumentException(
					'A different operation already exists for this object');
			return;
		}

		$this->operationOrder[] = $oid;
		$this->operations[$oid] = [
			self::KEY_OBJECT => $object,
			self::KEY_OPERATION => $operation
		];
	}

	private function getObjectOID($object)
	{
		return \call_user_func($this->oidFunction, $object);
	}

	/**
	 *
	 * @var array
	 */
	private $operations;

	/**
	 *
	 * @var array
	 */
	private $operationOrder;

	/**
	 *
	 * @var callable
	 */
	private $oidFunction;
}
