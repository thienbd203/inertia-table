<?php

namespace Musing\InertiaTable\Http\Controllers;

use Illuminate\Http\Request;
use Musing\InertiaTable\Exports\Export;
use Musing\InertiaTable\Exports\ExportManager;
use Musing\InertiaTable\Exports\QueuedExportDispatcher;
use Musing\InertiaTable\Support\TableReference;
use Musing\InertiaTable\Table;
use Symfony\Component\HttpFoundation\Response;

final class ExportController
{
    public function __invoke(Request $request, string $table, string $export): Response
    {
        $tableClass = TableReference::decode($table);
        abort_if($tableClass === null, 404);

        $tableInstance = app($tableClass);
        abort_unless($tableInstance instanceof Table, 404);
        $definition = $tableInstance->export($export);
        abort_unless($definition instanceof Export, 404);
        abort_unless($definition->isAuthorized($request, $tableInstance), 403);

        $validated = $request->validate([
            'state' => ['sometimes', 'array'],
            'selection' => ['sometimes', 'array'],
            'idempotencyKey' => [$definition->isQueued() ? 'required' : 'sometimes', 'string', 'max:128'],
        ]);

        if ($definition->isQueued()) {
            $status = app(QueuedExportDispatcher::class)->dispatch(
                $request,
                $tableInstance,
                $definition,
                $validated['state'] ?? [],
                is_array($validated['selection'] ?? null) ? $validated['selection'] : null,
                $validated['idempotencyKey'],
            );

            return response()->json(['export' => $status], 202);
        }

        return app(ExportManager::class)->download(
            $request,
            $tableInstance,
            $definition,
            $validated['state'] ?? [],
            is_array($validated['selection'] ?? null) ? $validated['selection'] : null,
        );
    }
}
