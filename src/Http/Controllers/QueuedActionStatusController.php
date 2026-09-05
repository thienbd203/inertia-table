<?php

namespace Musing\InertiaTable\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Musing\InertiaTable\Actions\Action;
use Musing\InertiaTable\Actions\QueuedActionRepository;
use Musing\InertiaTable\Contracts\ActionContext;
use Musing\InertiaTable\Support\TableReference;
use Musing\InertiaTable\Table;

final class QueuedActionStatusController
{
    public function __invoke(
        Request $request,
        string $table,
        string $action,
        string $id,
        QueuedActionRepository $repository,
    ): JsonResponse {
        $tableClass = TableReference::decode($table);
        abort_if($tableClass === null, 404);

        $tableInstance = app($tableClass);
        abort_unless($tableInstance instanceof Table, 404);
        $definition = $tableInstance->action($action);
        abort_unless($definition instanceof Action && $definition->hasHandler() && $definition->isQueued(), 404);
        $resolved = $definition->resolve(request: $request);
        abort_unless($resolved['authorized'] && ! $resolved['hidden'], 403);

        $status = $repository->get($id);
        abort_unless(is_array($status), 404);
        $context = app($definition->contextClass());
        abort_unless($context instanceof ActionContext, 500);
        $expectedHash = $repository->accessHash(
            $tableInstance::class,
            $definition->key,
            $context->actorId($request, $tableInstance, $definition),
            $definition->resolvedScopeAttributes($request, $tableInstance),
        );
        $accessHash = $status['_accessHash'] ?? null;
        abort_unless(is_string($accessHash) && hash_equals($accessHash, $expectedHash), 404);

        return response()->json([
            'action' => $repository->forResponse($status),
        ]);
    }
}
