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
class UnitOfWork implements ObjectContainerInterface
{

	/**
	 * Managed object pending operation
	 *
	 * @var integer
	 */
	const KEY_OPERATION = 0;

	/**
	 * Managed object data
	 *
	 * @var integer
	 */
	const KEY_OBJECT = 1;

	/**
	 * Object initial identity
	 *
	 * @var integer
	 */
	const KEY_IDENTITY = 2;

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
		$this->managedObjects = [];
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

	public function attach(object $object)
	{
		$oid = $this->getObjectOID($object);
		if (isset($this->managedObjects[$oid]))
			return;
		$this->managedObjects[$oid] = [
			self::KEY_OBJECT => $object
		];
	}

	public function detach($object)
	{
		$oid = $this->getObjectOID($object);
		if (!isset($this->managedObjects[$oid]))
			$this->notManagedException($object);
		$index = \array_search($oid, $this->operationOrder);
		unset($this->managedObjects[$oid]);
		\array_splice($this->operationOrder, $index, 1);
	}

	public function clear($full)
	{
		if ($full)
			$this->managedObjects = [];
		else
		{
			foreach ($this->managedObjects as $oid => $infos)
				unset($this->managedObjects[$oid][self::KEY_OPERATION]);
		}

		$this->operationOrder = [];
	}

	public function contains($object)
	{
		$oid = $this->getObjectOID($object);
		if (!isset($this->managedObjects[$oid]))
			return false;
		$o = $this->managedObjects[$oid];
		if (!isset($o[self::KEY_OPERATION]))
			return true;
		return ($o[self::KEY_OPERATION] != self::OPERATION_REMOVE);
	}

	/**
	 *
	 * @internal Used by ObjectManager
	 */
	public function getTasks()
	{
		$tasks = [];
		foreach ($this->operationOrder as $oid)
			$tasks[] = $this->managedObjects[$oid];
		return $tasks;
	}

	public function setObjectIdentity($object, $id)
	{
		if (!\is_array($id) && \count($id))
			throw new \InvalidArgumentException(
				'Object identity must be an array with at least 1 element. Got ' .
				gettype($object));
		$oid = $this->getObjectOID($object);
		if (!isset($this->managedObjects[$oid]))
			$this->notManagedException($object);
		$this->managedObjects[$oid][self::KEY_IDENTITY] = $id;
	}

	protected function notManagedException($classOrObject)
	{
		throw new NotManagedException($classOrObject, $this);
	}

	private function appendOperation($oid, $object, $operation)
	{
		if (!isset($this->managedObjects[$oid]))
			$this->managedObjects[$oid] = [
				self::KEY_OBJECT => $object
			];

		if (isset($this->managedObjects[$oid][self::KEY_OPERATION]))
		{
			$previous = $this->managedObjects[$oid][self::KEY_OPERATION];
			if ($previous == $operation)
				return;

			$conflicts = true;

			if ($previous == self::OPERATION_INSERT)
			{
				if ($operation == self::OPERATION_UPDATE)
					return;
				if ($operation == self::OPERATION_REMOVE)
					$conflicts = false;
			}

			if ($conflicts)
				throw new \InvalidArgumentException(
					'A different operation already exists for this object');
		}

		$this->operationOrder[] = $oid;

		$this->managedObjects[$oid][self::KEY_OPERATION] = $operation;
	}

	private function getObjectOID($object)
	{
		return \call_user_func($this->oidFunction, $object);
	}

	/**
	 *
	 * @var array
	 */
	private $managedObjects;

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
