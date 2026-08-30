<?php

it('merges the package default configuration without a published config file', function () {
    expect(config('inertia-table.per_page'))->toBe(25)
        ->and(config('inertia-table.per_page_options'))->toBe([10, 25, 50, 100])
        ->and(config('inertia-table.debounce'))->toBe(300)
        ->and(config('inertia-table.action_path'))->toBe('_inertia-table/actions')
        ->and(config('inertia-table.view_path'))->toBe('_inertia-table/views')
        ->and(config('inertia-table.views.table'))->toBe('table_views')
        ->and(route('inertia-table.actions', ['table' => 'table', 'action' => 'action'], false))
        ->toStartWith('/_inertia-table/actions/table/action')
        ->and(route('inertia-table.views.store', ['table' => 'table'], false))
        ->toStartWith('/_inertia-table/views/table');
});

it('publishes the table views migration', function () {
    $this->artisan('vendor:publish', [
        '--tag' => 'inertia-table-migrations',
        '--force' => true,
    ]);

    expect(glob(database_path('migrations/*_create_table_views_table.php')))
        ->not->toBeEmpty();
});

it('publishes its config file under the inertia-table-config tag', function () {
    $this->artisan('vendor:publish', ['--tag' => 'inertia-table-config', '--force' => true]);

    expect(file_exists(config_path('inertia-table.php')))->toBeTrue();
});
