<?php

use Musing\InertiaTable\Exporters\LaravelExcelExporter;
use Musing\InertiaTable\Exporters\NativeCsvExporter;
use Musing\InertiaTable\Sorters\PowerJoinsRelationshipSorter;

return [
    'per_page' => 25,
    'per_page_options' => [10, 25, 50, 100],
    'pagination_type' => 'full',
    'debounce' => 300,
    'sticky' => [
        'footer' => false,
        'backdrop_filter' => true,
    ],
    'columns' => [
        'resizable' => true,
        'reorderable' => true,
    ],
    'action_path' => '_inertia-table/actions',
    'actions' => [
        'queue' => [
            'connection' => null,
            'queue' => null,
            'delay' => 0,
            'expires_after' => 86400,
            'status_retention' => 86400,
            'after_commit' => true,
        ],
    ],
    'export_path' => '_inertia-table/exports',
    'exporters' => [
        'csv' => NativeCsvExporter::class,
        'xlsx' => LaravelExcelExporter::class,
        'pdf' => LaravelExcelExporter::class,
    ],
    'exports' => [
        'chunk_size' => 1000,
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
