<?php

/**
 * Copyright © 2024 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package Persistence
 */
namespace NoreSources\Persistence\TestUtility;

use NoreSources\Container\Container;
use NoreSources\Type\TypeConversion;
use NoreSources\Type\TypeDescription;

trait ResultComparisonTrait
{

	/**
	 *
	 * @param mixed $tests
	 *        	List of tests
	 * @param mixed $a
	 *        	Reference implementation
	 * @param mixed $b
	 *        	Implementation to compare to reference
	 * @param unknown $testName
	 *        	Main test name
	 * @throws \Exception
	 */
	protected function compareImplementation($tests, $a, $b, $testName)
	{
		$vs = TypeDescription::getLocalName($a) . ' vs ' .
			TypeDescription::getLocalName($b);
		foreach ($tests as $method => $arguments)
		{
			if (\is_integer($method) && \is_string($arguments))
			{
				$method = $arguments;
				$arguments = [];
			}

			$callableA = [
				$a,
				$method
			];
			$callableB = [
				$b,
				$method
			];

			$actual = null;
			$expected = null;
			$callable = false;

			$label = $vs . ' | ';

			if (($callable = \is_callable($callableA)))
			{
				$label .= $method . '(';
				$label .= Container::implodeValues($arguments, ', ',
					function ($a) {
						try
						{
							return TypeConversion::toString($a);
						}
						catch (\Exception $e)
						{
							return TypeDescription::getName($a);
						}
					});
				$label .= ')';

				$this->assertIsCallable($callableB,
					TypeDescription::getLocalName($a) . '::' . $method);
			}
			else
			{
				$label .= '$' . $method . ' = ?';
				$this->assertCount(0, $arguments,
					$label . ' Cannot query ' . $method .
					' property with arguments');
			}
			try
			{
				if ($callable)
					$expected = \call_user_func_array($callableA,
						$arguments);
				else
					$expected = $a->$method;
			}
			catch (\Exception $e)
			{
				throw new \Exception(
					TypeDescription::getLocalName($a) . ' | ' . $label .
					': ' . $e->getMessage());
			}
			try
			{
				if ($callable)
					$actual = \call_user_func_array($callableB,
						$arguments);
				else
					$actual = $b->$method;
			}
			catch (\Exception $e)
			{
				throw new \Exception(
					TypeDescription::getLocalName($b) . ' | ' . $label .
					': ' . $e->getMessage());
			}

			if (Container::isArray($expected) &&
				Container::isArray($actual) &&
				Container::isIndexed($expected))
			{
				\sort($expected);
				\sort($actual);
			}

			$this->assertEquals($expected, $actual,
				$testName . ' | ' . $label);
		}
	}
}
