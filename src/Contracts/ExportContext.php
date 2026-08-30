<?php

namespace Musing\InertiaTable\Contracts;

use Illuminate\Http\Request;
use Musing\InertiaTable\Exports\Export;
use Musing\InertiaTable\Table;

interface ExportContext
{
    public function actorId(Request $request, Table $table, Export $export): int|string|null;

    /** @param array<string, mixed> $attributes */
    public function restore(int|string|null $actorId, array $attributes): void;

    public function release(): void;
}
