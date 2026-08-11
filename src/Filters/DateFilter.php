<?php

namespace Toolbelt\InertiaTable\Filters;

use Illuminate\Database\Eloquent\Builder;

class DateFilter extends Filter
{
    protected function defaultClauses(): array
    {
        return [Clause::Before, Clause::After, Clause::EqualOrBefore, Clause::EqualOrAfter, Clause::Equals, Clause::NotEquals, Clause::Between, Clause::NotBetween];
    }

    public function normalize(mixed $value, ?string $clause = null): string|array|null
    {
        if (in_array($clause, [Clause::IsSet->value, Clause::IsNotSet->value], true)) {
            return null;
        }

        if (in_array($clause, [Clause::Between->value, Clause::NotBetween->value], true)) {
            return is_array($value) && count($value) === 2 && is_string($value[0]) && is_string($value[1]) ? array_values($value) : null;
        }

        return is_string($value) && $value !== '' ? $value : null;
    }

    protected function apply(Builder $query, string $clause, mixed $value): void
    {
        match ($clause) {
            'before' => $query->whereDate($this->attribute, '<', $value),
            'after' => $query->whereDate($this->attribute, '>', $value),
            'equal_or_before' => $query->whereDate($this->attribute, '<=', $value),
            'equal_or_after' => $query->whereDate($this->attribute, '>=', $value),
            'equals' => $query->whereDate($this->attribute, $value),
            'not_equals' => $query->whereDate($this->attribute, '!=', $value),
            'between' => $query->whereBetween($this->attribute, $value),
            'not_between' => $query->whereNotBetween($this->attribute, $value),
            default => null,
        };
    }

    public function toArray(): array
    {
        return [...parent::toArray(), 'type' => 'date', 'options' => []];
    }
}
