<?php

namespace Musing\InertiaTable;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Pagination\Cursor;

/** @implements Arrayable<string, mixed> */
final readonly class TableState implements Arrayable
{
    /**
     * @param  array<string, array{enabled: bool, clause: string, value: mixed}>  $filters
     * @param  array<string, bool>  $columns
     * @param  array{left: array<int, string>, right: array<int, string>}  $pinnedColumns
     * @param  array<int, string>  $columnOrder
     * @param  array<string, int>  $columnWidths
     */
    public function __construct(
        public string $search,
        public ?string $sort,
        public array $filters,
        public array $columns,
        public int $page,
        public int $perPage,
        public int|string|null $view = null,
        public array $pinnedColumns = ['left' => [], 'right' => []],
        public ?string $cursor = null,
        public array $columnOrder = [],
        public array $columnWidths = [],
    ) {}

    /**
     * @param  array<int, int>  $perPageOptions
     * @param  array<string, bool>  $defaultColumns
     * @param  array{left: array<int, string>, right: array<int, string>}  $defaultPinnedColumns
     * @param  array<int, string>  $defaultColumnOrder
     * @param  array<string, int>  $defaultColumnWidths
     */
    public static function fromRequest(
        Request $request,
        string $tableName,
        ?string $defaultSort,
        int $defaultPerPage,
        array $perPageOptions,
        array $defaultColumns = [],
        array $defaultPinnedColumns = ['left' => [], 'right' => []],
        array $defaultColumnOrder = [],
        array $defaultColumnWidths = [],
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
        $view = is_int($input['view'] ?? null) || is_string($input['view'] ?? null)
            ? $input['view']
            : null;
        $requestedPinnedColumns = is_array($input['pinnedColumns'] ?? null)
            ? $input['pinnedColumns']
            : null;
        $pinnedColumns = [
            'left' => is_array($requestedPinnedColumns['left'] ?? null)
                ? $requestedPinnedColumns['left']
                : $defaultPinnedColumns['left'],
            'right' => is_array($requestedPinnedColumns['right'] ?? null)
                ? $requestedPinnedColumns['right']
                : $defaultPinnedColumns['right'],
        ];
        $requestedCursor = is_string($input['cursor'] ?? null)
            ? $input['cursor']
            : null;
        $cursor = Cursor::fromEncoded($requestedCursor) !== null
            ? $requestedCursor
            : null;
        $declaredOrder = $defaultColumnOrder !== []
            ? array_values($defaultColumnOrder)
            : array_keys($defaultColumns);
        $requestedOrder = is_array($input['columnOrder'] ?? null)
            ? $input['columnOrder']
            : $declaredOrder;
        $requestedOrder = array_values(array_unique(array_filter(
            $requestedOrder,
            fn (mixed $attribute) => is_string($attribute) && in_array($attribute, $declaredOrder, true),
        )));
        $columnOrder = [...$requestedOrder, ...array_values(array_diff($declaredOrder, $requestedOrder))];
        $requestedWidths = is_array($input['columnWidths'] ?? null)
            ? $input['columnWidths']
            : [];
        $columnWidths = $defaultColumnWidths;

        foreach ($requestedWidths as $attribute => $width) {
            if (! is_string($attribute)
                || ! in_array($attribute, $declaredOrder, true)
                || filter_var($width, FILTER_VALIDATE_INT) === false
                || (int) $width < 1) {
                continue;
            }

            $columnWidths[$attribute] = (int) $width;
        }

        return new self($search, $sort, $filters, $columns, $page, $perPage, $view, $pinnedColumns, $cursor, $columnOrder, $columnWidths);
    }

    public function withSort(?string $sort): self
    {
        return new self($this->search, $sort, $this->filters, $this->columns, $this->page, $this->perPage, $this->view, $this->pinnedColumns, $this->cursor, $this->columnOrder, $this->columnWidths);
    }

    public function withSearch(string $search): self
    {
        return new self($search, $this->sort, $this->filters, $this->columns, $this->page, $this->perPage, $this->view, $this->pinnedColumns, $this->cursor, $this->columnOrder, $this->columnWidths);
    }

    /**
     * @param  array<string, array{enabled: bool, clause: string, value: mixed}>  $filters
     */
    public function withFilters(array $filters): self
    {
        return new self($this->search, $this->sort, $filters, $this->columns, $this->page, $this->perPage, $this->view, $this->pinnedColumns, $this->cursor, $this->columnOrder, $this->columnWidths);
    }

    /** @param array<string, bool> $columns */
    public function withColumns(array $columns): self
    {
        return new self($this->search, $this->sort, $this->filters, $columns, $this->page, $this->perPage, $this->view, $this->pinnedColumns, $this->cursor, $this->columnOrder, $this->columnWidths);
    }

    public function withView(int|string|null $view): self
    {
        return new self($this->search, $this->sort, $this->filters, $this->columns, $this->page, $this->perPage, $view, $this->pinnedColumns, $this->cursor, $this->columnOrder, $this->columnWidths);
    }

    /** @param array{left: array<int, string>, right: array<int, string>} $pinnedColumns */
    public function withPinnedColumns(array $pinnedColumns): self
    {
        return new self($this->search, $this->sort, $this->filters, $this->columns, $this->page, $this->perPage, $this->view, $pinnedColumns, $this->cursor, $this->columnOrder, $this->columnWidths);
    }

    public function withPage(int $page): self
    {
        return new self($this->search, $this->sort, $this->filters, $this->columns, $page, $this->perPage, $this->view, $this->pinnedColumns, $this->cursor, $this->columnOrder, $this->columnWidths);
    }

    public function withCursor(?string $cursor): self
    {
        return new self($this->search, $this->sort, $this->filters, $this->columns, $this->page, $this->perPage, $this->view, $this->pinnedColumns, $cursor, $this->columnOrder, $this->columnWidths);
    }

    /** @param array<int, string> $columnOrder */
    public function withColumnOrder(array $columnOrder): self
    {
        return new self($this->search, $this->sort, $this->filters, $this->columns, $this->page, $this->perPage, $this->view, $this->pinnedColumns, $this->cursor, $columnOrder, $this->columnWidths);
    }

    /** @param array<string, int> $columnWidths */
    public function withColumnWidths(array $columnWidths): self
    {
        return new self($this->search, $this->sort, $this->filters, $this->columns, $this->page, $this->perPage, $this->view, $this->pinnedColumns, $this->cursor, $this->columnOrder, $columnWidths);
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
            'view' => $this->view,
            'pinnedColumns' => $this->pinnedColumns,
            'cursor' => $this->cursor,
            'columnOrder' => $this->columnOrder,
            'columnWidths' => $this->columnWidths,
        ];
    }
}
