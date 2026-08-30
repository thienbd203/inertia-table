<?php

namespace Musing\InertiaTable\Exporters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Musing\InertiaTable\Contracts\Exporter;
use Musing\InertiaTable\Exports\Export;
use Musing\InertiaTable\Table;
use Symfony\Component\HttpFoundation\Response;

final class LaravelExcelExporter implements Exporter
{
    public function download(
        Request $request,
        Table $table,
        Export $export,
        Builder $query,
        array $columns,
    ): Response {
        if (! interface_exists('Maatwebsite\\Excel\\Concerns\\FromQuery')) {
            throw ValidationException::withMessages([
                'export' => 'Install maatwebsite/excel before using XLSX or PDF table exports.',
            ]);
        }

        $writer = app('excel');

        if (! is_object($writer) || ! is_callable([$writer, 'download'])) {
            throw ValidationException::withMessages([
                'export' => 'Laravel Excel is installed but its export service is unavailable.',
            ]);
        }

        $writerType = match ($export->typeName()) {
            'xlsx' => 'Xlsx',
            'pdf' => 'Dompdf',
            default => $export->typeName(),
        };
        $response = call_user_func(
            [$writer, 'download'],
            new LaravelExcelRows($query, $columns),
            $export->resolvedFilename($request, $table),
            $writerType,
        );

        if (! $response instanceof Response) {
            throw ValidationException::withMessages([
                'export' => 'The Laravel Excel adapter did not return a download response.',
            ]);
        }

        return $response;
    }
}
