<?php

/**
 * Copyright © 2023 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package Persistence
 */
namespace NoreSources\Persistence\TestUtility;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Tools\SchemaTool;

trait TestEntityManagerFactoryTrait
{

	private function createEntityManager(Configuration $configuration,
		$databasePath, $classNames = array(), $initialize = true)
	{
		$params = [
			'driver' => 'pdo_sqlite',
			'path' => $databasePath
		];

		$connection = DriverManager::getConnection($params);
		$em = new EntityManager($connection, $configuration);

		if (!$initialize)
			return $em;

		if (\count($classNames) == 0)
			$classNames = $configuration->getMetadataDriverImpl()->getAllClassNames();

		$metadata = [];
		foreach ($classNames as $className)
			$metadata[] = $em->getClassMetadata($className);

		$tool = new SchemaTool($em);

		if (!\file_exists($databasePath))
			$tool->createSchema($metadata);
		else
			$tool->updateSchema($metadata);
		return $em;
	}
}
