<?php

namespace Musing\InertiaTable\Filters;

use Illuminate\Database\Eloquent\Builder;

class BooleanFilter extends Filter
{
    protected function defaultClauses(): array
    {
        return [Clause::IsTrue, Clause::IsFalse];
    }

    public function normalize(mixed $value, ?string $clause = null): mixed
    {
        return null;
    }

    protected function apply(Builder $query, string $clause, mixed $value): void
    {
        $query->where($this->attribute, $clause === Clause::IsTrue->value);
    }

    public function toArray(): array
    {
        return [...parent::toArray(), 'type' => 'boolean', 'options' => []];
    }
}
