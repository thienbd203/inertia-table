<?php

namespace Musing\InertiaTable\Filters;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use LogicException;

class SetFilter extends Filter
{
    /** @var array<string|int, string> */
    protected array $options = [];

    /** @var (Closure(): array<string|int, mixed>)|null */
    protected ?Closure $optionsResolver = null;

    protected bool $multiple = false;

    protected bool $lazy = false;

    protected ?Closure $applyUsing = null;

    protected function defaultClauses(): array
    {
        return [Clause::In, Clause::NotIn, Clause::Equals, Clause::NotEquals];
    }

    /** @param array<string|int, string> $options */
    public function options(array $options): static
    {
        $this->options = $options;
        $this->optionsResolver = null;

        return $this;
    }

    /** @param class-string<Model> $model */
    public function pluckOptionsFromModel(
        string $model,
        string $label = 'name',
        ?string $value = null,
    ): static {
        $instance = new $model;
        $label = $this->validateOptionAttribute($label);
        $value = $this->validateOptionAttribute($value ?? $instance->getKeyName());
        $this->options = [];
        $this->optionsResolver = static fn (): array => $model::query()
            ->orderBy($label)
            ->pluck($label, $value)
            ->all();

        return $this;
    }

    public function lazy(bool $lazy = true): static
    {
        $this->lazy = $lazy;

        return $this;
    }

    public function isLazy(): bool
    {
        return $this->lazy;
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
            foreach (array_keys($this->resolvedOptions()) as $option) {
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

    /** @return array<string|int, string> */
    public function resolvedOptions(): array
    {
        if ($this->optionsResolver instanceof Closure) {
            $this->options = $this->normalizeOptions(($this->optionsResolver)());
            $this->optionsResolver = null;
        }

        return $this->options;
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

    /** @return array<string, mixed> */
    public function toArray(bool $loadLazyOptions = false): array
    {
        $options = ! $this->lazy || $loadLazyOptions
            ? $this->resolvedOptions()
            : [];

        $resolved = [
            ...parent::toArray(),
            'type' => 'set',
            'options' => collect($options)
                ->map(fn (string $label, string|int $value) => [
                    'value' => $value,
                    'label' => $label,
                ])
                ->values()
                ->all(),
            'multiple' => $this->multiple,
        ];

        return ! $this->lazy ? $resolved : [
            ...$resolved,
            'lazy' => true,
            'lazyLoaded' => $loadLazyOptions,
        ];
    }

    /**
     * @param  array<string|int, mixed>  $options
     * @return array<string|int, string>
     */
    private function normalizeOptions(array $options): array
    {
        $normalized = [];

        foreach ($options as $value => $label) {
            if (! is_string($label) && ! is_int($label) && ! is_float($label)) {
                continue;
            }

            $normalized[$value] = (string) $label;
        }

        return $normalized;
    }

    private function validateOptionAttribute(string $attribute): string
    {
        if (! preg_match('/^[A-Za-z_][A-Za-z0-9_.]*$/', $attribute)) {
            throw new LogicException("Invalid set filter option attribute [{$attribute}].");
        }

        return $attribute;
    }
}
