<?php

namespace Musing\InertiaTable\Filters;

use Closure;
use Illuminate\Database\Eloquent\Builder;

class SetFilter extends Filter
{
    /** @var array<string|int, string> */
    protected array $options = [];

    protected bool $multiple = false;

    protected ?Closure $applyUsing = null;

    protected function defaultClauses(): array
    {
        return [Clause::In, Clause::NotIn, Clause::Equals, Clause::NotEquals];
    }

    /** @param array<string|int, string> $options */
    public function options(array $options): static
    {
        $this->options = $options;

        return $this;
    }

    public function multiple(bool $multiple = true): static
    {
        $this->multiple = $multiple;

        return $this;
    }

    public function applyUsing(Closure $callback): static
    {
        $this->applyUsing = $callback;

        return $this;
    }

    public function normalize(mixed $value, ?string $clause = null): string|int|array|null
    {
        $values = is_array($value) ? $value : [$value];
        $normalized = [];

        foreach ($values as $candidate) {
            foreach (array_keys($this->options) as $option) {
                if ((string) $option === (string) $candidate) {
                    $normalized[] = $option;
                    break;
                }
            }
        }

        if ($normalized === []) {
            return null;
        }

        return $this->multiple || in_array($clause, [Clause::In->value, Clause::NotIn->value], true)
            ? array_values(array_unique($normalized, SORT_REGULAR))
            : $normalized[0];
    }

    protected function apply(Builder $query, string $clause, mixed $value, string $attribute): void
    {
        if ($this->applyUsing) {
            ($this->applyUsing)($query, $value, $clause, $attribute);

            return;
        }

        $values = is_array($value) ? $value : [$value];
        match ($clause) {
            'in' => $query->whereIn($attribute, $values),
            'not_in' => $query->whereNotIn($attribute, $values),
            'equals' => $query->where($attribute, $values[0]),
            'not_equals' => $query->where($attribute, '!=', $values[0]),
            default => null,
        };
    }

    public function toArray(): array
    {
        return [
            ...parent::toArray(),
            'type' => 'set',
            'options' => collect($this->options)->map(fn (string $label, string|int $value) => ['value' => $value, 'label' => $label])->values()->all(),
            'multiple' => $this->multiple,
        ];
    }
}
