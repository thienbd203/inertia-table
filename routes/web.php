<?php

use Illuminate\Support\Facades\Route;
use Musing\InertiaTable\Http\Controllers\ActionController;

Route::middleware(['web', 'signed:relative'])
    ->post(
        trim((string) config('inertia-table.action_path', '_inertia-table/actions'), '/')
            .'/{table}/{action}',
        ActionController::class,
    )
    ->where('table', '[A-Za-z0-9_-]+')
    ->name('inertia-table.actions');
