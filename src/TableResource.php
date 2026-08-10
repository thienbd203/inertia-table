<?php

namespace Toolbelt\InertiaTable;

use Illuminate\Contracts\Support\Arrayable;

/** @implements Arrayable<string, mixed> */
final readonly class TableResource implements Arrayable
{
    /**
     * @param  array<int, array<string, mixed>>  $columns
     * @param  array<int, array<string, mixed>>  $filters
     * @param  array<string, mixed>  $results
     * @param  array<int, string>  $reloadProps
     */
    public function __construct(
        public string $name,
        public array $columns,
        public array $filters,
        public TableState $state,
        public array $results,
        public array $reloadProps = [],
    ) {}

    public function toArray(): array
    {
        return [
            'schemaVersion' => 1,
            'name' => $this->name,
            'columns' => $this->columns,
            'filters' => $this->filters,
            'state' => $this->state->toArray(),
            'results' => $this->results,
            'reloadProps' => $this->reloadProps,
        ];
    }
}
