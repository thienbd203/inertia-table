<?php

namespace Maatwebsite\Excel\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

interface FromQuery
{
    /** @return Builder<Model> */
    public function query();
}

interface WithHeadings
{
    /** @return array<int, string> */
    public function headings(): array;
}

interface WithMapping
{
    /** @return array<int, mixed> */
    public function map(mixed $row): array;
}

interface WithColumnFormatting
{
    /** @return array<string, string> */
    public function columnFormats(): array;
}
