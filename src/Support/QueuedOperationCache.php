<?php

namespace Musing\InertiaTable\Support;

use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;

/** @internal Cache-key and storage mechanics shared by queued table operations. */
final readonly class QueuedOperationCache
{
    public function __construct(private string $prefix) {}

    public function reserve(string $fingerprint, string $id, int $ttl): ?string
    {
        $key = "{$this->prefix}:request:".hash('sha256', $fingerprint);

        if (Cache::add($key, $id, $ttl)) {
            return null;
        }

        $existing = Cache::get($key);

        return is_string($existing) ? $existing : null;
    }

    public function executionLock(string $id, int $seconds): Lock
    {
        return Cache::lock("{$this->prefix}:lock:{$id}", max($seconds, 1));
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

        return is_array($status) ? $status : null;
    }

    public function forget(string $id): void
    {
        Cache::forget($this->statusKey($id));
    }

    private function statusKey(string $id): string
    {
        return "{$this->prefix}:status:{$id}";
    }
}
