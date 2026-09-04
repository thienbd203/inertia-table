<?php

namespace Musing\InertiaTable\Summaries;

enum SummaryAggregate: string
{
    case Count = 'count';
    case CountDistinct = 'count_distinct';
    case Sum = 'sum';
    case Average = 'avg';
    case Minimum = 'min';
    case Maximum = 'max';
    case Custom = 'custom';
}
