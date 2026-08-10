<?php

namespace Toolbelt\InertiaTable\Filters;

use Illuminate\Database\Eloquent\Builder;

class TextFilter extends Filter
{
    protected function defaultClauses(): array
    {
        return [Clause::Contains, Clause::NotContains, Clause::StartsWith, Clause::EndsWith, Clause::NotStartsWith, Clause::NotEndsWith, Clause::Equals, Clause::NotEquals];
    }

    public function normalize(mixed $value, ?string $clause = null): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    protected function apply(Builder $query, string $clause, mixed $value): void
    {
        $value = (string) $value;
        match ($clause) {
            'contains' => $query->where($this->attribute, 'like', "%{$value}%"),
            'not_contains' => $query->where($this->attribute, 'not like', "%{$value}%"),
            'starts_with' => $query->where($this->attribute, 'like', "{$value}%"),
            'ends_with' => $query->where($this->attribute, 'like', "%{$value}"),
            'not_starts_with' => $query->where($this->attribute, 'not like', "{$value}%"),
            'not_ends_with' => $query->where($this->attribute, 'not like', "%{$value}"),
            'equals' => $query->where($this->attribute, $value),
            'not_equals' => $query->where($this->attribute, '!=', $value),
            default => null,
        };
    }

    public function toArray(): array
    {
        return [...parent::toArray(), 'type' => 'text', 'options' => []];
    }
}
