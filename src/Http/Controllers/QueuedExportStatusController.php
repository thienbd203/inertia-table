<?php

namespace Musing\InertiaTable\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Musing\InertiaTable\Contracts\ExportContext;
use Musing\InertiaTable\Exports\Export;
use Musing\InertiaTable\Exports\QueuedExportRepository;
use Musing\InertiaTable\Support\TableReference;
use Musing\InertiaTable\Table;

final class QueuedExportStatusController
{
    public function __invoke(
        Request $request,
        string $table,
        string $export,
        string $id,
        QueuedExportRepository $repository,
    ): JsonResponse {
        $tableClass = TableReference::decode($table);
        abort_if($tableClass === null, 404);

        $tableInstance = app($tableClass);
        abort_unless($tableInstance instanceof Table, 404);
        $definition = $tableInstance->export($export);
        abort_unless($definition instanceof Export && $definition->isQueued(), 404);
        abort_unless($definition->isAuthorized($request, $tableInstance), 403);

        $status = $repository->get($id);
        abort_unless(is_array($status), 404);
        $context = app($definition->contextClass());
        abort_unless($context instanceof ExportContext, 500);
        $expectedHash = $repository->accessHash(
            $tableInstance::class,
            $definition->key,
            $context->actorId($request, $tableInstance, $definition),
            $definition->resolvedScopeAttributes($request, $tableInstance),
        );
        $accessHash = $status['_accessHash'] ?? null;
        abort_unless(is_string($accessHash) && hash_equals($accessHash, $expectedHash), 404);

        return response()->json([
            'export' => $repository->forResponse($status),
        ]);
    }
}
