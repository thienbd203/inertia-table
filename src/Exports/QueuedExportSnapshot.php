<?php

namespace Musing\InertiaTable\Exports;

use Illuminate\Contracts\Support\Arrayable;
use Musing\InertiaTable\Contracts\ExportContext;
use Musing\InertiaTable\Table;

/** @implements Arrayable<string, mixed> */
final readonly class QueuedExportSnapshot implements Arrayable
{
    /**
     * @param  class-string<Table>  $tableClass
     * @param  array<string, mixed>  $state
     * @param  array<string, mixed>|null  $selection
     * @param  array<string, mixed>  $scopeAttributes
     * @param  class-string<ExportContext>  $contextClass
     */
    public function __construct(
        public string $id,
        public string $tableClass,
        public string $exportKey,
        public string $type,
        public string $scope,
        public array $state,
        public ?array $selection,
        public int|string|null $actorId,
        public array $scopeAttributes,
        public string $contextClass,
        public string $disk,
        public string $path,
        public string $filename,
        public int $expiresAt,
    ) {}

    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
