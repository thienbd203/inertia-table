<?php

namespace Toolbelt\InertiaTable\Filters;

enum Clause: string
{
    case Contains = 'contains';
    case NotContains = 'not_contains';
    case StartsWith = 'starts_with';
    case EndsWith = 'ends_with';
    case NotStartsWith = 'not_starts_with';
    case NotEndsWith = 'not_ends_with';
    case Equals = 'equals';
    case NotEquals = 'not_equals';
    case In = 'in';
    case NotIn = 'not_in';
    case GreaterThan = 'greater_than';
    case GreaterThanOrEqual = 'greater_than_or_equal';
    case LessThan = 'less_than';
    case LessThanOrEqual = 'less_than_or_equal';
    case Between = 'between';
    case NotBetween = 'not_between';
    case Before = 'before';
    case After = 'after';
    case EqualOrBefore = 'equal_or_before';
    case EqualOrAfter = 'equal_or_after';
    case IsTrue = 'is_true';
    case IsFalse = 'is_false';
    case IsSet = 'is_set';
    case IsNotSet = 'is_not_set';
}
