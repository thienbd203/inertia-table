<?php

namespace Toolbelt\InertiaTable;

use Illuminate\Contracts\Support\Arrayable;

/** @implements Arrayable<string, mixed> */
final readonly class TableResource implements Arrayable
{
    /**
     * @param  array<int, array<string, mixed>>  $columns
     * @param  array<int, array<string, mixed>>  $filters
     * @param  array<int, array<string, mixed>>  $actions
     * @param  array<string, bool>  $capabilities
     * @param  array<string, mixed>  $results
     * @param  array<string, mixed>  $options
     */
    public function __construct(
        public string $name,
        public array $columns,
        public array $filters,
        public array $actions,
        public array $capabilities,
        public TableState $state,
        public array $results,
        public array $options,
    ) {}

    public function toArray(): array
    {
        return [
            'schemaVersion' => 1,
            'name' => $this->name,
            'columns' => $this->columns,
            'filters' => $this->filters,
            'actions' => $this->actions,
            'capabilities' => $this->capabilities,
            'state' => $this->state->toArray(),
            'results' => $this->results,
            'options' => $this->options,
        ];
    }
}
