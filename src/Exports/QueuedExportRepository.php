<?php

namespace Musing\InertiaTable\Exports;

use Illuminate\Contracts\Cache\Lock;
use Musing\InertiaTable\Support\QueuedOperationCache;

final class QueuedExportRepository
{
    private QueuedOperationCache $cache;

    public function __construct()
    {
        $this->cache = new QueuedOperationCache('inertia-table:queued-export');
    }

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
        return $this->cache->reserve($fingerprint, $id, $ttl);
    }

    public function executionLock(string $id, int $seconds): Lock
    {
        return $this->cache->executionLock($id, $seconds);
    }

    /** @param array<string, mixed> $status */
    public function put(string $id, array $status, int $ttl): void
    {
        $this->cache->put($id, $status, $ttl);
    }

    /** @return array<string, mixed>|null */
    public function get(string $id): ?array
    {
        $status = $this->cache->get($id);

        if ($status === null) {
            return null;
        }

        $expiresAt = $status['expiresAt'] ?? null;

        if (is_int($expiresAt) && $expiresAt <= time() && ($status['status'] ?? null) !== 'expired') {
            $status = [
                ...$status,
                'status' => 'expired',
                'url' => null,
                'redirect' => null,
                'message' => null,
            ];
            $this->put($id, $status, 86400);
        }

        return $status;
    }

    public function forget(string $id): void
    {
        $this->cache->forget($id);
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
}
