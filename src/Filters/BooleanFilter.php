<?php

namespace Toolbelt\InertiaTable\Filters;

use Spatie\QueryBuilder\AllowedFilter;

class BooleanFilter extends Filter
{
    public function defaultClause(): string
    {
        return 'equals';
    }

    public function allowedFilter(): AllowedFilter
    {
        return AllowedFilter::exact($this->attribute);
    }

    public function normalize(mixed $value): ?bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
    }

    public function toArray(): array
    {
        return [...parent::toArray(), 'type' => 'boolean', 'options' => []];
    }
}
