<?php

namespace Musing\InertiaTable\Actions;

use Illuminate\Contracts\Cache\Lock;
use Musing\InertiaTable\Support\QueuedOperationCache;

final class QueuedActionRepository
{
    private QueuedOperationCache $cache;

    public function __construct()
    {
        $this->cache = new QueuedOperationCache('inertia-table:queued-action');
    }

    /** @param array<string, mixed> $attributes */
    public function accessHash(
        string $tableClass,
        string $actionKey,
        int|string|null $actorId,
        array $attributes,
    ): string {
        return hash('sha256', json_encode([
            'table' => $tableClass,
            'action' => $actionKey,
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

    /** @param array<string, mixed> $status */
    public function putIfMissing(string $id, array $status, int $ttl): void
    {
        $this->cache->putIfMissing($id, $status, $ttl);
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
            $retention = max((int) ($status['_statusRetention'] ?? 86400), 1);
            $status = [
                ...$status,
                'status' => 'expired',
                'result' => null,
                'redirect' => null,
                'message' => null,
            ];
            $this->put($id, $status, $retention);
        }

        return $status;
    }

    /** @param array<string, mixed> $status */
    public function forResponse(array $status): array
    {
        unset($status['_accessHash'], $status['_statusRetention']);

        return $status;
    }
}
