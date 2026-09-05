<?php

namespace Musing\InertiaTable\Filters;

use Illuminate\Http\Request;
use Musing\InertiaTable\Table;

final readonly class FilterOptionRequest
{
    /**
     * @param  array<string, mixed>  $dependencies
     * @param  array<int, string|int|bool>  $selected
     * @param  array<string, mixed>  $state
     */
    public function __construct(
        public Request $request,
        public Table $table,
        public SetFilter $filter,
        public string $search = '',
        public ?string $cursor = null,
        public array $dependencies = [],
        public array $selected = [],
        public array $state = [],
        public int $perPage = 25,
    ) {}

    public function dependency(string $attribute, mixed $default = null): mixed
    {
        return $this->dependencies[$attribute] ?? $default;
    }
}
