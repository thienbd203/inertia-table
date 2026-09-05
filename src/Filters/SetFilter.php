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

    protected bool $multiple = false;

    protected ?Closure $applyUsing = null;

    protected ?Closure $optionsUsing = null;

    protected ?Closure $authorizeOptionsUsing = null;

    protected ?Closure $countsUsing = null;

    protected string $optionValue = 'id';

    protected string $optionLabel = 'name';

    /** @var array<int, string> */
    protected array $optionSearchColumns = [];

    /** @var array<int, string> */
    protected array $optionDependencies = [];

    protected bool $optionSearchable = false;

    protected bool $optionCounts = false;

    protected ?int $optionPageSize = null;

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

    public function optionsUsing(Closure $callback): static
    {
        $this->optionsUsing = $callback;

        return $this;
    }

    public function optionValue(string $attribute): static
    {
        $this->optionValue = $this->validateOptionAttribute($attribute);

        return $this;
    }

    public function optionLabel(string $attribute): static
    {
        $this->optionLabel = $this->validateOptionAttribute($attribute);

        return $this;
    }

    /** @param bool|string|array<int, string> $searchable */
    public function searchableOptions(bool|string|array $searchable = true): static
    {
        $this->optionSearchable = $searchable !== false;
        $columns = is_string($searchable)
            ? [$searchable]
            : (is_array($searchable) ? $searchable : [$this->optionLabel]);
        $this->optionSearchColumns = array_values(array_unique(array_map(
            fn (string $attribute) => $this->validateOptionAttribute($attribute),
            $columns,
        )));

        return $this;
    }

    /** @param array<int, string> $attributes */
    public function dependsOn(array $attributes): static
    {
        $this->optionDependencies = array_values(array_unique(array_filter(
            $attributes,
            fn (string $attribute) => $attribute !== $this->attribute,
        )));

        return $this;
    }

    public function withCounts(bool|Closure $counts = true): static
    {
        $this->optionCounts = $counts !== false;
        $this->countsUsing = $counts instanceof Closure ? $counts : null;

        return $this;
    }

    public function authorizeOptionsUsing(Closure $callback): static
    {
        $this->authorizeOptionsUsing = $callback;

        return $this;
    }

    public function optionPageSize(int $size): static
    {
        $this->optionPageSize = max(1, $size);

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
        if ($this->hasRemoteOptions()) {
            $normalized = $this->normalizeOptionValues($value);

            if ($normalized === []) {
                return null;
            }

            return $this->multiple || in_array($clause, [Clause::In->value, Clause::NotIn->value], true)
                ? $normalized
                : $normalized[0];
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

    public function hasRemoteOptions(): bool
    {
        return $this->optionsUsing instanceof Closure;
    }

    /** @return array<int, string> */
    public function optionDependencies(): array
    {
        return $this->optionDependencies;
    }

    /** @return array<int, string|int|bool> */
    public function normalizeOptionValues(mixed $values): array
    {
        $values = is_array($values) ? array_slice($values, 0, 100) : [$values];
        $normalized = [];

        foreach ($values as $value) {
            if (! is_string($value) && ! is_int($value) && ! is_bool($value)) {
                continue;
            }

            if (is_string($value) && (trim($value) === '' || mb_strlen($value) > 255)) {
                continue;
            }

            $normalized[(string) $value] = $value;
        }

        return array_values($normalized);
    }

    /**
     * @param  array{filters?: array<string, array{enabled?: bool, value?: mixed}>}  $state
     * @return array<string, mixed>
     */
    public function dependenciesFromState(array $state): array
    {
        $dependencies = [];

        foreach ($this->optionDependencies as $attribute) {
            $dependency = $state['filters'][$attribute] ?? null;
            $dependencies[$attribute] = is_array($dependency) && ($dependency['enabled'] ?? false)
                ? ($dependency['value'] ?? null)
                : null;
        }

        return $dependencies;
    }

    public function resolveOptionPageSize(mixed $requested = null): int
    {
        $configured = $this->optionPageSize
            ?? (int) config('inertia-table.filters.remote.per_page', 25);
        $maximum = max(1, (int) config('inertia-table.filters.remote.max_per_page', 100));
        $size = is_int($requested) ? $requested : $configured;

        return min(max(1, $size), $maximum);
    }

    public function isOptionLoadingAuthorized(FilterOptionRequest $request): bool
    {
        return $this->authorizeOptionsUsing === null
            || (bool) ($this->authorizeOptionsUsing)($request->request, $request->table, $this);
    }

    /** @return array{options: array<int, array{value: mixed, label: string, count?: int}>, selected: array<int, array{value: mixed, label: string, count?: int}>, nextCursor: string|null} */
    public function resolveRemoteOptions(FilterOptionRequest $request): array
    {
        $query = $this->optionQuery($request);
        $selected = $this->resolveSelectedOptions($request);

        if ($this->optionSearchable && $request->search !== '') {
            $this->applyOptionSearch($query, $request->search);
        }

        if ($query->getQuery()->orders === null) {
            $query->orderBy($query->qualifyColumn($this->optionValue));
        }

        $paginator = $query->cursorPaginate(
            $request->perPage,
            ['*'],
            'cursor',
            $request->cursor,
        );
        $options = $this->serializeOptionModels($paginator->items());
        $values = array_values(array_unique(array_map(
            fn (array $option) => $option['value'],
            [...$options, ...$selected],
        ), SORT_REGULAR));
        $counts = $this->resolveCounts($request, $values);

        return [
            'options' => $this->applyCounts($options, $counts),
            'selected' => $this->applyCounts($selected, $counts),
            'nextCursor' => $paginator->nextCursor()?->encode(),
        ];
    }

    /** @return array<int, array{value: mixed, label: string}> */
    public function resolveSelectedOptions(FilterOptionRequest $request): array
    {
        if ($request->selected === []) {
            return [];
        }

        $hydrateRequest = new FilterOptionRequest(
            request: $request->request,
            table: $request->table,
            filter: $this,
            // Selected labels must remain resolvable when a dependency changes
            // and removes that value from the currently available option set.
            dependencies: [],
            selected: $request->selected,
            state: $request->state,
            perPage: $request->perPage,
        );
        $query = $this->optionQuery($hydrateRequest);
        $query->whereIn($query->qualifyColumn($this->optionValue), $request->selected);

        return $this->serializeOptionModels($query->get());
    }

    /** @return array<string, mixed> */
    public function remoteConfiguration(string $endpoint): array
    {
        return [
            'endpoint' => $endpoint,
            'searchable' => $this->optionSearchable,
            'dependsOn' => $this->optionDependencies,
            'perPage' => $this->resolveOptionPageSize(),
            'debounceTime' => (int) config('inertia-table.filters.remote.debounce', 250),
            'cacheTtl' => (int) config('inertia-table.filters.remote.cache_ttl', 30000),
            'maxCacheEntries' => (int) config('inertia-table.filters.remote.max_cache_entries', 50),
            'withCounts' => $this->optionCounts,
        ];
    }

    private function optionQuery(FilterOptionRequest $request): Builder
    {
        $query = ($this->optionsUsing)($request);

        if (! $query instanceof Builder) {
            throw new LogicException('Remote filter option callbacks must return an Eloquent builder.');
        }

        return $query;
    }

    private function applyOptionSearch(Builder $query, string $search): void
    {
        $columns = $this->optionSearchColumns !== []
            ? $this->optionSearchColumns
            : [$this->optionLabel];
        $pattern = '%'.str_replace(['=', '%', '_'], ['==', '=%', '=_'], $search).'%';

        $query->where(function (Builder $query) use ($columns, $pattern): void {
            $grammar = $query->getQuery()->getGrammar();

            foreach ($columns as $index => $column) {
                $wrapped = $grammar->wrap($query->qualifyColumn($column));
                $method = $index === 0 ? 'whereRaw' : 'orWhereRaw';
                $query->{$method}("{$wrapped} LIKE ? ESCAPE '='", [$pattern]);
            }
        });
    }

    /** @param iterable<int, Model|array<string, mixed>|object> $models
     * @return array<int, array{value: mixed, label: string}>
     */
    private function serializeOptionModels(iterable $models): array
    {
        return collect($models)->map(fn (mixed $model) => [
            'value' => data_get($model, $this->optionValue),
            'label' => (string) data_get($model, $this->optionLabel),
        ])->filter(fn (array $option) => $option['value'] !== null)
            ->unique(fn (array $option) => (string) $option['value'])
            ->values()
            ->all();
    }

    /** @param array<int, string|int|bool> $values
     * @return array<string, int>
     */
    private function resolveCounts(FilterOptionRequest $request, array $values): array
    {
        if (! $this->optionCounts || $values === []) {
            return [];
        }

        $counts = $this->countsUsing
            ? ($this->countsUsing)($request, $values)
            : $request->table->facetCounts($this, $request->state, $values);

        if (! is_array($counts)) {
            throw new LogicException('Remote filter count callbacks must return an array keyed by option value.');
        }

        return collect($counts)->mapWithKeys(
            fn (mixed $count, mixed $value) => [(string) $value => max(0, (int) $count)],
        )->all();
    }

    /** @param array<int, array{value: mixed, label: string}> $options
     * @param  array<string, int>  $counts
     * @return array<int, array{value: mixed, label: string, count?: int}>
     */
    private function applyCounts(array $options, array $counts): array
    {
        if (! $this->optionCounts) {
            return $options;
        }

        return array_map(fn (array $option) => [
            ...$option,
            'count' => $counts[(string) $option['value']] ?? 0,
        ], $options);
    }

    private function validateOptionAttribute(string $attribute): string
    {
        if (! preg_match('/^[A-Za-z_][A-Za-z0-9_.]*$/', $attribute)) {
            throw new LogicException("Invalid remote option attribute [{$attribute}].");
        }

        return $attribute;
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
