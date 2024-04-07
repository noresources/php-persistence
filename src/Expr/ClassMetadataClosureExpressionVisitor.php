<?php

/**
 * Copyright © 2024 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package Persistence
 */
namespace NoreSources\Persistence\Expr;

use Doctrine\Persistence\Mapping\ClassMetadata;
use Doctrine\Common\Collections\Expr\Comparison;
use Doctrine\Common\Collections\Expr\ClosureExpressionVisitor;
use Doctrine\Common\Comparable;
use NoreSources\Type\TypeDescription;
use NoreSources\Type\TypeConversion;
use NoreSources\ComparableInterface;

class ClassMetadataClosureExpressionVisitor extends ClosureExpressionVisitor
{

	public function __construct(ClassMetadata $metadata)
	{
		$this->metadata = $metadata;
	}

	public function walkComparison(Comparison $comparison)
	{
		$op = $comparison->getOperator();
		$value = $comparison->getValue()->getValue(); // shortcut for walkValue()
		$field = $comparison->getField();
		if (!$this->metadata->hasField($field))
			return parent::walkComparison($comparison);
		$type = $this->metadata->getTypeOfField($field);
		if (!\class_exists($type))
			return parent::walkComparison($comparison);

		if (\is_a($type, ComparableInterface::class, true))
		{
			switch ($op)
			{
				case Comparison::EQ:
					return static function ($object) use ($field, $value): bool {
						$fieldValue = ClosureExpressionVisitor::getObjectFieldValue(
							$object, $field);
						if ($fieldValue === $value)
							return true;
						return $fieldValue->compareTo($value) == 0;
					};
				case Comparison::LT:
					return static function ($object) use ($field, $value): bool {
						$fieldValue = ClosureExpressionVisitor::getObjectFieldValue(
							$object, $field);
						return $fieldValue->compareTo($value) < 0;
					};
				case Comparison::LE:
					return static function ($object) use ($field, $value): bool {
						$fieldValue = ClosureExpressionVisitor::getObjectFieldValue(
							$object, $field);
						return $fieldValue->compareTo($value) <= 0;
					};
				case Comparison::GT:
					return static function ($object) use ($field, $value): bool {
						$fieldValue = ClosureExpressionVisitor::getObjectFieldValue(
							$object, $field);
						return $fieldValue->compareTo($value) > 0;
					};
				case Comparison::GE:
					return static function ($object) use ($field, $value): bool {
						$fieldValue = ClosureExpressionVisitor::getObjectFieldValue(
							$object, $field);
						return $fieldValue->compareTo($value) >= 0;
					};
			}
		}
		elseif (\method_exists($type, '__toString'))
		{
			switch ($op)
			{
				case Comparison::EQ:
					return static function ($object) use ($field, $value): bool {
						$fieldValue = ClosureExpressionVisitor::getObjectFieldValue(
							$object, $field);
						if ($fieldValue === $value)
							return true;
						$c = \strcmp(
							TypeConversion::toString($fieldValue),
							TypeConversion::toString($value));
						return $c === 0;
					};
				case Comparison::LT:
					return static function ($object) use ($field, $value): bool {
						$fieldValue = ClosureExpressionVisitor::getObjectFieldValue(
							$object, $field);
						$c = \strcmp(
							TypeConversion::toString($fieldValue),
							TypeConversion::toString($value));
						return $c < 0;
					};
				case Comparison::LE:
					return static function ($object) use ($field, $value): bool {
						$fieldValue = ClosureExpressionVisitor::getObjectFieldValue(
							$object, $field);
						$c = \strcmp(
							TypeConversion::toString($fieldValue),
							TypeConversion::toString($value));
						return $c <= 0;
					};
				case Comparison::GT:
					return static function ($object) use ($field, $value): bool {
						$fieldValue = ClosureExpressionVisitor::getObjectFieldValue(
							$object, $field);
						$c = \strcmp(
							TypeConversion::toString($fieldValue),
							TypeConversion::toString($value));
						return $c > 0;
					};
				case Comparison::GE:
					return static function ($object) use ($field, $value): bool {
						$fieldValue = ClosureExpressionVisitor::getObjectFieldValue(
							$object, $field);
						$c = \strcmp(
							TypeConversion::toString($fieldValue),
							TypeConversion::toString($value));
						return $c >= 0;
					};
			}
		}

		return parent::walkComparison($comparison);
	}

	public static function compareString($fieldValue, $value)
	{
		try
		{
			return \strcmp(TypeConversion::toString($fieldValue),
				TypeConversion::toString($value));
		}
		catch (\Exception $e)
		{
			return false;
		}
	}

	/**
	 *
	 * @var ClassMetadata
	 */
	private $metadata;
}
