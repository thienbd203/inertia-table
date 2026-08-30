<?php

namespace Musing\InertiaTable\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Musing\InertiaTable\SortDirection;

interface RelationshipSorter
{
    public function sort(Builder $query, string $path, SortDirection $direction): void;
}
