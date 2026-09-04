<?php

namespace Musing\InertiaTable\Contracts;

use Illuminate\Http\Request;
use Musing\InertiaTable\Actions\Action;
use Musing\InertiaTable\Table;

interface ActionContext
{
    public function actorId(Request $request, Table $table, Action $action): int|string|null;

    /** @param array<string, mixed> $attributes */
    public function restore(int|string|null $actorId, array $attributes): void;

    public function release(): void;
}
