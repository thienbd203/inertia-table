<?php

use Musing\InertiaTable\Exporters\NativeCsvExporter;
use Musing\InertiaTable\Sorters\PowerJoinsRelationshipSorter;

afterEach(function () {
    $publishedConfig = config_path('inertia-table.php');

    if (is_file($publishedConfig)) {
        unlink($publishedConfig);
    }

    foreach (glob(database_path('migrations/*_create_table_views_table.php')) ?: [] as $migration) {
        if (is_file($migration)) {
            unlink($migration);
        }
    }
});

it('merges the package default configuration without a published config file', function () {
    expect(config('inertia-table.per_page'))->toBe(25)
        ->and(config('inertia-table.per_page_options'))->toBe([10, 25, 50, 100])
        ->and(config('inertia-table.pagination_type'))->toBe('full')
        ->and(config('inertia-table.debounce'))->toBe(300)
        ->and(config('inertia-table.sticky.backdrop_filter'))->toBeTrue()
        ->and(config('inertia-table.columns.resizable'))->toBeTrue()
        ->and(config('inertia-table.columns.reorderable'))->toBeTrue()
        ->and(config('inertia-table.action_path'))->toBe('_inertia-table/actions')
        ->and(config('inertia-table.actions.queue.connection'))->toBeNull()
        ->and(config('inertia-table.actions.queue.queue'))->toBeNull()
        ->and(config('inertia-table.actions.queue.delay'))->toBe(0)
        ->and(config('inertia-table.actions.queue.expires_after'))->toBe(86400)
        ->and(config('inertia-table.actions.queue.status_retention'))->toBe(86400)
        ->and(config('inertia-table.actions.queue.after_commit'))->toBeTrue()
        ->and(config('inertia-table.export_path'))->toBe('_inertia-table/exports')
        ->and(config('inertia-table.exporters.csv'))->toBe(NativeCsvExporter::class)
        ->and(config('inertia-table.relationship_sorter'))->toBe(PowerJoinsRelationshipSorter::class)
        ->and(config('inertia-table.queue.connection'))->toBeNull()
        ->and(config('inertia-table.queue.queue'))->toBeNull()
        ->and(config('inertia-table.queue.delay'))->toBe(0)
        ->and(config('inertia-table.queue.disk'))->toBe('local')
        ->and(config('inertia-table.queue.path'))->toBe('table-exports')
        ->and(config('inertia-table.queue.expires_after'))->toBe(604800)
        ->and(config('inertia-table.view_path'))->toBe('_inertia-table/views')
        ->and(config('inertia-table.views.table'))->toBe('table_views')
        ->and(route('inertia-table.actions', ['table' => 'table', 'action' => 'action'], false))
        ->toStartWith('/_inertia-table/actions/table/action')
        ->and(route('inertia-table.action-status', [
            'table' => 'table',
            'action' => 'action',
            'id' => '00000000-0000-0000-0000-000000000000',
        ], false))->toStartWith('/_inertia-table/actions/table/action/')
        ->and(route('inertia-table.execute-export', ['table' => 'table', 'export' => 'csv'], false))
        ->toStartWith('/_inertia-table/exports/table/csv')
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
