<?php

use Musing\InertiaTable\Exporters\LaravelExcelExporter;
use Musing\InertiaTable\Exporters\NativeCsvExporter;

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
    'view_path' => '_inertia-table/views',
    'views' => [
        'table' => 'table_views',
    ],
];
