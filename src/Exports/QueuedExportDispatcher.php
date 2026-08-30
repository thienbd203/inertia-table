<?php

namespace Musing\InertiaTable\Exports;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;
use Musing\InertiaTable\Contracts\ExportContext;
use Musing\InertiaTable\Jobs\GenerateQueuedExport;
use Musing\InertiaTable\Table;
use Throwable;

final class QueuedExportDispatcher
{
    /**
     * @param  array<string, mixed>  $state
     * @param  array<string, mixed>|null  $selection
     * @return array<string, mixed>
     */
    public function dispatch(
        Request $request,
        Table $table,
        Export $export,
        array $state,
        ?array $selection,
        string $idempotencyKey,
    ): array {
        $context = app($export->contextClass());

        if (! $context instanceof ExportContext) {
            throw new LogicException('The queued export context is invalid.');
        }

        $actorId = $context->actorId($request, $table, $export);
        $attributes = $export->resolvedScopeAttributes($request, $table);
        $configuration = $export->queueConfiguration();
        $normalizedState = $table->normalizeViewState($state, true);
        $normalizedSelection = $export->scope() === ExportScope::Selected
            ? $this->normalizeSelection($table, $selection)
            : null;
        $fingerprint = json_encode([
            'table' => $table::class,
            'export' => $export->key,
            'actor' => $actorId,
            'attributes' => $attributes,
            'idempotency' => $idempotencyKey,
        ], JSON_THROW_ON_ERROR);
        $repository = app(QueuedExportRepository::class);
        $id = (string) Str::uuid();
        $existingId = $repository->reserve($fingerprint, $id, $configuration['expiresAfter'] + 86400);

        if ($existingId !== null) {
            return [
                ...($repository->get($existingId) ?? ['id' => $existingId, 'status' => 'dispatched']),
                'duplicate' => true,
            ];
        }

        $filename = $export->resolvedFilename($request, $table);
        $snapshot = new QueuedExportSnapshot(
            id: $id,
            tableClass: $table::class,
            exportKey: $export->key,
            type: $export->typeName(),
            scope: $export->scope()->value,
            state: $normalizedState,
            selection: $normalizedSelection,
            actorId: $actorId,
            scopeAttributes: $attributes,
            contextClass: $export->contextClass(),
            disk: $configuration['disk'],
            path: $configuration['path'].'/'.$id.'/'.$filename,
            filename: $filename,
            expiresAt: time() + $configuration['expiresAfter'],
        );
        $status = [
            'id' => $id,
            'status' => 'dispatched',
            'filename' => $filename,
            'url' => null,
            'expiresAt' => $snapshot->expiresAt,
            'redirect' => $export->resolvedDispatchRedirect($request, $table),
            'duplicate' => false,
        ];
        $repository->put($id, $status, $configuration['expiresAfter'] + 86400);
        $job = new GenerateQueuedExport($snapshot);
        $job->onConnection($configuration['connection']);
        $job->onQueue($configuration['queue']);
        $job->delay($configuration['delay']);
        $job->chain($export->resolvedChain($request, $table, $snapshot));

        try {
            dispatch($job);
        } catch (Throwable $exception) {
            $repository->put($id, [
                ...$status,
                'status' => 'failed',
                'message' => $exception->getMessage(),
            ], 86400);

            throw $exception;
        }

        return $status;
    }

    /**
     * @param  array<string, mixed>|null  $selection
     * @return array<string, mixed>
     */
    private function normalizeSelection(Table $table, ?array $selection): array
    {
        if ($selection === null) {
            throw ValidationException::withMessages([
                'selection' => 'Select at least one row before exporting.',
            ]);
        }

        return $table->selection($selection)->toArray();
    }
}
