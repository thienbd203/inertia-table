<?php

namespace Musing\InertiaTable\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Musing\InertiaTable\Columns\Column;
use Musing\InertiaTable\Exports\Export;
use Musing\InertiaTable\Table;
use Symfony\Component\HttpFoundation\Response;

interface Exporter
{
    /**
     * @param  Builder<Model>  $query
     * @param  array<int, Column>  $columns
     */
    public function download(
        Request $request,
        Table $table,
        Export $export,
        Builder $query,
        array $columns,
    ): Response;
}
