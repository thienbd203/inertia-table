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
        Storage::disk($this->disk)->delete($this->path);
        $status = $repository->get($this->id);

        if ($status !== null) {
            $repository->put($this->id, [
                ...$status,
                'status' => 'expired',
                'url' => null,
            ], 86400);
        }
    }
}
