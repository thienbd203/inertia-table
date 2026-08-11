<?php

namespace Toolbelt\InertiaTable;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;

/** @implements Arrayable<string, mixed> */
final readonly class TableState implements Arrayable
{
    /**
     * @param  array<string, array{enabled: bool, clause: string, value: mixed}>  $filters
     * @param  array<string, bool>  $columns
     */
    public function __construct(
        public string $search,
        public ?string $sort,
        public array $filters,
        public array $columns,
        public int $page,
        public int $perPage,
    ) {}

    /**
     * @param  array<int, int>  $perPageOptions
     * @param  array<string, bool>  $defaultColumns
     */
    public static function fromRequest(
        Request $request,
        string $tableName,
        ?string $defaultSort,
        int $defaultPerPage,
        array $perPageOptions,
        array $defaultColumns = [],
    ): self {
        $input = data_get($request->query(), "table.{$tableName}", []);
        $input = is_array($input) ? $input : [];
        $search = is_scalar($input['search'] ?? null) ? trim((string) $input['search']) : '';
        $sort = is_string($input['sort'] ?? null) && $input['sort'] !== ''
            ? $input['sort']
            : $defaultSort;
        $filters = is_array($input['filters'] ?? null) ? $input['filters'] : [];
        $requestedColumns = is_array($input['columns'] ?? null) ? $input['columns'] : [];
        $columns = [];

        foreach ($defaultColumns as $attribute => $visible) {
            $columns[$attribute] = array_key_exists($attribute, $requestedColumns)
                ? filter_var($requestedColumns[$attribute], FILTER_VALIDATE_BOOL)
                : $visible;
        }
        $page = filter_var($input['page'] ?? 1, FILTER_VALIDATE_INT, [
            'options' => ['default' => 1, 'min_range' => 1],
        ]);
        $requestedPerPage = filter_var($input['perPage'] ?? $defaultPerPage, FILTER_VALIDATE_INT);
        $perPage = in_array($requestedPerPage, $perPageOptions, true)
            ? $requestedPerPage
            : $defaultPerPage;

        return new self($search, $sort, $filters, $columns, $page, $perPage);
    }

    public function withSort(?string $sort): self
    {
        return new self($this->search, $sort, $this->filters, $this->columns, $this->page, $this->perPage);
    }

    public function withSearch(string $search): self
    {
        return new self($search, $this->sort, $this->filters, $this->columns, $this->page, $this->perPage);
    }

    /**
     * @param  array<string, array{enabled: bool, clause: string, value: mixed}>  $filters
     */
    public function withFilters(array $filters): self
    {
        return new self($this->search, $this->sort, $filters, $this->columns, $this->page, $this->perPage);
    }

    /** @param array<string, bool> $columns */
    public function withColumns(array $columns): self
    {
        return new self($this->search, $this->sort, $this->filters, $columns, $this->page, $this->perPage);
    }

    public function toArray(): array
    {
        return [
            'search' => $this->search,
            'sort' => $this->sort,
            'filters' => $this->filters,
            'columns' => $this->columns,
            'page' => $this->page,
            'perPage' => $this->perPage,
        ];
    }
}
