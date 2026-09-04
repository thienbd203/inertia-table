<?php

namespace Musing\InertiaTable;

use Closure;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Pagination\Cursor;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use LogicException;
use Musing\InertiaTable\Actions\Action;
use Musing\InertiaTable\Columns\Column;
use Musing\InertiaTable\Exports\Export;
use Musing\InertiaTable\Exports\ExportScope;
use Musing\InertiaTable\Filters\Filter;
use Musing\InertiaTable\Support\DataAttributes;
use Musing\InertiaTable\Support\TableReference;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/** @implements Arrayable<string, mixed> */
abstract class Table implements Arrayable
{
    protected ?string $name = null;

    protected ?string $defaultSort = null;

    protected ?int $perPage = null;

    protected ?bool $stickyHeader = null;

    protected ?bool $stickyBackdropFilter = null;

    protected ?bool $columnResizing = null;

    protected ?bool $columnReordering = null;

    protected bool $pagination = true;

    protected ?PaginationType $paginationType = null;

    protected ?int $debounceTime = null;

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

    /**
     * Build a lightweight table without declaring a dedicated Table class.
     *
     * @param  class-string<Model>|Builder<Model>  $resource
     * @param  array<int, mixed>  $columns
     * @param  array<int, mixed>  $filters
     * @param  array<int, string>|string|null  $search
     * @param  array<int, mixed>|null  $perPageOptions
     * @param  Closure(Model): mixed|null  $transformModelUsing
     * @param  Closure(QueryBuilder): mixed|null  $withQueryBuilder
     */
    public static function build(
        string|Builder $resource,
        array $columns = [],
        array $filters = [],
        array|string|null $search = [],
        string $name = 'default',
        bool $pagination = true,
        ?int $debounceTime = null,
        ?array $perPageOptions = null,
        ?string $defaultSort = null,
        ?Closure $transformModelUsing = null,
        ?Closure $withQueryBuilder = null,
        ?EmptyState $emptyState = null,
        ?bool $stickyHeader = null,
        ?int $defaultPerPage = null,
        ?PaginationType $paginationType = null,
        ?bool $stickyBackdropFilter = null,
        ?bool $columnResizing = null,
        ?bool $columnReordering = null,
    ): AnonymousTable {
        return new AnonymousTable(
            resource: $resource,
            columns: $columns,
            filters: $filters,
            search: $search,
            name: $name,
            pagination: $pagination,
            debounceTime: $debounceTime,
            perPageOptions: $perPageOptions,
            defaultSort: $defaultSort,
            transformModelUsing: $transformModelUsing,
            withQueryBuilder: $withQueryBuilder,
            emptyState: $emptyState,
            stickyHeader: $stickyHeader,
            defaultPerPage: $defaultPerPage,
            paginationType: $paginationType,
            stickyBackdropFilter: $stickyBackdropFilter,
            columnResizing: $columnResizing,
            columnReordering: $columnReordering,
        );
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

    /** @return array<int, mixed> */
    public function exports(): array
    {
        return [];
    }

    public function views(): ?Views
    {
        return null;
    }

    public function emptyState(): ?EmptyState
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

    public function stickyHeader(bool $sticky = true): static
    {
        $this->stickyHeader = $sticky;

        return $this;
    }

    public function stickyBackdropFilter(bool $enabled = true): static
    {
        $this->stickyBackdropFilter = $enabled;

        return $this;
    }

    public function columnResizing(bool $enabled = true): static
    {
        $this->columnResizing = $enabled;

        return $this;
    }

    public function columnReordering(bool $enabled = true): static
    {
        $this->columnReordering = $enabled;

        return $this;
    }

    public function paginationType(PaginationType $type): static
    {
        $this->paginationType = $type;

        return $this;
    }

    public function resolvedPaginationType(): PaginationType
    {
        if ($this->paginationType instanceof PaginationType) {
            return $this->paginationType;
        }

        return PaginationType::tryFrom((string) config('inertia-table.pagination_type', 'full'))
            ?? PaginationType::Full;
    }

    public function resolve(?Request $request = null): TableResource
    {
        $request ??= request();
        $columns = $this->validatedColumns();
        $filters = $this->validatedFilters();
        $actions = $this->validatedActions();
        $exports = $this->validatedExports();
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
        $paginationType = $this->resolvedPaginationType();
        $state = $this->normalizePaginationState($state, $columns, $paginationType);
        $query = $this->buildQuery($request, $state, $columns, $filters);

        $bulkActions = collect($actions)
            ->filter(fn (Action $action) => $action->isBulkAction())
            ->map(fn (Action $action) => $this->resolveAction($action))
            ->filter(fn (array $action) => $action['authorized'] && ! $action['hidden'])
            ->values()
            ->all();
        $authorizedExports = collect($exports)
            ->filter(fn (Export $export) => $export->isAuthorized($request, $this));
        $resolvedExports = $authorizedExports
            ->map(fn (Export $export) => $this->resolveExport($export, $request))
            ->values()
            ->all();
        $hasSelectionExport = $authorizedExports->contains(
            fn (Export $export) => $export->scope() === ExportScope::Selected,
        );
        $selectable = $bulkActions !== [] || $hasSelectionExport;
        $selectableTotal = ! $selectable
            ? 0
            : $this->selectableQuery(clone $query->getEloquentBuilder())->count();
        $results = $this->paginate(
            $query,
            $state,
            $columns,
            $actions,
            $selectableTotal,
        );
        $emptyState = $this->emptyState();
        $resolvedEmptyState = $emptyState !== null
            && $results['data'] === []
            && ! $this->queryForAll()->exists()
                ? $emptyState->toArray()
                : null;

        return new TableResource(
            name: $this->name(),
            columns: array_map(fn (Column $column) => $column->toArray(), $columns),
            filters: array_map(fn (Filter $filter) => $filter->toArray(), $filters),
            actions: $bulkActions,
            search: array_map(fn (Column $column) => $column->attribute, $searchable),
            capabilities: [
                'searchable' => $searchable !== [],
                'selectable' => $selectable,
                'paginated' => $this->pagination,
                'hasSearch' => $searchable !== [],
                'hasFilters' => $filters !== [],
                'hasActions' => $actions !== [],
                'hasBulkActions' => $bulkActions !== [],
                'hasExports' => $resolvedExports !== [],
                'hasToggleableColumns' => collect($columns)->contains(fn (Column $column) => $column->isToggleable()),
                'hasStickableColumns' => collect($columns)->contains(fn (Column $column) => $column->isStickable()),
                'hasResizableColumns' => $this->resolvedColumnResizing()
                    && collect($columns)->contains(fn (Column $column) => $column->isResizable()),
                'hasReorderableColumns' => $this->resolvedColumnReordering()
                    && collect($columns)->contains(fn (Column $column) => $column->isReorderable()),
                'hasEmptyState' => $emptyState !== null,
            ],
            state: $state,
            results: $results,
            options: [
                'debounceTime' => $this->debounceTime ?? (int) config('inertia-table.debounce', 300),
                'perPage' => $perPageOptions,
                'paginationType' => $paginationType->value,
                'reloadProps' => $this->reloadProps,
                'stickyHeader' => $this->stickyHeader ?? false,
                'stickyBackdropFilter' => $this->stickyBackdropFilter
                    ?? (bool) config('inertia-table.sticky.backdrop_filter', true),
                'columnResizing' => $this->resolvedColumnResizing(),
                'columnReordering' => $this->resolvedColumnReordering(),
            ],
            views: $resolvedViews['resource'] ?? null,
            exports: $resolvedExports,
            emptyState: $resolvedEmptyState,
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

    public function export(string $key): ?Export
    {
        foreach ($this->validatedExports() as $export) {
            if ($export->key === $key) {
                return $export;
            }
        }

        return null;
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
        $normalized = [
            'schemaVersion' => 2,
            'sort' => $resolved->sort,
            'filters' => $resolved->filters,
            'columns' => $resolved->columns,
            'pinnedColumns' => $resolved->pinnedColumns,
            'columnOrder' => $resolved->columnOrder,
            'columnWidths' => $resolved->columnWidths,
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
     * @return array{search: string, sort: string|null, filters: array<string, array{enabled: bool, clause: string, value: mixed}>}
     */
    public function normalizeSelectionState(array $state): array
    {
        $request = Request::create('/', 'GET', [
            'table' => [
                $this->name() => [
                    'search' => $state['search'] ?? '',
                    'sort' => $state['sort'] ?? null,
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
            'sort' => $resolved->sort,
            'filters' => $resolved->filters,
        ];
    }

    /**
     * Build an unpaginated query from normalized table state.
     *
     * @param  array<string, mixed>  $state
     * @return Builder<Model>
     */
    public function queryForState(array $state): Builder
    {
        $columns = $this->validatedColumns();
        $filters = $this->validatedFilters();
        $request = Request::create('/', 'GET', [
            'table' => [$this->name() => $state],
        ]);
        $resolved = $this->resolveState($request, $columns, $filters);

        return $this->buildQuery($request, $resolved, $columns, $filters)
            ->getEloquentBuilder();
    }

    /** @return Builder<Model> */
    public function queryForAll(): Builder
    {
        $request = Request::create('/', 'GET');
        $query = $this->withQueryBuilder(QueryBuilder::for($this->query(), $request));

        return $this->stabilizeJoinedQuery($query, $this->hasJoins($query))->getEloquentBuilder();
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<int, Column>
     */
    public function columnsForExport(Export $export, array $state): array
    {
        $columns = array_values(array_filter(
            $this->validatedColumns(),
            fn (Column $column) => $column->isExportable(),
        ));

        if (! $export->usesVisibleColumns() && ! $export->usesUserColumnOrder()) {
            return $columns;
        }

        $layout = $this->normalizeViewState($state);

        if ($export->usesVisibleColumns()) {
            $columns = array_values(array_filter(
                $columns,
                fn (Column $column) => $layout['columns'][$column->attribute] ?? false,
            ));
        }

        if ($export->usesUserColumnOrder()) {
            $positions = array_flip($layout['columnOrder']);
            usort(
                $columns,
                fn (Column $left, Column $right) => ($positions[$left->attribute] ?? PHP_INT_MAX)
                    <=> ($positions[$right->attribute] ?? PHP_INT_MAX),
            );
        }

        return $columns;
    }

    /** @return Builder<Model> */
    public function queryForSelection(Selection $selection): Builder
    {
        if ($selection->table !== $this->name()) {
            throw new LogicException('The selection does not belong to this table.');
        }

        if (! $selection->all) {
            $query = $this->queryForAll()->whereKey($selection->keys);
            $sort = $selection->state['sort'];

            if ($sort !== null) {
                $attribute = ltrim($sort, '-');
                $column = collect($this->validatedColumns())
                    ->first(fn (Column $column) => $column->attribute === $attribute && $column->isSortable());
                $column?->applySort($query, str_starts_with($sort, '-') ? 'desc' : 'asc');
            }
        } else {
            $query = $this->queryForState($selection->state);
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

    /** @param array<int, Column> $columns */
    protected function normalizeColumnLayout(array $columns, TableState $state): TableState
    {
        $declaredOrder = array_map(fn (Column $column) => $column->attribute, $columns);
        $definitions = [];

        foreach ($columns as $column) {
            $definitions[$column->attribute] = $column;
        }
        $requestedOrder = array_values(array_unique(array_filter(
            $state->columnOrder,
            fn (string $attribute) => in_array($attribute, $declaredOrder, true),
        )));
        $requestedOrder = [...$requestedOrder, ...array_values(array_diff($declaredOrder, $requestedOrder))];

        if (! $this->resolvedColumnReordering()) {
            $columnOrder = $declaredOrder;
        } else {
            $side = fn (string $attribute): string => in_array($attribute, $state->pinnedColumns['left'], true)
                ? 'left'
                : (in_array($attribute, $state->pinnedColumns['right'], true) ? 'right' : 'none');
            $reorderable = ['left' => [], 'none' => [], 'right' => []];

            foreach ($requestedOrder as $attribute) {
                if ($definitions[$attribute]->isReorderable()) {
                    $reorderable[$side($attribute)][] = $attribute;
                }
            }

            $columnOrder = [];

            foreach ($columns as $column) {
                if (! $column->isReorderable()) {
                    $columnOrder[] = $column->attribute;

                    continue;
                }

                $group = $side($column->attribute);
                $columnOrder[] = array_shift($reorderable[$group]) ?? $column->attribute;
            }
        }

        $widths = [];

        foreach ($columns as $column) {
            $requested = $state->columnWidths[$column->attribute] ?? null;

            if ($this->resolvedColumnResizing() && $column->isResizable() && is_int($requested)) {
                $widths[$column->attribute] = $column->clampWidth($requested);
            } elseif ($column->defaultWidth() !== null) {
                $widths[$column->attribute] = $column->defaultWidth();
            }
        }

        return $state
            ->withColumnOrder($columnOrder)
            ->withColumnWidths($widths);
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

    protected function withQueryBuilder(QueryBuilder $query): QueryBuilder
    {
        return $query;
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
            $this->defaultPinnedColumns($columns),
            array_map(fn (Column $column) => $column->attribute, $columns),
            collect($columns)
                ->filter(fn (Column $column) => $column->defaultWidth() !== null)
                ->mapWithKeys(fn (Column $column) => [$column->attribute => $column->defaultWidth()])
                ->all(),
        );
        $state = $this->normalizeSort($columns, $state);

        if ($this->searchableColumns($columns) === []) {
            $state = $state->withSearch('');
        }

        return $this->normalizeColumnLayout($columns, $this->normalizePinnedState($columns, $this->normalizeColumns(
            $columns,
            $this->normalizeFilters($filters, $state),
        )));
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

        foreach (['filters', 'columns', 'pinnedColumns', 'columnWidths'] as $group) {
            if (is_array($baseState[$group] ?? null) && is_array($explicit[$group] ?? null)) {
                $merged[$group] = $group === 'columnWidths'
                    && filter_var($explicit[$group]['__reset'] ?? false, FILTER_VALIDATE_BOOL)
                        ? array_diff_key($explicit[$group], ['__reset' => true])
                        : array_replace($baseState[$group], $explicit[$group]);
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
        $query = $this->withQueryBuilder(QueryBuilder::for(
            $this->query(),
            $this->queryBuilderRequest($request, $state),
        ));
        $deduplicate = $this->hasJoins($query);
        $query->allowedFilters(...$this->allowedFilters($columns, $filters))
            ->allowedSorts(...array_map(
                fn (Column $column) => $column->allowedSort(),
                array_values(array_filter(
                    $columns,
                    fn (Column $column) => $column->isSortable(),
                )),
            ));

        return $this->stabilizeJoinedQuery($query, $deduplicate);
    }

    private function stabilizeJoinedQuery(QueryBuilder $query, bool $deduplicate): QueryBuilder
    {
        $eloquent = $query->getEloquentBuilder();

        if (! $deduplicate) {
            return $query;
        }

        if ($eloquent->getQuery()->columns === null) {
            $eloquent->select($eloquent->getModel()->qualifyColumn('*'));
        }

        $eloquent->distinct($eloquent->getModel()->getQualifiedKeyName());

        return $query;
    }

    private function hasJoins(QueryBuilder $query): bool
    {
        return $query->getEloquentBuilder()->getQuery()->joins !== null;
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

    private function resolvedColumnResizing(): bool
    {
        return $this->columnResizing
            ?? (bool) config('inertia-table.columns.resizable', true);
    }

    private function resolvedColumnReordering(): bool
    {
        return $this->columnReordering
            ?? (bool) config('inertia-table.columns.reorderable', true);
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
        if (! $this->pagination) {
            $models = $query->get();
            $total = $models->count();

            return [
                'data' => $this->serializeModels($models, $columns, $actions),
                'currentPage' => 1,
                'from' => $total > 0 ? 1 : null,
                'lastPage' => 1,
                'links' => [],
                'perPage' => $total,
                'to' => $total > 0 ? $total : null,
                'total' => $total,
                'selectableTotal' => $selectableTotal,
                'hasPreviousPage' => false,
                'hasNextPage' => false,
                'previousCursor' => null,
                'nextCursor' => null,
            ];
        }

        return match ($this->resolvedPaginationType()) {
            PaginationType::Full => $this->paginateFully($query, $state, $columns, $actions, $selectableTotal),
            PaginationType::Simple => $this->paginateSimply($query, $state, $columns, $actions, $selectableTotal),
            PaginationType::Cursor => $this->paginateByCursor($query, $state, $columns, $actions, $selectableTotal),
        };
    }

    /**
     * @param  array<int, Column>  $columns
     * @param  array<int, Action>  $actions
     * @return array<string, mixed>
     */
    private function paginateFully(
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
            'data' => $this->serializeModels($paginator->items(), $columns, $actions),
            'currentPage' => $paginator->currentPage(),
            'from' => $paginator->firstItem(),
            'lastPage' => $paginator->lastPage(),
            'links' => $this->paginationLinks($paginator),
            'perPage' => $paginator->perPage(),
            'to' => $paginator->lastItem(),
            'total' => $paginator->total(),
            'selectableTotal' => $selectableTotal,
            'hasPreviousPage' => ! $paginator->onFirstPage(),
            'hasNextPage' => $paginator->hasMorePages(),
            'previousCursor' => null,
            'nextCursor' => null,
        ];
    }

    /**
     * @param  array<int, Column>  $columns
     * @param  array<int, Action>  $actions
     * @return array<string, mixed>
     */
    private function paginateSimply(
        QueryBuilder $query,
        TableState $state,
        array $columns,
        array $actions,
        int $selectableTotal,
    ): array {
        /** @var Paginator<int, Model> $paginator */
        $paginator = $query->simplePaginate(
            perPage: $state->perPage,
            pageName: "table[{$this->name()}][page]",
            page: $state->page,
        )->withQueryString();

        return [
            'data' => $this->serializeModels($paginator->items(), $columns, $actions),
            'currentPage' => $paginator->currentPage(),
            'from' => $paginator->firstItem(),
            'lastPage' => null,
            'links' => [],
            'perPage' => $paginator->perPage(),
            'to' => $paginator->lastItem(),
            'total' => null,
            'selectableTotal' => $selectableTotal,
            'hasPreviousPage' => ! $paginator->onFirstPage(),
            'hasNextPage' => $paginator->hasMorePages(),
            'previousCursor' => null,
            'nextCursor' => null,
        ];
    }

    /**
     * @param  array<int, Column>  $columns
     * @param  array<int, Action>  $actions
     * @return array<string, mixed>
     */
    private function paginateByCursor(
        QueryBuilder $query,
        TableState $state,
        array $columns,
        array $actions,
        int $selectableTotal,
    ): array {
        $this->stabilizeCursorOrder($query);

        /** @var CursorPaginator<int, Model> $paginator */
        $paginator = $query->cursorPaginate(
            perPage: $state->perPage,
            cursorName: "table[{$this->name()}][cursor]",
            cursor: Cursor::fromEncoded($state->cursor),
        )->withQueryString();

        return [
            'data' => $this->serializeModels($paginator->items(), $columns, $actions),
            'currentPage' => null,
            'from' => null,
            'lastPage' => null,
            'links' => [],
            'perPage' => $paginator->perPage(),
            'to' => null,
            'total' => null,
            'selectableTotal' => $selectableTotal,
            'hasPreviousPage' => ! $paginator->onFirstPage(),
            'hasNextPage' => $paginator->hasMorePages(),
            'previousCursor' => $paginator->previousCursor()?->encode(),
            'nextCursor' => $paginator->nextCursor()?->encode(),
        ];
    }

    /**
     * @param  iterable<int, Model>  $models
     * @param  array<int, Column>  $columns
     * @param  array<int, Action>  $actions
     * @return array<int, array<string, mixed>>
     */
    private function serializeModels(iterable $models, array $columns, array $actions): array
    {
        return collect($models)
            ->map(fn (Model $model) => $this->serializeRow($model, $columns, $actions))
            ->values()
            ->all();
    }

    /** @param array<int, Column> $columns */
    private function normalizePaginationState(
        TableState $state,
        array $columns,
        PaginationType $type,
    ): TableState {
        if (! $this->pagination || $type !== PaginationType::Cursor) {
            return $state->withCursor(null);
        }

        if ($state->sort === null) {
            throw new LogicException('Cursor pagination requires a default or requested sort.');
        }

        $attribute = ltrim($state->sort, '-');
        $column = collect($columns)->first(
            fn (Column $column) => $column->attribute === $attribute && $column->isSortable(),
        );

        if (! $column instanceof Column || str_contains($attribute, '.')) {
            throw new LogicException('Cursor pagination only supports sortable columns on the base table.');
        }

        return $state->withPage(1);
    }

    private function stabilizeCursorOrder(QueryBuilder $query): void
    {
        $eloquent = $query->getEloquentBuilder();
        $key = $eloquent->getModel()->getKeyName();
        $qualifiedKey = $eloquent->getModel()->qualifyColumn($key);
        $orders = $eloquent->getQuery()->orders ?? [];
        $ordersByKey = false;

        foreach ($orders as $order) {
            $column = is_array($order) && isset($order['direction'])
                ? ($order['column'] ?? null)
                : null;

            if (! is_string($column)) {
                throw new LogicException('Cursor pagination requires plain column sorts; raw or expression sorts are not supported.');
            }

            if (in_array($column, [$key, $qualifiedKey], true)) {
                $ordersByKey = true;
            }
        }

        if ($ordersByKey) {
            return;
        }

        $eloquent->orderBy($qualifiedKey);
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
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function dataAttributesForModel(Model $model, array $data): array
    {
        return [];
    }

    /**
     * @param  array<int, Column>  $columns
     * @param  array<int, Action>  $actions
     * @return array<string, mixed>
     */
    protected function serializeRow(Model $model, array $columns, array $actions): array
    {
        $data = $this->transform($model);
        $dataAttributes = DataAttributes::normalize(
            $this->dataAttributesForModel($model, $data),
            ['row-clickable', 'selected'],
        );

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
                'dataAttributes' => $dataAttributes,
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

    /** @return array<string, mixed> */
    private function resolveExport(Export $export, Request $request): array
    {
        $endpoint = url()->signedRoute(
            'inertia-table.execute-export',
            [
                'table' => TableReference::encode(static::class),
                'export' => $export->key,
            ],
            absolute: false,
        );

        return $export->resolve($request, $this, $endpoint);
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

    /** @return array<int, Export> */
    private function validatedExports(): array
    {
        $exports = $this->exports();
        $keys = [];

        foreach ($exports as $export) {
            if (! $export instanceof Export) {
                throw new LogicException('Every table export must be an instance of '.Export::class.'.');
            }

            if (in_array($export->key, $keys, true)) {
                throw new LogicException("Table export keys must be unique; duplicate [{$export->key}] found.");
            }

            $keys[] = $export->key;
        }

        return array_values($exports);
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

        $requested = array_filter(
            $columns,
            fn (mixed $column) => is_string($column),
        );

        return array_values(array_filter(
            $allowed,
            fn (string $column) => in_array($column, $requested, true),
        ));
    }

    /**
     * @param  array<int, Column>  $columns
     * @return array{left: array<int, string>, right: array<int, string>}
     */
    private function defaultPinnedColumns(array $columns): array
    {
        $lastIndex = max(count($columns) - 1, 0);
        $left = [];
        $right = [];

        foreach ($columns as $index => $column) {
            if (! $column->isSticky()) {
                continue;
            }

            if ($index <= $lastIndex / 2) {
                $left[] = $column->attribute;
            } else {
                $right[] = $column->attribute;
            }
        }

        return ['left' => $left, 'right' => $right];
    }

    /** @param array<int, Column> $columns */
    private function normalizePinnedState(array $columns, TableState $state): TableState
    {
        $allowed = collect($columns)
            ->filter(fn (Column $column) => $column->isStickable())
            ->pluck('attribute')
            ->all();
        $permanent = $this->defaultPinnedColumns($columns);
        $permanentColumns = [...$permanent['left'], ...$permanent['right']];
        $left = array_values(array_diff(
            $this->normalizePinnedColumns($state->pinnedColumns['left'], $allowed),
            $permanentColumns,
        ));
        $right = array_values(array_diff(
            $this->normalizePinnedColumns($state->pinnedColumns['right'], $allowed),
            $permanentColumns,
            $left,
        ));

        return $state->withPinnedColumns([
            'left' => array_values(array_unique([...$permanent['left'], ...$left])),
            'right' => array_values(array_unique([...$right, ...$permanent['right']])),
        ]);
    }
}
