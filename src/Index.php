<?php

/**
 * Copyright © 2024 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package Persistence
 */
namespace NoreSources\Persistence;

use NoreSources\Container\KeyNotFoundException;
use NoreSources\Type\ArrayRepresentation;
use NoreSources\Type\TypeConversion;
use NoreSources\Type\TypeDescription;
use Psr\Container\ContainerInterface;

class Index implements ContainerInterface, ArrayRepresentation,
	\IteratorAggregate
{

	/**
	 *
	 * @param array $data
	 *        	Original index data
	 */
	public function __construct($data = array())
	{
		$this->data = $data;
	}

	/**
	 *
	 * @param string|integer $indexValue
	 *        	Index value
	 * @param mixed $objectId
	 *        	Object ID
	 */
	public function append($indexValue, $objectId)
	{
		$indexValue = $this->normalizeIndexValue($indexValue);
		if (!isset($this->data[$indexValue]))
			$this->data[$indexValue] = [];
		$this->data[$indexValue][] = $objectId;
	}

	/**
	 *
	 * @param string|integer $indexValue
	 *        	Index value
	 * @param mixed $objectId
	 *        	Object ID
	 */
	public function remove($indexValue, $objectId)
	{
		$indexValue = $this->normalizeIndexValue($indexValue);
		if (!isset($this->data[$indexValue]))
			return;
		$offset = \array_search($objectId, $this->data[$indexValue]);
		\array_splice($this->data[$indexValue], $offset, 1);
		if (\count($this->data[$indexValue]) == 0)
			unset($this->data[$indexValue]);
	}

	/**
	 *
	 * @param string|integer $indexValue
	 *        	Index value
	 * @param mixed $objectId
	 *        	Object ID
	 * @return boolean
	 */
	public function contains($indexValue, $objectId)
	{
		$indexValue = $this->normalizeIndexValue($indexValue);
		if (!isset($this->data[$indexValue]))
			return false;
		return \array_search($objectId, $this->data[$indexValue]) !==
			false;
	}

	/**
	 *
	 * @param string|integer $fromIndexValue
	 *        	Original index value
	 * @param string|integer $toIndexValue
	 *        	New index value
	 * @param mixed $objectId
	 *        	Object ID
	 */
	public function move($fromIndexValue, $toIndexValue, $objectId)
	{
		$fromIndexValue = $this->normalizeIndexValue($fromIndexValue);
		$toIndexValue = $this->normalizeIndexValue($toIndexValue);
		$this->remove($fromIndexValue, $objectId);
		$this->append($toIndexValue, $objectId);
	}

	/**
	 *
	 * @param
	 *        	string|integer Index value
	 * @return array
	 */
	public function get(string $indexValue)
	{
		$indexValue = $this->normalizeIndexValue($indexValue);
		if (!isset($this->data[$indexValue]))
			throw new KeyNotFoundException($indexValue);
		return $this->data[$indexValue];
	}

	public function has(string $indexValue)
	{
		$indexValue = $this->normalizeIndexValue($indexValue);
		return isset($this->data[$indexValue]);
	}

	public function getArrayCopy()
	{
		return $this->data;
	}

	#[\ReturnTypeWillChange]
	public function getIterator()
	{
		return new \ArrayIterator($this->data);
	}

	/**
	 * Clear index data
	 */
	public function clear()
	{
		$this->data = [];
	}

	private function normalizeIndexValue($indexValue)
	{
		if (\is_integer($indexValue))
			return $indexValue;
		if (!TypeDescription::hasStringRepresentation($indexValue))
			$this->notIndexable($indexValue);
		return TypeConversion::toString($indexValue);
	}

	private function notIndexable($indexValue)
	{
		throw new \LogicException(
			TypeDescription::getName($indexValue) .
			' is not a valid index value');
	}

	/**
	 *
	 * @var array<mixed, mixed>
	 */
	private $data = [];
}
