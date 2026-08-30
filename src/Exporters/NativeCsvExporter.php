<?php

namespace Musing\InertiaTable\Exporters;

use BackedEnum;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Musing\InertiaTable\Columns\Column;
use Musing\InertiaTable\Contracts\Exporter;
use Musing\InertiaTable\Exports\Export;
use Musing\InertiaTable\Table;
use Stringable;
use Symfony\Component\HttpFoundation\Response;

final class NativeCsvExporter implements Exporter
{
    public function download(
        Request $request,
        Table $table,
        Export $export,
        Builder $query,
        array $columns,
    ): Response {
        $filename = $export->resolvedFilename($request, $table);
        $metadata = $export->metadata();
        $delimiter = $this->delimiter($metadata['delimiter'] ?? ',');
        $includeBom = $metadata['bom'] ?? true;

        return response()->streamDownload(
            function () use ($query, $columns, $delimiter, $includeBom) {
                $stream = fopen('php://output', 'wb');

                if ($stream === false) {
                    throw new \RuntimeException('Unable to open the CSV output stream.');
                }

                if ($includeBom !== false) {
                    fwrite($stream, "\xEF\xBB\xBF");
                }

                fputcsv(
                    $stream,
                    array_map(fn (Column $column) => $column->label, $columns),
                    $delimiter,
                    '"',
                    '',
                );

                foreach ($query->cursor() as $model) {
                    fputcsv(
                        $stream,
                        array_map(
                            fn (Column $column) => $this->normalizeValue(
                                $column->resolveExportValue($model),
                            ),
                            $columns,
                        ),
                        $delimiter,
                        '"',
                        '',
                    );
                }

                fclose($stream);
            },
            $filename,
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }

    private function delimiter(mixed $delimiter): string
    {
        return is_string($delimiter) && in_array($delimiter, [',', ';', "\t", '|'], true)
            ? $delimiter
            : ',';
    }

    private function normalizeValue(mixed $value): string|int|float
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if ($value instanceof BackedEnum) {
            $value = $value->value;
        } elseif ($value instanceof DateTimeInterface) {
            $value = $value->format(DateTimeInterface::ATOM);
        } elseif ($value instanceof Stringable) {
            $value = (string) $value;
        }

        if (is_int($value) || is_float($value)) {
            return $value;
        }

        if (! is_string($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        }

        return preg_match('/^[\t\r ]*[=+\-@]/u', $value) === 1
            ? "'".$value
            : $value;
    }
}
