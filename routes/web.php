<?php

use Illuminate\Support\Facades\Route;
use Musing\InertiaTable\Http\Controllers\ActionController;
use Musing\InertiaTable\Http\Controllers\ExportController;
use Musing\InertiaTable\Http\Controllers\FilterOptionsController;
use Musing\InertiaTable\Http\Controllers\QueuedActionStatusController;
use Musing\InertiaTable\Http\Controllers\QueuedExportStatusController;
use Musing\InertiaTable\Http\Controllers\ViewController;

Route::middleware(['web', 'signed:relative'])
    ->post(
        trim((string) config('inertia-table.filter_option_path', '_inertia-table/filter-options'), '/')
            .'/{table}/{filter}',
        FilterOptionsController::class,
    )
    ->where(['table' => '[A-Za-z0-9_-]+', 'filter' => '[A-Za-z0-9_.-]+'])
    ->name('inertia-table.filter-options');

Route::middleware(['web', 'signed:relative'])
    ->post(
        trim((string) config('inertia-table.action_path', '_inertia-table/actions'), '/')
            .'/{table}/{action}',
        ActionController::class,
    )
    ->where('table', '[A-Za-z0-9_-]+')
    ->name('inertia-table.actions');

Route::middleware(['web', 'signed:relative'])
    ->get(
        trim((string) config('inertia-table.action_path', '_inertia-table/actions'), '/')
            .'/{table}/{action}/{id}',
        QueuedActionStatusController::class,
    )
    ->where([
        'table' => '[A-Za-z0-9_-]+',
        'action' => '[A-Za-z0-9_-]+',
        'id' => '[A-Fa-f0-9-]+',
    ])
    ->name('inertia-table.action-status');

Route::middleware(['web', 'signed:relative'])
    ->post(
        trim((string) config('inertia-table.export_path', '_inertia-table/exports'), '/')
            .'/{table}/{export}',
        ExportController::class,
    )
    ->where(['table' => '[A-Za-z0-9_-]+', 'export' => '[A-Za-z0-9_-]+'])
    ->name('inertia-table.execute-export');

Route::middleware(['web', 'signed:relative'])
    ->get(
        trim((string) config('inertia-table.export_path', '_inertia-table/exports'), '/')
            .'/{table}/{export}/{id}',
        QueuedExportStatusController::class,
    )
    ->where([
        'table' => '[A-Za-z0-9_-]+',
        'export' => '[A-Za-z0-9_-]+',
        'id' => '[A-Fa-f0-9-]+',
    ])
    ->name('inertia-table.export-status');

Route::middleware(['web', 'signed:relative'])
    ->prefix(trim((string) config('inertia-table.view_path', '_inertia-table/views'), '/'))
    ->where(['table' => '[A-Za-z0-9_-]+', 'view' => '[A-Za-z0-9_-]+'])
    ->group(function () {
        Route::post('/{table}', [ViewController::class, 'store'])
            ->name('inertia-table.views.store');
        Route::patch('/{table}/{view}', [ViewController::class, 'update'])
            ->name('inertia-table.views.update');
        Route::delete('/{table}/{view}', [ViewController::class, 'destroy'])
            ->name('inertia-table.views.destroy');
        Route::post('/{table}/{view}/default', [ViewController::class, 'setDefault'])
            ->name('inertia-table.views.default');
        Route::post('/{table}/{view}/share', [ViewController::class, 'share'])
            ->name('inertia-table.views.share');
    });
