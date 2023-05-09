<?php

/**
 * Copyright © 2023 - 2024 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package Persistence
 */
namespace NoreSources\Persistence\Traits;

use Doctrine\Persistence\Mapping\ClassMetadata;
use NoreSources\Reflection\ReflectionData;
use NoreSources\Reflection\ReflectionService;
use NoreSources\Reflection\ReflectionServiceInterface;
use ReflectionProperty;

/**
 * Use reflection to implements some ClassMetadata interfaces
 *
 * @used-by DefaultObjectSorter
 * @used-by ClassMetadataReflectionPropertyMapper
 *
 */
trait ReflectionMetadataFieldTrait
{

	/**
	 *
	 * @return ClassMetadata
	 */
	public function getClassMetadata()
	{
		return $this->metadata;
	}

	/**
	 *
	 * @return \NoreSources\Reflection\ReflectionService
	 */
	public function getReflectionService()
	{
		if (!isset($this->reflectionService))
			$this->reflectionService = ReflectionService::getInstance();
		return $this->reflectionService;
	}

	public function getReflectionFieldFlags()
	{
		return 0;
	}

	/**
	 *
	 * @param string $name
	 *        	Class name
	 * @return ReflectionProperty|\NoreSources\Reflection\ReflectionData
	 */
	public function getReflectionField($name)
	{
		if (!isset($this->reflectionFields))
			$this->reflectionFields = [];
		if (\array_key_exists($name, $this->reflectionFields))
			return $this->reflectionFields[$name];

		$this->reflectionFields[$name] = $this->createReflectionField(
			$name);
		return $this->reflectionFields[$name];
	}

	/**
	 *
	 * @param ClassMetadata $metadataClass
	 *        	metadata
	 */
	protected function setClassMetadata(ClassMetadata $metadata)
	{
		$this->metadata = $metadata;
	}

	/**
	 *
	 * @param string $name
	 * @return \ReflectionProperty|ReflectionData
	 */
	protected function createReflectionField($name)
	{
		$mask = (ReflectionServiceInterface::ALLOW_RW_METHODS |
			ReflectionServiceInterface::FORCE_RW_METHODS);
		$flags = ReflectionServiceInterface::RW |
			ReflectionServiceInterface::EXPOSE_HIDDEN_PROPERTY;

		$flags |= ($mask & $this->getReflectionFieldFlags());

		return $this->getReflectionService()->getReflectionProperty(
			$this->metadata->getName(), $name, $flags);
	}

	/**
	 *
	 * @param string $name
	 *        	Property name
	 * @param \ReflectionProperty|ReflectionData $property
	 * @return $this
	 */
	protected function setReflectionField($name, \Reflector $property)
	{
		if (!isset($this->reflectionFields))
			$this->reflectionFields = [];
		$this->reflectionFields[$name] = $property;
		return $this;
	}

	/**
	 *
	 * @var ClassMetadata
	 */
	private $metadata;

	/**
	 *
	 * @var ReflectionService
	 */
	private $reflectionService;

	/**
	 *
	 * @var \ReflectionProperty[]|ReflectionData[]
	 */
	private $reflectionFields;
}
