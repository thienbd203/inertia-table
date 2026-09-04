<?php

namespace Musing\InertiaTable;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use LogicException;
use Spatie\QueryBuilder\QueryBuilder;

final class AnonymousTable extends Table
{
    /** @var class-string<Model>|Builder<Model> */
    private string|Builder $resource;

    /** @var array<int, mixed> */
    private array $tableColumns;

    /** @var array<int, mixed> */
    private array $tableFilters;

    /** @var Closure(Model): mixed|null */
    private ?Closure $transformModelUsing;

    /** @var Closure(QueryBuilder): mixed|null */
    private ?Closure $queryBuilderUsing;

    private ?EmptyState $tableEmptyState;

    /**
     * @param  class-string<Model>|Builder<Model>  $resource
     * @param  array<int, mixed>  $columns
     * @param  array<int, mixed>  $filters
     * @param  array<int, string>|string|null  $search
     * @param  array<int, mixed>|null  $perPageOptions
     * @param  Closure(Model): mixed|null  $transformModelUsing
     * @param  Closure(QueryBuilder): mixed|null  $withQueryBuilder
     */
    public function __construct(
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
    ) {
        if (is_string($resource) && ! is_subclass_of($resource, Model::class)) {
            throw new LogicException('Anonymous table resources must be an Eloquent model class or builder.');
        }

        if ($debounceTime !== null && $debounceTime < 0) {
            throw new LogicException('Anonymous table debounce time must be zero or greater.');
        }

        if ($defaultPerPage !== null && $defaultPerPage < 1) {
            throw new LogicException('Anonymous table default per-page value must be greater than zero.');
        }

        $validatedPerPageOptions = null;

        if ($perPageOptions !== null) {
            $validatedPerPageOptions = [];

            foreach ($perPageOptions as $option) {
                if (! is_int($option) || $option < 1) {
                    throw new LogicException('Anonymous table per-page options must be positive integers.');
                }

                $validatedPerPageOptions[] = $option;
            }

            $validatedPerPageOptions = array_values(array_unique($validatedPerPageOptions));

            if ($validatedPerPageOptions === []) {
                throw new LogicException('Anonymous tables must declare at least one per-page option.');
            }
        }

        if ($defaultPerPage !== null
            && $validatedPerPageOptions !== null
            && ! in_array($defaultPerPage, $validatedPerPageOptions, true)) {
            throw new LogicException('Anonymous table default per-page value must be one of its per-page options.');
        }

        $this->resource = $resource;
        $this->tableColumns = $columns;
        $this->tableFilters = $filters;
        $this->search = $search;
        $this->name = $name;
        $this->pagination = $pagination;
        $this->debounceTime = $debounceTime;
        $this->perPageOptions = $validatedPerPageOptions;
        $this->defaultSort = $defaultSort;
        $this->transformModelUsing = $transformModelUsing;
        $this->queryBuilderUsing = $withQueryBuilder;
        $this->tableEmptyState = $emptyState;
        $this->stickyHeader = $stickyHeader;
        $this->perPage = $defaultPerPage;
        $this->paginationType = $paginationType;
        $this->stickyBackdropFilter = $stickyBackdropFilter;
        $this->columnResizing = $columnResizing;
        $this->columnReordering = $columnReordering;
    }

    public function query(): Builder
    {
        if ($this->resource instanceof Builder) {
            return clone $this->resource;
        }

        return $this->resource::query();
    }

    public function columns(): array
    {
        return $this->tableColumns;
    }

    public function filters(): array
    {
        return $this->tableFilters;
    }

    public function emptyState(): ?EmptyState
    {
        return $this->tableEmptyState;
    }

    protected function transform(Model $model): array
    {
        if ($this->transformModelUsing === null) {
            return parent::transform($model);
        }

        $data = ($this->transformModelUsing)($model);

        if (! is_array($data)) {
            throw new LogicException('Anonymous table model transforms must return an array.');
        }

        return $data;
    }

    protected function withQueryBuilder(QueryBuilder $query): QueryBuilder
    {
        if ($this->queryBuilderUsing === null) {
            return $query;
        }

        $resolved = ($this->queryBuilderUsing)($query);

        if ($resolved === null) {
            return $query;
        }

        if (! $resolved instanceof QueryBuilder) {
            throw new LogicException('Anonymous table query builder callbacks must return a query builder or null.');
        }

        return $resolved;
    }
}
