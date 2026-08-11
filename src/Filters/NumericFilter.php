<?php

namespace Toolbelt\InertiaTable\Filters;

use Illuminate\Database\Eloquent\Builder;

class NumericFilter extends Filter
{
    protected function defaultClauses(): array
    {
        return [Clause::Equals, Clause::NotEquals, Clause::GreaterThan, Clause::GreaterThanOrEqual, Clause::LessThan, Clause::LessThanOrEqual, Clause::Between, Clause::NotBetween];
    }

    public function normalize(mixed $value, ?string $clause = null): int|float|array|null
    {
        if (in_array($clause, [Clause::Between->value, Clause::NotBetween->value], true)) {
            if (! is_array($value) || count($value) !== 2 || ! is_numeric($value[0]) || ! is_numeric($value[1])) {
                return null;
            }

            return [0 + $value[0], 0 + $value[1]];
        }

        return is_numeric($value) ? 0 + $value : null;
    }

    protected function apply(Builder $query, string $clause, mixed $value): void
    {
        match ($clause) {
            'equals' => $query->where($this->attribute, $value),
            'not_equals' => $query->where($this->attribute, '!=', $value),
            'greater_than' => $query->where($this->attribute, '>', $value),
            'greater_than_or_equal' => $query->where($this->attribute, '>=', $value),
            'less_than' => $query->where($this->attribute, '<', $value),
            'less_than_or_equal' => $query->where($this->attribute, '<=', $value),
            'between' => $query->whereBetween($this->attribute, $value),
            'not_between' => $query->whereNotBetween($this->attribute, $value),
            default => null,
        };
    }

    public function toArray(): array
    {
        return [...parent::toArray(), 'type' => 'numeric', 'options' => []];
    }
}
