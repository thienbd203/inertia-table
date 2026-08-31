<?php

namespace Musing\InertiaTable\Exporters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use LogicException;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithCustomChunkSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Musing\InertiaTable\Columns\Column;

/** @internal Loaded only when the optional maatwebsite/excel package is installed. */
final class LaravelExcelRows implements FromQuery, WithColumnFormatting, WithCustomChunkSize, WithHeadings, WithMapping
{
    /**
     * @param  Builder<Model>  $query
     * @param  array<int, Column>  $columns
     */
    public function __construct(
        private readonly Builder $query,
        private readonly array $columns,
        private readonly int $chunkSize,
    ) {}

    public function chunkSize(): int
    {
        return $this->chunkSize;
    }

    /** @return Builder<Model> */
    public function query(): Builder
    {
        return clone $this->query;
    }

    /** @return array<int, string> */
    public function headings(): array
    {
        return array_map(fn (Column $column) => $column->label, $this->columns);
    }

    /** @return array<int, mixed> */
    public function map(mixed $row): array
    {
        if (! $row instanceof Model) {
            throw new LogicException('Laravel Excel table exports require Eloquent models.');
        }

        return array_map(
            fn (Column $column) => $column->resolveExportValue($row),
            $this->columns,
        );
    }

    /** @return array<string, string> */
    public function columnFormats(): array
    {
        $formats = [];

        foreach ($this->columns as $index => $column) {
            $format = $column->resolvedExportFormat();

            if ($format !== null) {
                $formats[$this->columnLetter($index + 1)] = $format;
            }
        }

        return $formats;
    }

    private function columnLetter(int $number): string
    {
        $letter = '';

        while ($number > 0) {
            $number--;
            $letter = chr(65 + ($number % 26)).$letter;
            $number = intdiv($number, 26);
        }

        return $letter;
    }
}
