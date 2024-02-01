<?php

/**
 * Copyright © 2023 - 2024 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package Persistence
 */
namespace NoreSources\Persistence\Mapping\Traits;

use NoreSources\Persistence\Id\IdGeneratorInterface;
use NoreSources\Text\Text;
use NoreSources\Type\TypeDescription;

/**
 * Id generator related method for ClassMetadata implementations.
 *
 * @use-by GenericClassMetadata
 * @used-by ReflectionClassMetadata
 *
 */
trait IdGeneratorTypeClassnameTrait
{

	/**
	 * ID generator-compatible class
	 */
	public $idGenerator;

	/**
	 *
	 * @var array
	 */
	public $customGeneratorDefinition;

	/**
	 *
	 * @param string|IdGeneratorInterface $type
	 *        	ID generator type , class nmme or short name
	 * @throws \InvalidArgumentException
	 */
	public function setIdGeneratorType($type)
	{
		if (\strcasecmp($type, 'custom') == 0 &&
			isset($this->customGeneratorDefinition))
		{
			$type = $this->customGeneratorDefinition;
		}

		if (\is_object($type))
		{
			if (\method_exists($type, 'generate'))
			{
				$this->idGenerator = $type;
				return;
			}
		}

		if (!\is_string($type))
			throw new \InvalidArgumentException(
				IdGeneratorInterface::class .
				'-compatible class or class name expected. Got ' .
				TypeDescription::getName($type));

		$namespaces = TypeDescription::getNamespaces(
			IdGeneratorInterface::class, true);
		$namespace = \implode('\\', $namespaces);
		$pascal = Text::toPascalCase($type);
		$alternatives = [
			$type,
			$pascal,
			$namespace . '\\' . $type,
			$namespace . '\\' . $pascal,
			$namespace . '\\' . $type . 'IdGenerator',
			$namespace . '\\' . $pascal . 'IdGenerator'
		];

		foreach ($alternatives as $a)
		{
			if (!\class_exists($a))
				continue;
			if (!\method_exists($a, 'generate'))
				continue;
			$reflection = new \ReflectionClass($a);
			$this->idGenerator = $reflection->newInstance();
			return;
		}

		throw new \InvalidArgumentException(
			\implode(', ', $alternatives) . ' are not ' .
			IdGeneratorInterface::class . '-compatible class names');
	}

	/**
	 *
	 * @param array $definition
	 *        	Custom ID generator descriptor
	 */
	public function setCustomGeneratorDefinition($definition)
	{
		$this->customGeneratorDefinition = $definition['class'];
	}
}
