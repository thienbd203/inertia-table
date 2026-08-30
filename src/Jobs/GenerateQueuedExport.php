<?php

namespace Musing\InertiaTable\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use LogicException;
use Musing\InertiaTable\Contracts\ExportContext;
use Musing\InertiaTable\Exports\Export;
use Musing\InertiaTable\Exports\ExportManager;
use Musing\InertiaTable\Exports\QueuedExportRepository;
use Musing\InertiaTable\Exports\QueuedExportSnapshot;
use Musing\InertiaTable\Table;
use Throwable;

final class GenerateQueuedExport implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly QueuedExportSnapshot $snapshot) {}

    public function handle(ExportManager $manager, QueuedExportRepository $repository): void
    {
        $ttl = max($this->snapshot->expiresAt - time() + 86400, 86400);
        $repository->put($this->snapshot->id, [
            ...$this->status($repository),
            'status' => 'processing',
        ], $ttl);
        $context = app($this->snapshot->contextClass);

        if (! $context instanceof ExportContext) {
            throw new LogicException('The queued export context is invalid.');
        }

        try {
            $context->restore($this->snapshot->actorId, $this->snapshot->scopeAttributes);
            [$request, $table, $export] = $this->resolveDefinition();
            $manager->store(
                $request,
                $table,
                $export,
                $this->snapshot->state,
                $this->snapshot->selection,
                $this->snapshot->disk,
                $this->snapshot->path,
            );
            $url = $export->resolvedDeliveryUrl($this->snapshot);
            $repository->put($this->snapshot->id, [
                ...$this->status($repository),
                'status' => 'ready',
                'url' => $url,
            ], $ttl);
            $export->notifyReady($this->snapshot, $url);
            CleanupQueuedExport::dispatch(
                $this->snapshot->id,
                $this->snapshot->disk,
                $this->snapshot->path,
            )->delay(Carbon::createFromTimestamp($this->snapshot->expiresAt));
        } finally {
            $context->release();
        }
    }

    public function failed(?Throwable $exception): void
    {
        $exception ??= new LogicException('The queued export failed.');
        Storage::disk($this->snapshot->disk)->delete($this->snapshot->path);
        $repository = app(QueuedExportRepository::class);
        $repository->put($this->snapshot->id, [
            ...$this->status($repository),
            'status' => 'failed',
            'url' => null,
            'message' => $exception->getMessage(),
        ], 86400);

        try {
            $context = app($this->snapshot->contextClass);

            if (! $context instanceof ExportContext) {
                return;
            }

            try {
                $context->restore($this->snapshot->actorId, $this->snapshot->scopeAttributes);
                [, , $export] = $this->resolveDefinition();
                $export->notifyFailure($this->snapshot, $exception);
            } finally {
                $context->release();
            }
        } catch (Throwable) {
            // The original failure remains the authoritative job error.
        }
    }

    /** @return array{Request, Table, Export} */
    private function resolveDefinition(): array
    {
        $table = app($this->snapshot->tableClass);

        if (! $table instanceof Table) {
            throw new LogicException('The queued export table no longer exists.');
        }

        $export = $table->export($this->snapshot->exportKey);

        if (! $export instanceof Export || ! $export->isQueued()) {
            throw new LogicException('The queued export definition no longer exists.');
        }

        if ($export->typeName() !== $this->snapshot->type || $export->scope()->value !== $this->snapshot->scope) {
            throw new LogicException('The queued export definition changed after dispatch.');
        }

        $request = Request::create('/', 'POST');
        $request->setUserResolver(fn () => auth()->user());

        if (! $export->isAuthorized($request, $table)) {
            throw new LogicException('The queued export is no longer authorized.');
        }

        return [$request, $table, $export];
    }

    /** @return array<string, mixed> */
    private function status(QueuedExportRepository $repository): array
    {
        return $repository->get($this->snapshot->id) ?? [
            'id' => $this->snapshot->id,
            'status' => 'dispatched',
            'filename' => $this->snapshot->filename,
            'url' => null,
            'expiresAt' => $this->snapshot->expiresAt,
        ];
    }
}
