<?php

/**
 * Copyright © 2024 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package Persistence
 */
namespace NoreSources\Persistence\Collection;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Persistence\Mapping\ClassMetadata;
use Doctrine\Persistence\Mapping\ReflectionService;
use NoreSources\Container\Container;
use NoreSources\Type\ArrayRepresentation;
use Closure;
use ReflectionProperty;
use Traversable;

class ObjectCollection implements Collection, ArrayRepresentation
{

	/**
	 *
	 * @param Collection|array|Traversable $collection
	 *        	Object collection
	 * @param ClassMetadata $metadata
	 *        	Object class metadata
	 */
	public function __construct($collection, ClassMetadata $metadata)
	{
		if (\is_array($collection))
			$collection = new ArrayCollection($collection);
		elseif (!($collection instanceof Collection))
		{
			$collection = new ArrayCollection(
				Container::createArray($collection));
		}

		$this->collection = $collection;
		$this->metadata = $metadata;
	}

	public function next()
	{
		return $this->collection->next();
	}

	public function forAll(Closure $p)
	{
		return $this->collection->forAll($p);
	}

	public function remove($key)
	{
		return $this->collection->remove($key);
	}

	public function current()
	{
		return $this->collection->current();
	}

	public function partition(Closure $p)
	{
		return $this->collection->partition($p);
	}

	#[\ReturnTypeWillChange]
	public function offsetExists($offset)
	{
		return $this->collection->offsetExists($offset);
	}

	public function slice($offset, $length = null)
	{
		return $this->collection->slice($offset);
	}

	public function get($key)
	{
		return $this->collection->get($key);
	}

	#[\ReturnTypeWillChange]
	public function offsetUnset($offset)
	{
		return $this->collection->offsetUnset($offset);
	}

	public function toArray()
	{
		return $this->collection->toArray();
	}

	public function map(Closure $func)
	{
		return $this->collection->map($func);
	}

	public function indexOf($element)
	{
		return $this->collection->indexOf($element);
	}

	public function key()
	{
		return $this->collection->key();
	}

	public function add($element)
	{
		return $this->collection->add($element);
	}

	#[\ReturnTypeWillChange]
	public function offsetGet($offset)
	{
		return $this->collection->offsetGet($offset);
	}

	public function set($key, $value)
	{
		return $this->collection->set($key, $value);
	}

	public function getValues()
	{
		return $this->collection->getValues();
	}

	public function last()
	{
		return $this->collection->last();
	}

	public function containsKey($key)
	{
		return $this->collection->contains($element);
	}

	public function clear()
	{
		return $this->collection->clear();
	}

	public function isEmpty()
	{
		return $this->collection->isEmpty();
	}

	#[\ReturnTypeWillChange]
	public function count()
	{
		return $this->collection->count();
	}

	public function getKeys()
	{
		return $this->collection->getKeys();
	}

	#[\ReturnTypeWillChange]
	public function offsetSet($offset, $value)
	{
		return $this->collection->offsetSet($offset, $value);
	}

	public function filter(Closure $p)
	{
		return $this->collection->filter($p);
	}

	public function contains($element)
	{
		return $this->collection->contains($element);
	}

	#[\ReturnTypeWillChange]
	public function getIterator()
	{
		return $this->collection->getIterator();
	}

	public function exists(Closure $p)
	{
		return $this->collection->exists($p);
	}

	public function removeElement($element)
	{
		return $this->collection->removeElement($element);
	}

	public function first()
	{
		return $this->collection->first();
	}

	/**
	 * doctrine/collections v2.0 API
	 *
	 * @param Closure $p
	 * @return unknown|\Doctrine\Common\Collections\Collection|NULL
	 */
	public function findFirst(Closure $p)
	{
		if (\method_exists($this->collection, 'findFirst'))
			return $this->collection->findFirst($p);
		foreach ($this->collection as $value)
		{
			if ($p($value))
				return $value;
		}
		return null;
	}

	public function reduce(Closure $func, mixed $initial = null)
	{
		if (\method_exists($this->collection, 'reduce'))
			return $this->collection->reduce($func, $initial);
		throw new \RuntimeException('Not implemented');
	}

	public function getArrayCopy()
	{
		return $this->collection->toArray();
	}

	/**
	 * Get list of object identifiers
	 *
	 * @return Collection
	 */
	public function getIdentifiers()
	{
		return $this->collection->map(
			function ($object) {
				return $this->metadata->getIdentifierValues($object);
			})
			->toArray();
	}

	/**
	 *
	 * @param callable|NULL $idStringifier
	 *        	ID stringifier for composite IDs
	 * @throws \InvalidArgumentException
	 * @return array Dictionary of objects where keys are the object IDs
	 */
	public function getDictionary($idStringifier = null)
	{
		$dictionary = [];
		$idFields = $this->metadata->getIdentifierFieldNames();
		$c = \count($idFields);
		if ($c == 0)
			return $this->collection->toArray();

		if ($c == 1)
		{
			foreach ($this->collection as $object)
			{
				$id = Container::firstValue(
					$this->metadata->getIdentifierValues($object));
				$dictionary[$id] = $object;
			}

			return $dictionary;
		}

		if (!\is_callable($idStringifier))
			throw new \InvalidArgumentException(
				'ID stringifier is require for composite identifier');

		foreach ($this->collection as $object)
		{
			$id = \call_user_func($idStringifier,
				$this->metadata->getIdentifierValues($object));
			$dictionary[$id] = $object;
		}

		return $dictionary;
	}

	const NODE_OBJECT = 'object';

	const NODE_CHILDREN = 'children';

	/**
	 * Get the object collection reorganized as a tree.
	 *
	 * @param string $parentAssociationName
	 *        	Parent association field name
	 * @param ReflectionService $reflectionService
	 *        	Reflection service. If not set, the default reflection service is used.
	 * @throws \InvalidArgumentException
	 * @return array
	 */
	public function getHierarchyFromParentAssociation(
		$parentAssociationName,
		ReflectionService $reflectionService = null)
	{
		if (!$this->metadata->isSingleValuedAssociation(
			$parentAssociationName))
			throw new \InvalidArgumentException(
				$parentAssociationName .
				' is not a single value association');

		if (!$reflectionService)
			$reflectionService = \NoreSources\Persistence\Mapping\ReflectionService::getInstance();

		$tree = [];
		$visited = [];
		$property = $reflectionService->getAccessibleProperty(
			$this->metadata->getName(), $parentAssociationName);
		foreach ($this->collection as $object)
		{
			$visited[] = $object;
			$parent = $property->getValue($object);
			if ($parent !== null)
				continue;
			$node = [
				self::NODE_OBJECT => $object,
				self::NODE_CHILDREN => []
			];
			$this->populateChildren($node, $visited, $property);
			$tree[] = $node;
		}

		return $tree;
	}

	private function populateChildren(&$node, &$visited,
		ReflectionProperty $property)
	{
		$parent = $node[self::NODE_OBJECT];
		foreach ($this->collection as $objecr)
		{
			if (\in_array($objecr, $visited))
				continue;
			$value = $property->getValue($objecr);
			if ($value !== $parent)
				continue;
			$visited[] = $objecr;
			$child = [
				self::NODE_OBJECT => $objecr,
				self::NODE_CHILDREN => []
			];

			$this->populateChildren($child, $visited, $property);
			$node[self::NODE_CHILDREN] = $child;
		}
	}

	/**
	 *
	 * @var Collection
	 */
	private $collection;

	/**
	 *
	 * @var ClassMetadata
	 */
	private $metadata;
}
