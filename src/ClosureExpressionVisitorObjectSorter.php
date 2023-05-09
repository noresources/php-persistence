<?php

/**
 * Copyright © 2023 - 2024 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package Persistence
 */
namespace NoreSources\Persistence;

use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Expr\ClosureExpressionVisitor;

/**
 * Implements ObjectSorterInterface using closure
 */
class ClosureExpressionVisitorObjectSorter implements
	ObjectSorterInterface

{

	public function sortObjects(&$list, $orderBy)
	{
		$next = null;
		foreach (array_reverse($orderBy) as $field => $ordering)
		{
			$next = ClosureExpressionVisitor::sortByField($field,
				$ordering === Criteria::DESC ? -1 : 1, $next);
		}

		if ($next)
			\uasort($list, $next);
	}
}
