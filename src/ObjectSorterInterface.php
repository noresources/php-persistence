<?php

/**
 * Copyright © 2023 - 2024 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package Persistence
 */
namespace NoreSources\Persistence;

/**
 * Sort object collection according user defined rules
 */
interface ObjectSorterInterface
{

	/**
	 *
	 * Ascending sort
	 *
	 * @var string
	 */
	/**
	 * Descending sort
	 *
	 * @var string
	 */
	const ASC = 'ASC';

	const DESC = 'DESC';

	/**
	 *
	 * @param object[] $list
	 *        	List to sort
	 * @param array $orderBy
	 *        	List of ordering criteria
	 * @return object[]
	 */
	function sortObjects(&$list, $orderBy);
}
