<?php

namespace Musing\InertiaTable\Tests\Consumer;

use Illuminate\Database\Eloquent\Builder;
use Musing\InertiaTable\Exports\Export;
use Musing\InertiaTable\Exports\QueuedExportSnapshot;

use function PHPStan\Testing\assertType;

$export = Export::make('csv')
    ->modifyQueryUsing(function (array $state, Builder $query): void {
        $query->whereNotNull('published_at');
    })
    ->deliveryUrlUsing(fn (string $path, QueuedExportSnapshot $snapshot): string => '/download/'.$path)
    ->onReady(function (?string $url): void {});

assertType(Export::class, $export);
