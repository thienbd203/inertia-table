<?php

namespace Musing\InertiaTable;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use LogicException;
use Musing\InertiaTable\Actions\Action;
use Musing\InertiaTable\Columns\Column;
use Musing\InertiaTable\Filters\Filter;
use Musing\InertiaTable\Support\TableReference;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/** @implements Arrayable<string, mixed> */
abstract class Table implements Arrayable
{
    protected ?string $name = null;

    protected ?string $defaultSort = null;

    protected ?int $perPage = null;

    /** @var array<int, int>|null */
    protected ?array $perPageOptions = null;

    /** @var array<int, string>|string|null */
    protected array|string|null $search = null;

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

    public function views(): ?Views
    {
        return null;
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
        $views = $this->views();
        $resolvedViews = $views?->resolve($this, $request);
        $searchable = $this->searchableColumns($columns);
        $perPageOptions = $this->perPageOptions();
        $state = $this->resolveState(
            $request,
            $columns,
            $filters,
            $resolvedViews['state'] ?? [],
        );

        if ($resolvedViews !== null) {
            $state = $state->withView($resolvedViews['resource']['selected']);
        }
        $query = $this->buildQuery($request, $state, $columns, $filters);

        $bulkActions = collect($actions)
            ->filter(fn (Action $action) => $action->isBulkAction())
            ->map(fn (Action $action) => $this->resolveAction($action))
            ->filter(fn (array $action) => $action['authorized'] && ! $action['hidden'])
            ->values()
            ->all();
        $selectableTotal = $bulkActions === []
            ? 0
            : $this->selectableQuery(clone $query->getEloquentBuilder())->count();

        return new TableResource(
            name: $this->name(),
            columns: array_map(fn (Column $column) => $column->toArray(), $columns),
            filters: array_map(fn (Filter $filter) => $filter->toArray(), $filters),
            actions: $bulkActions,
            search: array_map(fn (Column $column) => $column->attribute, $searchable),
            capabilities: [
                'searchable' => $searchable !== [],
                'selectable' => $bulkActions !== [],
                'paginated' => true,
                'hasSearch' => $searchable !== [],
                'hasFilters' => $filters !== [],
                'hasActions' => $actions !== [],
                'hasBulkActions' => $bulkActions !== [],
                'hasToggleableColumns' => collect($columns)->contains(fn (Column $column) => $column->isToggleable()),
            ],
            state: $state,
            results: $this->paginate(
                $query,
                $state,
                $columns,
                $actions,
                $selectableTotal,
            ),
            options: [
                'debounceTime' => (int) config('inertia-table.debounce', 300),
                'perPage' => $perPageOptions,
                'reloadProps' => $this->reloadProps,
            ],
            views: $resolvedViews['resource'] ?? null,
        );
    }

    public function toArray(): array
    {
        return $this->resolve()->toArray();
    }

    /** @param array<string, mixed> $payload */
    public function selection(array $payload): Selection
    {
        return Selection::fromArray($this, $payload);
    }

    /** @return Builder<Model> */
    public function selectableQuery(Builder $query): Builder
    {
        return $query;
    }

    public function isSelectable(Model $model): bool
    {
        return true;
    }

    /**
     * Normalize saved view state through the table's current declarations.
     *
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    public function normalizeViewState(array $state, bool $includeSearch = false): array
    {
        $columns = $this->validatedColumns();
        $request = Request::create('/', 'GET', [
            'table' => [$this->name() => $state],
        ]);
        $resolved = $this->resolveState(
            $request,
            $columns,
            $this->validatedFilters(),
        );
        $allowedColumns = array_map(
            fn (Column $column) => $column->attribute,
            $columns,
        );
        $pinned = is_array($state['pinnedColumns'] ?? null)
            ? $state['pinnedColumns']
            : [];
        $left = $this->normalizePinnedColumns($pinned['left'] ?? [], $allowedColumns);
        $right = array_values(array_diff(
            $this->normalizePinnedColumns($pinned['right'] ?? [], $allowedColumns),
            $left,
        ));
        $normalized = [
            'schemaVersion' => 1,
            'sort' => $resolved->sort,
            'filters' => $resolved->filters,
            'columns' => $resolved->columns,
            'pinnedColumns' => ['left' => $left, 'right' => $right],
            'perPage' => $resolved->perPage,
        ];

        if ($includeSearch) {
            $normalized['search'] = $resolved->search;
        }

        return $normalized;
    }

    public function action(string $key): ?Action
    {
        foreach ($this->validatedActions() as $action) {
            if ($action->key === $key) {
                return $action;
            }
        }

        return null;
    }

    /**
     * Normalize untrusted client state through this table's declared search and filters.
     *
     * @param  array<string, mixed>  $state
     * @return array{search: string, filters: array<string, array{enabled: bool, clause: string, value: mixed}>}
     */
    public function normalizeSelectionState(array $state): array
    {
        $request = Request::create('/', 'GET', [
            'table' => [
                $this->name() => [
                    'search' => $state['search'] ?? '',
                    'filters' => is_array($state['filters'] ?? null) ? $state['filters'] : [],
                ],
            ],
        ]);
        $resolved = $this->resolveState(
            $request,
            $this->validatedColumns(),
            $this->validatedFilters(),
        );

        return [
            'search' => $resolved->search,
            'filters' => $resolved->filters,
        ];
    }

    /** @return Builder<Model> */
    public function queryForSelection(Selection $selection): Builder
    {
        if ($selection->table !== $this->name()) {
            throw new LogicException('The selection does not belong to this table.');
        }

        if (! $selection->all) {
            $query = $this->query()->whereKey($selection->keys);
        } else {
            $columns = $this->validatedColumns();
            $filters = $this->validatedFilters();
            $request = Request::create('/');
            $state = new TableState(
                search: $selection->state['search'],
                sort: null,
                filters: $selection->state['filters'],
                columns: [],
                page: 1,
                perPage: $this->defaultPerPage(),
            );
            $query = $this->buildQuery($request, $state, $columns, $filters)
                ->getEloquentBuilder();
        }

        if ($selection->appliesSelectableScope()) {
            $query = $this->selectableQuery($query);
        }

        if ($selection->except !== []) {
            $query->whereKeyNot($selection->except);
        }

        return $query;
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
        $normalized = [];

        foreach ($filters as $filter) {
            $normalized[$filter->attribute] = array_key_exists($filter->attribute, $state->filters)
                ? $filter->normalizeState($state->filters[$filter->attribute])
                : $filter->defaultState();
        }

        return $state->withFilters($normalized);
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
        $searchable = $this->searchableColumns($columns);

        if ($searchable !== []) {
            $searchFilter = AllowedFilter::callback(
                '__search',
                function (Builder $query, mixed $value) use ($searchable) {
                    $search = trim(is_array($value) ? implode(',', $value) : (string) $value);

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
            );
            array_unshift($allowed, $searchFilter);
        }

        return $allowed;
    }

    protected function queryBuilderRequest(Request $request, TableState $state): Request
    {
        $queryBuilderRequest = Request::createFrom($request);
        $filter = collect($state->filters)
            ->filter(fn (array $filter) => $filter['enabled'])
            ->mapWithKeys(fn (array $filter, string $attribute) => [
                $attribute => json_encode([
                    'clause' => $filter['clause'],
                    'value' => $filter['value'],
                ], JSON_THROW_ON_ERROR),
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
     * @param  array<int, Column>  $columns
     * @param  array<int, Filter>  $filters
     */
    private function resolveState(
        Request $request,
        array $columns,
        array $filters,
        array $baseState = [],
    ): TableState {
        $request = $this->stateRequest($request, $baseState);
        $state = TableState::fromRequest(
            $request,
            $this->name(),
            $this->defaultSort,
            $this->defaultPerPage(),
            $this->perPageOptions(),
            collect($columns)->mapWithKeys(fn (Column $column) => [
                $column->attribute => $column->isVisibleByDefault(),
            ])->all(),
        );
        $state = $this->normalizeSort($columns, $state);

        if ($this->searchableColumns($columns) === []) {
            $state = $state->withSearch('');
        }

        return $this->normalizeColumns(
            $columns,
            $this->normalizeFilters($filters, $state),
        );
    }

    /** @param array<string, mixed> $baseState */
    private function stateRequest(Request $request, array $baseState): Request
    {
        if ($baseState === []) {
            return $request;
        }

        $query = $request->query->all();
        $tables = is_array($query['table'] ?? null) ? $query['table'] : [];
        $explicit = is_array($tables[$this->name()] ?? null)
            ? $tables[$this->name()]
            : [];
        $merged = array_replace($baseState, $explicit);

        foreach (['filters', 'columns'] as $group) {
            if (is_array($baseState[$group] ?? null) && is_array($explicit[$group] ?? null)) {
                $merged[$group] = array_replace($baseState[$group], $explicit[$group]);
            }
        }

        $tables[$this->name()] = $merged;
        $query['table'] = $tables;
        $stateRequest = Request::createFrom($request);
        $stateRequest->query->replace($query);

        return $stateRequest;
    }

    /**
     * @param  array<int, Column>  $columns
     * @param  array<int, Filter>  $filters
     */
    private function buildQuery(
        Request $request,
        TableState $state,
        array $columns,
        array $filters,
    ): QueryBuilder {
        return QueryBuilder::for(
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
    }

    /** @return array<int, int> */
    private function perPageOptions(): array
    {
        return $this->perPageOptions ?? config('inertia-table.per_page_options', [10, 25, 50, 100]);
    }

    private function defaultPerPage(): int
    {
        return $this->perPage ?? (int) config('inertia-table.per_page', 25);
    }

    /**
     * @param  array<int, Column>  $columns
     * @return array<int, Column>
     */
    protected function searchableColumns(array $columns): array
    {
        $attributes = $this->search === null
            ? collect($columns)->filter(fn (Column $column) => $column->isSearchable())->pluck('attribute')->all()
            : (array) $this->search;

        return array_values(array_filter(
            $columns,
            fn (Column $column) => in_array($column->attribute, $attributes, true),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    protected function paginate(
        QueryBuilder $query,
        TableState $state,
        array $columns,
        array $actions,
        int $selectableTotal,
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
            'selectableTotal' => $selectableTotal,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function transform(Model $model): array
    {
        return $model->toArray();
    }

    protected function rowUrl(Model $model): string|Url|null
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
        $data = $this->transform($model);

        foreach ($columns as $column) {
            if ($column->attribute !== '__actions') {
                $data[$column->attribute] = $column->resolveValue($model);
            }
        }

        $columnUrls = collect($columns)
            ->mapWithKeys(fn (Column $column) => [
                $column->attribute => $column->resolveUrl($model),
            ])
            ->filter()
            ->all();
        $cellMeta = collect($columns)
            ->mapWithKeys(fn (Column $column) => [
                $column->attribute => $column->resolveCellMeta($model),
            ])
            ->filter(fn (array $meta) => $meta !== [])
            ->all();
        $rowActions = collect($actions)
            ->filter(fn (Action $action) => $action->isRowAction())
            ->map(fn (Action $action) => $this->resolveAction($action, $model))
            ->filter(fn (array $action) => $action['authorized'] && ! $action['hidden'])
            ->values()
            ->all();

        return [
            ...$data,
            '_table' => [
                'key' => $model->getKey(),
                'selectable' => $this->isSelectable($model),
                'url' => $this->resolveUrl($this->rowUrl($model)),
                'columns' => $columnUrls,
                'cells' => $cellMeta,
                'actions' => $rowActions,
            ],
        ];
    }

    /** @return array<string, bool|string>|null */
    private function resolveUrl(string|Url|null $url): ?array
    {
        if (is_string($url)) {
            $url = Url::make()->to($url);
        }

        return $url instanceof Url && $url->hasUrl() && ! $url->isHidden()
            ? $url->toArray()
            : null;
    }

    /** @return array<string, mixed> */
    private function resolveAction(Action $action, ?Model $model = null): array
    {
        $handlerUrl = $action->hasHandler()
            ? url()->signedRoute(
                'inertia-table.actions',
                [
                    'table' => TableReference::encode(static::class),
                    'action' => $action->key,
                ],
                absolute: false,
            )
            : null;

        return $action->resolve($model, $handlerUrl);
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
        $keys = [];

        foreach ($actions as $action) {
            if (! $action instanceof Action) {
                throw new LogicException('Every table action must be an instance of '.Action::class.'.');
            }

            if (in_array($action->key, $keys, true)) {
                throw new LogicException("Table action keys must be unique; duplicate [{$action->key}] found.");
            }

            $keys[] = $action->key;
        }

        return array_values($actions);
    }

    /**
     * @param  array<int, string>  $allowed
     * @return array<int, string>
     */
    private function normalizePinnedColumns(mixed $columns, array $allowed): array
    {
        if (! is_array($columns)) {
            return [];
        }

        return array_values(array_unique(array_filter(
            $columns,
            fn (mixed $column) => is_string($column) && in_array($column, $allowed, true),
        )));
    }
}
