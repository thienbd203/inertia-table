<?php

namespace Musing\InertiaTable\Actions;

use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;

final class QueuedActionRepository
{
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
        $key = $this->idempotencyKey($fingerprint);

        if (Cache::add($key, $id, $ttl)) {
            return null;
        }

        $existing = Cache::get($key);

        return is_string($existing) ? $existing : null;
    }

    public function executionLock(string $id, int $seconds): Lock
    {
        return Cache::lock("inertia-table:queued-action:lock:{$id}", max($seconds, 1));
    }

    /** @param array<string, mixed> $status */
    public function put(string $id, array $status, int $ttl): void
    {
        Cache::put($this->statusKey($id), $status, $ttl);
    }

    /** @param array<string, mixed> $status */
    public function putIfMissing(string $id, array $status, int $ttl): void
    {
        Cache::add($this->statusKey($id), $status, $ttl);
    }

    /** @return array<string, mixed>|null */
    public function get(string $id): ?array
    {
        $status = Cache::get($this->statusKey($id));

        if (! is_array($status)) {
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

    private function idempotencyKey(string $fingerprint): string
    {
        return 'inertia-table:queued-action:request:'.hash('sha256', $fingerprint);
    }

    private function statusKey(string $id): string
    {
        return "inertia-table:queued-action:status:{$id}";
    }
}
