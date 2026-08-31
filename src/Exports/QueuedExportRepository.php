<?php

namespace Musing\InertiaTable\Exports;

use Illuminate\Support\Facades\Cache;

final class QueuedExportRepository
{
    /** @param array<string, mixed> $attributes */
    public function accessHash(
        string $tableClass,
        string $exportKey,
        int|string|null $actorId,
        array $attributes,
    ): string {
        return hash('sha256', json_encode([
            'table' => $tableClass,
            'export' => $exportKey,
            'actor' => $actorId,
            'attributes' => $attributes,
        ], JSON_THROW_ON_ERROR));
    }

    public function reserve(string $fingerprint, string $id, int $ttl): ?string
    {
        $key = $this->idempotencyKey($fingerprint);

        if (Cache::add($key, $id, $ttl)) {
            return null;
        }

        $existing = Cache::get($key);

        return is_string($existing) ? $existing : null;
    }

    /** @param array<string, mixed> $status */
    public function put(string $id, array $status, int $ttl): void
    {
        Cache::put($this->statusKey($id), $status, $ttl);
    }

    /** @return array<string, mixed>|null */
    public function get(string $id): ?array
    {
        $status = Cache::get($this->statusKey($id));

        return is_array($status) ? $status : null;
    }

    public function forget(string $id): void
    {
        Cache::forget($this->statusKey($id));
    }

    /**
     * @param  array<string, mixed>  $status
     * @return array<string, mixed>
     */
    public function forResponse(array $status): array
    {
        unset($status['_accessHash']);

        return $status;
    }

    private function idempotencyKey(string $fingerprint): string
    {
        return 'inertia-table:queued-export:request:'.hash('sha256', $fingerprint);
    }

    private function statusKey(string $id): string
    {
        return "inertia-table:queued-export:status:{$id}";
    }
}
