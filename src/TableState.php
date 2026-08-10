<?php

namespace Toolbelt\InertiaTable;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;

/** @implements Arrayable<string, mixed> */
final readonly class TableState implements Arrayable
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function __construct(
        public string $search,
        public ?string $sort,
        public array $filters,
        public int $page,
        public int $perPage,
    ) {}

    /**
     * @param  array<int, int>  $perPageOptions
     */
    public static function fromRequest(
        Request $request,
        string $tableName,
        ?string $defaultSort,
        int $defaultPerPage,
        array $perPageOptions,
    ): self {
        $input = data_get($request->query(), "table.{$tableName}", []);
        $input = is_array($input) ? $input : [];
        $search = is_scalar($input['search'] ?? null) ? trim((string) $input['search']) : '';
        $sort = is_string($input['sort'] ?? null) && $input['sort'] !== ''
            ? $input['sort']
            : $defaultSort;
        $filters = is_array($input['filters'] ?? null) ? $input['filters'] : [];
        $page = filter_var($input['page'] ?? 1, FILTER_VALIDATE_INT, [
            'options' => ['default' => 1, 'min_range' => 1],
        ]);
        $requestedPerPage = filter_var($input['perPage'] ?? $defaultPerPage, FILTER_VALIDATE_INT);
        $perPage = in_array($requestedPerPage, $perPageOptions, true)
            ? $requestedPerPage
            : $defaultPerPage;

        return new self($search, $sort, $filters, $page, $perPage);
    }

    public function withSort(?string $sort): self
    {
        return new self($this->search, $sort, $this->filters, $this->page, $this->perPage);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function withFilters(array $filters): self
    {
        return new self($this->search, $this->sort, $filters, $this->page, $this->perPage);
    }

    public function toArray(): array
    {
        return [
            'search' => $this->search,
            'sort' => $this->sort,
            'filters' => $this->filters,
            'page' => $this->page,
            'perPage' => $this->perPage,
        ];
    }
}
