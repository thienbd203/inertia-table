<?php

namespace Musing\InertiaTable\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
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
        $lock = $repository->executionLock($this->snapshot->id, $this->executionLockSeconds());

        if (! $lock->get()) {
            $status = $this->status($repository);

            if (! in_array($status['status'] ?? null, ['ready', 'failed', 'expired'], true)) {
                $this->release(5);
            }

            return;
        }

        try {
            $this->generate($manager, $repository);
        } finally {
            $lock->release();
        }
    }

    private function generate(ExportManager $manager, QueuedExportRepository $repository): void
    {
        $ttl = max($this->snapshot->expiresAt - time() + 86400, 86400);
        $status = $this->status($repository);

        if (in_array($status['status'] ?? null, ['ready', 'failed', 'expired'], true)) {
            return;
        }

        if ($this->snapshot->expiresAt <= time()) {
            $this->expire($repository, $status);

            return;
        }

        $repository->put($this->snapshot->id, [
            ...$status,
            'status' => 'processing',
        ], $ttl);
        $context = app($this->snapshot->contextClass);

        if (! $context instanceof ExportContext) {
            throw new LogicException('The queued export context is invalid.');
        }

        $previousLocale = App::getLocale();

        try {
            $context->restore($this->snapshot->actorId, $this->snapshot->scopeAttributes);
            $this->restoreLocale();
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
            $status = $this->status($repository);

            if ($this->snapshot->expiresAt <= time() || ($status['status'] ?? null) === 'expired') {
                Storage::disk($this->snapshot->disk)->delete($this->snapshot->path);
                $this->expire($repository, $status);

                return;
            }

            CleanupQueuedExport::dispatch(
                $this->snapshot->id,
                $this->snapshot->disk,
                $this->snapshot->path,
            )->delay(Carbon::createFromTimestamp($this->snapshot->expiresAt));
            $repository->put($this->snapshot->id, [
                ...$status,
                'status' => 'ready',
                'url' => $url,
            ], $ttl);
            $export->notifyReady($this->snapshot, $url);
        } finally {
            App::setLocale($previousLocale);
            $context->release();
        }
    }

    public function failed(?Throwable $exception): void
    {
        $exception ??= new LogicException('The queued export failed.');
        $repository = app(QueuedExportRepository::class);
        $status = $this->status($repository);

        if (in_array($status['status'] ?? null, ['ready', 'expired'], true)) {
            return;
        }

        Storage::disk($this->snapshot->disk)->delete($this->snapshot->path);
        $repository->put($this->snapshot->id, [
            ...$status,
            'status' => 'failed',
            'url' => null,
            'message' => Export::DEFAULT_FAILURE_MESSAGE,
        ], 86400);

        $previousLocale = App::getLocale();

        try {
            $context = app($this->snapshot->contextClass);

            if (! $context instanceof ExportContext) {
                return;
            }

            try {
                $context->restore($this->snapshot->actorId, $this->snapshot->scopeAttributes);
                $this->restoreLocale();
                [, , $export] = $this->resolveDefinition();
                $export->notifyFailure($this->snapshot, $exception);
            } finally {
                App::setLocale($previousLocale);
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

    private function restoreLocale(): void
    {
        if (isset($this->snapshot->locale) && is_string($this->snapshot->locale) && $this->snapshot->locale !== '') {
            App::setLocale($this->snapshot->locale);
        }
    }

    private function executionLockSeconds(): int
    {
        $connection = is_string($this->connection) && $this->connection !== ''
            ? $this->connection
            : config('queue.default');
        $retryAfter = is_string($connection)
            ? config("queue.connections.{$connection}.retry_after")
            : null;

        return max((int) $retryAfter + 60, 120);
    }

    /** @param array<string, mixed> $status */
    private function expire(QueuedExportRepository $repository, array $status): void
    {
        $repository->put($this->snapshot->id, [
            ...$status,
            'status' => 'expired',
            'url' => null,
            'redirect' => null,
            'message' => null,
        ], 86400);
    }
}
