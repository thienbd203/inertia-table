<?php

namespace Toolbelt\InertiaTable\Filters;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SetFilter extends Filter
{
    /** @var array<string|int, string> */
    protected array $options = [];

    protected bool $multiple = false;

    protected ?Closure $applyUsing = null;

    /** @var class-string<Model>|null */
    protected ?string $asyncOptionsModel = null;

    /** @var string|array<int, string>|Closure|null */
    protected string|array|Closure|null $asyncOptionsLabel = null;

    protected ?string $asyncOptionsValue = null;

    /** @var array<int, string> */
    protected array $asyncOptionsSearch = [];

    protected int $asyncOptionsLimit = 20;

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

    /**
     * @param  class-string<Model>  $model
     * @param  string|array<int, string>|Closure(Model): string  $label
     * @param  array<int, string>  $search
     */
    public function asyncOptionsFromModel(
        string $model,
        string $value = 'id',
        string|array|Closure $label = 'name',
        array $search = [],
        int $limit = 20,
    ): static {
        $this->asyncOptionsModel = $model;
        $this->asyncOptionsValue = $value;
        $this->asyncOptionsLabel = $label;
        $this->asyncOptionsSearch = $search;
        $this->asyncOptionsLimit = max(1, min($limit, 100));
        $this->options = [];

        return $this;
    }

    public function hasAsyncOptions(): bool
    {
        return $this->asyncOptionsModel !== null;
    }

    /** @return array<int, array{value: string|int, label: string}> */
    public function resolveAsyncOptions(string $search = ''): array
    {
        if (! $this->hasAsyncOptions()) {
            return collect($this->options)
                ->map(fn (string $label, string|int $value) => ['value' => $value, 'label' => $label])
                ->values()
                ->all();
        }

        $model = $this->asyncOptionsModel;
        $value = $this->asyncOptionsValue;
        $searchable = $this->asyncOptionsSearch;
        $query = $model::query();

        if ($search !== '' && $searchable !== []) {
            $query->where(function (Builder $query) use ($search, $searchable) {
                foreach ($searchable as $index => $column) {
                    $query->orWhere($column, 'like', "%{$search}%");
                }
            });
        }

        return $query->limit($this->asyncOptionsLimit)->get()
            ->map(fn (Model $model) => [
                'value' => $model->getAttribute($value),
                'label' => $this->resolveAsyncOptionLabel($model),
            ])->all();
    }

    protected function resolveAsyncOptionLabel(Model $model): string
    {
        if ($this->asyncOptionsLabel instanceof Closure) {
            return (string) ($this->asyncOptionsLabel)($model);
        }

        $attributes = (array) $this->asyncOptionsLabel;

        return collect($attributes)
            ->map(fn (string $attribute) => (string) $model->getAttribute($attribute))
            ->filter(fn (string $value) => $value !== '')
            ->implode(' - ');
    }

    public function normalize(mixed $value, ?string $clause = null): string|int|array|null
    {
        if ($this->hasAsyncOptions()) {
            if (is_array($value)) {
                return $this->multiple || in_array($clause, [Clause::In->value, Clause::NotIn->value], true)
                    ? array_values(array_filter($value, fn (mixed $candidate) => is_scalar($candidate)))
                    : null;
            }

            return is_scalar($value) ? $value : null;
        }

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

    protected function apply(Builder $query, string $clause, mixed $value): void
    {
        if ($this->applyUsing) {
            ($this->applyUsing)($query, $value, $clause);

            return;
        }

        $values = is_array($value) ? $value : [$value];
        match ($clause) {
            'in' => $query->whereIn($this->attribute, $values),
            'not_in' => $query->whereNotIn($this->attribute, $values),
            'equals' => $query->where($this->attribute, $values[0]),
            'not_equals' => $query->where($this->attribute, '!=', $values[0]),
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
            'asyncOptions' => $this->hasAsyncOptions() ? [
                'enabled' => true,
                'minimumSearchLength' => 0,
            ] : null,
        ];
    }
}
