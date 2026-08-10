<?php

namespace Toolbelt\InertiaTable;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use LogicException;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;
use Toolbelt\InertiaTable\Actions\Action;
use Toolbelt\InertiaTable\Columns\Column;
use Toolbelt\InertiaTable\Filters\Filter;

/** @implements Arrayable<string, mixed> */
abstract class Table implements Arrayable
{
    protected ?string $name = null;

    protected ?string $defaultSort = null;

    /** @var array<int, string> */
    protected array $reloadProps = [];

    public static function make(): static
    {
        return app(static::class);
    }

    abstract public function query(): Builder;

    /**
     * @return array<int, mixed>
     */
    abstract public function columns(): array;

    /**
     * @return array<int, mixed>
     */
    public function filters(): array
    {
        return [];
    }

    /** @return array<int, mixed> */
    public function actions(): array
    {
        return [];
    }

    public function name(): string
    {
        return $this->name ?? str(class_basename(static::class))
            ->remove('Table')
            ->snake()
            ->toString();
    }

    /**
     * @param  array<int, string>|string  $props
     */
    public function reloadProps(array|string $props): static
    {
        $this->reloadProps = array_values(array_unique([
            ...$this->reloadProps,
            ...(array) $props,
        ]));

        return $this;
    }

    public function resolve(?Request $request = null): TableResource
    {
        $request ??= request();
        $columns = $this->validatedColumns();
        $filters = $this->validatedFilters();
        $actions = $this->validatedActions();
        $perPageOptions = config('inertia-table.per_page_options', [10, 25, 50, 100]);
        $state = TableState::fromRequest(
            $request,
            $this->name(),
            $this->defaultSort,
            (int) config('inertia-table.per_page', 25),
            $perPageOptions,
            collect($columns)->mapWithKeys(fn (Column $column) => [
                $column->attribute => $column->isVisibleByDefault(),
            ])->all(),
        );
        $state = $this->normalizeSort($columns, $state);
        $state = $this->normalizeFilters($filters, $state);
        $state = $this->normalizeColumns($columns, $state);
        $query = QueryBuilder::for(
            $this->query(),
            $this->queryBuilderRequest($request, $state),
        )
            ->allowedFilters(...$this->allowedFilters($columns, $filters))
            ->allowedSorts(...array_map(
                fn (Column $column) => $column->allowedSort(),
                array_values(array_filter(
                    $columns,
                    fn (Column $column) => $column->isSortable(),
                )),
            ));

        $bulkActions = collect($actions)
            ->filter(fn (Action $action) => $action->isBulkAction())
            ->map(fn (Action $action) => $action->resolve())
            ->filter(fn (array $action) => $action['authorized'] && $action['endpoint'] !== null)
            ->values()
            ->all();

        return new TableResource(
            name: $this->name(),
            columns: array_map(fn (Column $column) => $column->toArray(), $columns),
            filters: array_map(fn (Filter $filter) => $filter->toArray(), $filters),
            actions: $bulkActions,
            capabilities: [
                'searchable' => collect($columns)->contains(fn (Column $column) => $column->isSearchable()),
                'selectable' => $bulkActions !== [],
                'paginated' => true,
            ],
            state: $state,
            results: $this->paginate($query, $state, $columns, $actions),
            options: [
                'debounceTime' => (int) config('inertia-table.debounce', 300),
                'perPage' => $perPageOptions,
                'reloadProps' => $this->reloadProps,
            ],
        );
    }

    public function toArray(): array
    {
        return $this->resolve()->toArray();
    }

    /**
     * @param  array<int, Column>  $columns
     */
    protected function normalizeSort(array $columns, TableState $state): TableState
    {
        $sortable = [];

        foreach ($columns as $column) {
            if ($column->isSortable()) {
                $sortable[$column->attribute] = $column;
            }
        }

        $sort = $state->sort;
        $attribute = $sort ? ltrim($sort, '-') : null;

        if (! $attribute || ! isset($sortable[$attribute])) {
            $sort = $this->defaultSort;
            $attribute = $sort ? ltrim($sort, '-') : null;
        }

        if (! $attribute || ! isset($sortable[$attribute])) {
            return $state->withSort(null);
        }

        return $state->withSort($sort);
    }

    /**
     * @param  array<int, Filter>  $filters
     */
    protected function normalizeFilters(array $filters, TableState $state): TableState
    {
        $active = [];

        foreach ($filters as $filter) {
            if (! array_key_exists($filter->attribute, $state->filters)) {
                continue;
            }

            $value = $filter->normalizeState($state->filters[$filter->attribute]);

            if ($value === null) {
                continue;
            }

            $active[$filter->attribute] = $value;
        }

        return $state->withFilters($active);
    }

    /** @param array<int, Column> $columns */
    protected function normalizeColumns(array $columns, TableState $state): TableState
    {
        $visibility = [];

        foreach ($columns as $column) {
            $visibility[$column->attribute] = $column->isToggleable()
                ? ($state->columns[$column->attribute] ?? $column->isVisibleByDefault())
                : true;
        }

        return $state->withColumns($visibility);
    }

    /**
     * @param  array<int, Column>  $columns
     * @param  array<int, Filter>  $filters
     * @return array<int, AllowedFilter>
     */
    protected function allowedFilters(array $columns, array $filters): array
    {
        $allowed = array_map(
            fn (Filter $filter) => $filter->allowedFilter(),
            $filters,
        );
        $searchable = array_values(array_filter(
            $columns,
            fn (Column $column) => $column->isSearchable(),
        ));

        if ($searchable !== []) {
            $searchFilter = AllowedFilter::callback(
                '__search',
                function (Builder $query, mixed $value) use ($searchable) {
                    $search = trim((string) $value);

                    if ($search === '') {
                        return;
                    }

                    $query->where(function (Builder $query) use ($searchable, $search) {
                        foreach ($searchable as $index => $column) {
                            $column->applySearch(
                                $query,
                                $search,
                                $index === 0 ? 'and' : 'or',
                            );
                        }
                    });
                },
            )->delimiter('');
            array_unshift($allowed, $searchFilter);
        }

        return $allowed;
    }

    protected function queryBuilderRequest(Request $request, TableState $state): Request
    {
        $queryBuilderRequest = Request::createFrom($request);
        $filter = collect($state->filters)
            ->mapWithKeys(fn (array $filter, string $attribute) => [
                $attribute => $filter['value'],
            ])
            ->all();

        if ($state->search !== '') {
            $filter['__search'] = $state->search;
        }

        $queryBuilderRequest->query->replace(array_filter([
            'filter' => $filter,
            'sort' => $state->sort,
        ], fn (mixed $value) => $value !== null && $value !== []));

        return $queryBuilderRequest;
    }

    /**
     * @return array<string, mixed>
     */
    protected function paginate(
        QueryBuilder $query,
        TableState $state,
        array $columns,
        array $actions,
    ): array {
        $paginator = $query->paginate(
            perPage: $state->perPage,
            pageName: "table[{$this->name()}][page]",
            page: $state->page,
        )->withQueryString();

        return [
            'data' => collect($paginator->items())
                ->map(fn (Model $model) => $this->serializeRow($model, $columns, $actions))
                ->values()
                ->all(),
            'currentPage' => $paginator->currentPage(),
            'from' => $paginator->firstItem(),
            'lastPage' => $paginator->lastPage(),
            'links' => $this->paginationLinks($paginator),
            'perPage' => $paginator->perPage(),
            'to' => $paginator->lastItem(),
            'total' => $paginator->total(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function transform(Model $model): array
    {
        return $model->toArray();
    }

    protected function rowUrl(Model $model): ?string
    {
        return null;
    }

    /**
     * @param  array<int, Column>  $columns
     * @param  array<int, Action>  $actions
     * @return array<string, mixed>
     */
    protected function serializeRow(Model $model, array $columns, array $actions): array
    {
        $columnUrls = collect($columns)
            ->mapWithKeys(fn (Column $column) => [
                $column->attribute => $column->resolveUrl($model),
            ])
            ->filter()
            ->all();
        $rowActions = collect($actions)
            ->filter(fn (Action $action) => $action->isRowAction())
            ->map(fn (Action $action) => $action->resolve($model))
            ->filter(fn (array $action) => $action['authorized'] && $action['endpoint'] !== null)
            ->values()
            ->all();

        return [
            ...$this->transform($model),
            '_table' => [
                'url' => $this->rowUrl($model),
                'columns' => $columnUrls,
                'actions' => $rowActions,
            ],
        ];
    }

    /**
     * @return array<int, array{url: string|null, label: string, active: bool}>
     */
    protected function paginationLinks(LengthAwarePaginator $paginator): array
    {
        return $paginator->linkCollection()
            ->map(fn (array $link) => [
                'url' => $link['url'],
                'label' => $link['label'],
                'active' => $link['active'],
            ])
            ->all();
    }

    /**
     * @return array<int, Column>
     */
    private function validatedColumns(): array
    {
        $columns = $this->columns();

        foreach ($columns as $column) {
            if (! $column instanceof Column) {
                throw new LogicException('Every table column must extend '.Column::class.'.');
            }
        }

        return array_values($columns);
    }

    /**
     * @return array<int, Filter>
     */
    private function validatedFilters(): array
    {
        $filters = $this->filters();

        foreach ($filters as $filter) {
            if (! $filter instanceof Filter) {
                throw new LogicException('Every table filter must extend '.Filter::class.'.');
            }
        }

        return array_values($filters);
    }

    /** @return array<int, Action> */
    private function validatedActions(): array
    {
        $actions = $this->actions();

        foreach ($actions as $action) {
            if (! $action instanceof Action) {
                throw new LogicException('Every table action must be an instance of '.Action::class.'.');
            }
        }

        return array_values($actions);
    }
}
