<?php

namespace Musing\InertiaTable\Actions;

use Illuminate\Contracts\Support\Arrayable;
use Musing\InertiaTable\Contracts\ActionContext;
use Musing\InertiaTable\Table;

/** @implements Arrayable<string, mixed> */
final readonly class QueuedActionSnapshot implements Arrayable
{
    /**
     * @param  class-string<Table>  $tableClass
     * @param  array<string, mixed>  $selection
     * @param  array<string, mixed>  $scopeAttributes
     * @param  class-string<ActionContext>  $contextClass
     */
    public function __construct(
        public string $id,
        public string $tableClass,
        public string $tableName,
        public string $actionKey,
        public string $definitionFingerprint,
        public array $selection,
        public int|string|null $actorId,
        public array $scopeAttributes,
        public string $contextClass,
        public string $locale,
        public int $dispatchedAt,
        public int $expiresAt,
        public string $idempotencyFingerprint,
    ) {}

    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
