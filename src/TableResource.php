<?php

namespace Musing\InertiaTable;

use Illuminate\Contracts\Support\Arrayable;

/** @implements Arrayable<string, mixed> */
final readonly class TableResource implements Arrayable
{
    /**
     * @param  array<int, array<string, mixed>>  $columns
     * @param  array<int, array<string, mixed>>  $filters
     * @param  array<int, array<string, mixed>>  $actions
     * @param  array<int, string>  $search
     * @param  array<string, bool>  $capabilities
     * @param  array<string, mixed>  $results
     * @param  array<string, mixed>  $options
     * @param  array<string, mixed>|null  $views
     * @param  array<int, array<string, mixed>>  $exports
     */
    public function __construct(
        public string $name,
        public array $columns,
        public array $filters,
        public array $actions,
        public array $search,
        public array $capabilities,
        public TableState $state,
        public array $results,
        public array $options,
        public ?array $views = null,
        public array $exports = [],
    ) {}

    public function toArray(): array
    {
        return [
            'schemaVersion' => 1,
            'name' => $this->name,
            'columns' => $this->columns,
            'filters' => $this->filters,
            'actions' => $this->actions,
            'search' => $this->search,
            'capabilities' => $this->capabilities,
            'state' => $this->state->toArray(),
            'results' => $this->results,
            'options' => $this->options,
            'views' => $this->views,
            'exports' => $this->exports,
        ];
    }
}
