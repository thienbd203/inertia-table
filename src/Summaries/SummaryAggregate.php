<?php

namespace Musing\InertiaTable\Summaries;

use LogicException;

enum SummaryAggregate: string
{
    case Count = 'count';
    case CountDistinct = 'count_distinct';
    case Sum = 'sum';
    case Average = 'avg';
    case Minimum = 'min';
    case Maximum = 'max';
    case Custom = 'custom';

    public function expression(?string $wrappedAttribute): string
    {
        return match ($this) {
            self::Count => 'COUNT(*)',
            self::CountDistinct => "COUNT(DISTINCT {$this->attribute($wrappedAttribute)})",
            self::Sum => "SUM({$this->attribute($wrappedAttribute)})",
            self::Average => "AVG({$this->attribute($wrappedAttribute)})",
            self::Minimum => "MIN({$this->attribute($wrappedAttribute)})",
            self::Maximum => "MAX({$this->attribute($wrappedAttribute)})",
            self::Custom => throw new LogicException('Custom summaries do not have a built-in SQL expression.'),
        };
    }

    private function attribute(?string $wrappedAttribute): string
    {
        if ($wrappedAttribute === null) {
            throw new LogicException('This summary aggregate requires an attribute.');
        }

        return $wrappedAttribute;
    }
}
