<?php

namespace Musing\InertiaTable\Exporters;

use BackedEnum;
use DateTimeInterface;
use Generator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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
        $summaries = $export->includesSummaries()
            ? $table->summariesForQuery($query, $columns)
            : [];

        return response()->streamDownload(
            function () use ($query, $columns, $delimiter, $includeBom, $export, $summaries) {
                $stream = fopen('php://output', 'wb');

                if ($stream === false) {
                    throw new \RuntimeException('Unable to open the CSV output stream.');
                }

                $this->write(
                    $stream,
                    $query,
                    $columns,
                    $delimiter,
                    $includeBom !== false,
                    $export->resolvedChunkSize(),
                    $summaries,
                );
            },
            $filename,
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }

    public function store(
        Request $request,
        Table $table,
        Export $export,
        Builder $query,
        array $columns,
        string $disk,
        string $path,
    ): void {
        $temporary = tmpfile();

        if ($temporary === false) {
            throw new \RuntimeException('Unable to create a temporary CSV stream.');
        }

        try {
            $metadata = $export->metadata();
            $summaries = $export->includesSummaries()
                ? $table->summariesForQuery($query, $columns)
                : [];
            $this->write(
                $temporary,
                $query,
                $columns,
                $this->delimiter($metadata['delimiter'] ?? ','),
                ($metadata['bom'] ?? true) !== false,
                $export->resolvedChunkSize(),
                $summaries,
            );
            rewind($temporary);

            if (! Storage::disk($disk)->writeStream($path, $temporary)) {
                throw new \RuntimeException("Unable to store queued export [{$path}].");
            }
        } finally {
            fclose($temporary);
        }
    }

    /**
     * @param  resource  $stream
     * @param  Builder<Model>  $query
     * @param  array<int, Column>  $columns
     * @param  array<string, mixed>  $summaries
     */
    private function write(
        $stream,
        Builder $query,
        array $columns,
        string $delimiter,
        bool $includeBom,
        int $chunkSize,
        array $summaries,
    ): void {
        if ($includeBom) {
            fwrite($stream, "\xEF\xBB\xBF");
        }

        fputcsv(
            $stream,
            array_map(fn (Column $column) => $column->label, $columns),
            $delimiter,
            '"',
            '',
        );

        foreach ($this->models($query, $chunkSize) as $model) {
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

        if ($summaries !== []) {
            fputcsv(
                $stream,
                array_map(
                    fn (Column $column) => $this->normalizeValue(
                        $summaries[$column->attribute] ?? null,
                    ),
                    $columns,
                ),
                $delimiter,
                '"',
                '',
            );
        }
    }

    /**
     * @param  Builder<Model>  $query
     * @return Generator<int, Model>
     */
    private function models(Builder $query, int $chunkSize): Generator
    {
        $baseOffset = max((int) ($query->getQuery()->offset ?? 0), 0);
        $remaining = $query->getQuery()->limit;
        $processed = 0;

        while ($remaining === null || $remaining > 0) {
            $size = $remaining === null
                ? $chunkSize
                : min($chunkSize, $remaining);
            $models = (clone $query)
                ->offset($baseOffset + $processed)
                ->limit($size)
                ->get();
            $count = $models->count();

            foreach ($models as $model) {
                yield $model;
            }

            $processed += $count;

            if ($count < $size) {
                return;
            }

            if ($remaining !== null) {
                $remaining -= $count;
            }
        }
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
