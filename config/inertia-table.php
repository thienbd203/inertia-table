<?php

use Musing\InertiaTable\Exporters\LaravelExcelExporter;
use Musing\InertiaTable\Exporters\NativeCsvExporter;
use Musing\InertiaTable\Sorters\PowerJoinsRelationshipSorter;

return [
    'per_page' => 25,
    'per_page_options' => [10, 25, 50, 100],
    'debounce' => 300,
    'action_path' => '_inertia-table/actions',
    'export_path' => '_inertia-table/exports',
    'exporters' => [
        'csv' => NativeCsvExporter::class,
        'xlsx' => LaravelExcelExporter::class,
        'pdf' => LaravelExcelExporter::class,
    ],
    'relationship_sorter' => PowerJoinsRelationshipSorter::class,
    'queue' => [
        'connection' => null,
        'queue' => null,
        'delay' => 0,
        'disk' => 'local',
        'path' => 'table-exports',
        'expires_after' => 604800,
    ],
    'view_path' => '_inertia-table/views',
    'views' => [
        'table' => 'table_views',
    ],
];
