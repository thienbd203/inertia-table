<?php

namespace Musing\InertiaTable\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Musing\InertiaTable\Exports\QueuedExportRepository;

final class CleanupQueuedExport implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $id,
        public readonly string $disk,
        public readonly string $path,
    ) {}

    public function handle(QueuedExportRepository $repository): void
    {
        $lock = $repository->executionLock($this->id, 120);

        if (! $lock->get()) {
            $this->release(5);

            return;
        }

        try {
            $this->cleanup($repository);
        } finally {
            $lock->release();
        }
    }

    private function cleanup(QueuedExportRepository $repository): void
    {
        $status = $repository->get($this->id);

        if (($status['status'] ?? null) !== 'ready') {
            return;
        }

        Storage::disk($this->disk)->delete($this->path);
        $repository->put($this->id, [
            ...$status,
            'status' => 'expired',
            'url' => null,
        ], 86400);
    }
}
