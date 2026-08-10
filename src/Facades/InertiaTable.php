<?php

namespace Toolbelt\InertiaTable\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Toolbelt\InertiaTable\InertiaTable
 */
class InertiaTable extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Toolbelt\InertiaTable\InertiaTable::class;
    }
}
