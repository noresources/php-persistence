<?php

/**
 * Copyright © 2023 - 2024 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package Persistence
 */
namespace NoreSources\Persistence\Id;

use Doctrine\Persistence\ObjectManager;
use NoreSources\Text\Text;

/**
 * ID generator based on uniqid PHP builtin function
 */
class UniqidIdGenerator implements IdGeneratorInterface
{

	public function generate(ObjectManager $manager, $object)
	{
		$className = \get_class($object);
		$prefix = $className;
		$id = \uniqid($prefix);
		if (!$manager)
			return $id;

		$limit = 10;
		do
		{
			$id = Text::toSnakeCase(\uniqid($prefix, true));
			try
			{
				if ($manager->find($className, $id))
				{
					$limit--;
					continue;
				}
			}
			catch (\Exception $e)
			{}
			return $id;
		}
		while ($limit > 0);

		throw new \RuntimeException(
			'Unique ID gneration maximum attempt limit reached.');
	}
}
