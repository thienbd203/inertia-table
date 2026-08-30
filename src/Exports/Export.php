<?php

namespace Musing\InertiaTable\Exports;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use LogicException;
use Musing\InertiaTable\Contracts\ExportContext;
use Musing\InertiaTable\Table;
use Throwable;

final class Export
{
    private bool|Closure $authorize;

    private string|Closure $label;

    private string|Closure $filename;

    private string $type;

    private ExportScope $scope = ExportScope::All;

    private bool $visibleColumnsOnly = false;

    private bool $queued = false;

    private ?string $queueConnection = null;

    private ?string $queueName = null;

    private ?int $queueDelay = null;

    private ?string $queueDisk = null;

    private ?int $queueExpiry = null;

    /** @var array<string, mixed>|Closure */
    private array|Closure $scopeAttributes = [];

    /** @var class-string<ExportContext> */
    private string $contextClass = AuthenticatedExportContext::class;

    private string|Closure|null $dispatchRedirect = null;

    private ?Closure $deliveryUrlResolver = null;

    private ?Closure $readyCallback = null;

    private ?Closure $failureCallback = null;

    /** @var array<int, object>|Closure */
    private array|Closure $chainedJobs = [];

    /** @var array<string, mixed> */
    private array $meta = [];

    private function __construct(
        public readonly string $key,
        string|Closure $label,
        string|Closure $filename,
        string $type,
        bool|Closure $authorize,
    ) {
        if (! preg_match('/^[A-Za-z0-9_-]+$/', $key)) {
            throw new LogicException('Table export keys may contain only letters, numbers, dashes, and underscores.');
        }

        $this->label = $label;
        $this->filename = $filename;
        $this->type = $this->normalizeType($type);
        $this->authorize = $authorize;
    }

    public static function make(
        string $key = 'csv',
        string|Closure|null $label = null,
        string|Closure|null $filename = null,
        string $type = 'csv',
        bool|Closure $authorize = true,
    ): self {
        return new self(
            $key,
            $label ?? str($key)->headline()->append(' Export')->toString(),
            $filename ?? fn (Table $table) => $table->name().'.'.$type,
            $type,
            $authorize,
        );
    }

    public function label(string|Closure $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function filename(string|Closure $filename): self
    {
        $this->filename = $filename;

        return $this;
    }

    public function type(string $type): self
    {
        $this->type = $this->normalizeType($type);

        return $this;
    }

    public function authorize(bool|Closure $authorize = true): self
    {
        $this->authorize = $authorize;

        return $this;
    }

    public function allRows(): self
    {
        $this->scope = ExportScope::All;

        return $this;
    }

    public function filtered(): self
    {
        $this->scope = ExportScope::Filtered;

        return $this;
    }

    public function selected(): self
    {
        $this->scope = ExportScope::Selected;

        return $this;
    }

    public function visibleColumnsOnly(bool $visibleOnly = true): self
    {
        $this->visibleColumnsOnly = $visibleOnly;

        return $this;
    }

    public function queue(
        ?string $connection = null,
        ?string $queue = null,
        ?int $delay = null,
        ?string $disk = null,
        ?int $expiresAfter = null,
    ): self {
        if (($delay !== null && $delay < 0) || ($expiresAfter !== null && $expiresAfter < 1)) {
            throw new LogicException('Queued export delay must be non-negative and expiry must be positive.');
        }

        $this->queued = true;
        $this->queueConnection = $connection;
        $this->queueName = $queue;
        $this->queueDelay = $delay;
        $this->queueDisk = $disk;
        $this->queueExpiry = $expiresAfter;

        return $this;
    }

    /** @param array<string, mixed>|Closure $attributes */
    public function scopeAttributes(array|Closure $attributes): self
    {
        $this->scopeAttributes = $attributes;

        return $this;
    }

    /** @param class-string<ExportContext> $contextClass */
    public function context(string $contextClass): self
    {
        if (! is_subclass_of($contextClass, ExportContext::class)) {
            throw new LogicException('Queued export contexts must implement '.ExportContext::class.'.');
        }

        $this->contextClass = $contextClass;

        return $this;
    }

    public function redirectAfterDispatch(string|Closure $redirect): self
    {
        $this->dispatchRedirect = $redirect;

        return $this;
    }

    /** @param Closure(QueuedExportSnapshot, string): (string|null) $resolver */
    public function deliveryUrlUsing(Closure $resolver): self
    {
        $this->deliveryUrlResolver = $resolver;

        return $this;
    }

    /** @param Closure(QueuedExportSnapshot, string|null): mixed $callback */
    public function onReady(Closure $callback): self
    {
        $this->readyCallback = $callback;

        return $this;
    }

    /** @param Closure(QueuedExportSnapshot, Throwable): mixed $callback */
    public function onFailure(Closure $callback): self
    {
        $this->failureCallback = $callback;

        return $this;
    }

    /** @param array<int, object>|Closure $jobs */
    public function chain(array|Closure $jobs): self
    {
        $this->chainedJobs = $jobs;

        return $this;
    }

    /** @param array<string, mixed> $meta */
    public function meta(array $meta): self
    {
        $this->meta = $meta;

        return $this;
    }

    public function isAuthorized(Request $request, Table $table): bool
    {
        return $this->authorize instanceof Closure
            ? (bool) app()->call($this->authorize, compact('request', 'table'))
            : $this->authorize;
    }

    public function resolvedLabel(Request $request, Table $table): string
    {
        $label = $this->label instanceof Closure
            ? app()->call($this->label, compact('request', 'table'))
            : $this->label;

        return trim((string) $label);
    }

    public function resolvedFilename(Request $request, Table $table): string
    {
        $filename = $this->filename instanceof Closure
            ? app()->call($this->filename, compact('request', 'table'))
            : $this->filename;
        $filename = basename(str_replace(["\0", "\r", "\n"], '', (string) $filename));

        if ($filename === '' || $filename === '.' || $filename === '..') {
            $filename = $table->name().'.'.$this->type;
        }

        return pathinfo($filename, PATHINFO_EXTENSION) === ''
            ? $filename.'.'.$this->type
            : $filename;
    }

    public function scope(): ExportScope
    {
        return $this->scope;
    }

    public function typeName(): string
    {
        return $this->type;
    }

    public function usesVisibleColumns(): bool
    {
        return $this->visibleColumnsOnly;
    }

    public function isQueued(): bool
    {
        return $this->queued;
    }

    /**
     * @return array{connection: string|null, queue: string|null, delay: int, disk: string, expiresAfter: int, path: string}
     */
    public function queueConfiguration(): array
    {
        $connection = $this->queueConnection ?? config('inertia-table.queue.connection');
        $queue = $this->queueName ?? config('inertia-table.queue.queue');
        $disk = $this->queueDisk ?? config('inertia-table.queue.disk', 'local');
        $path = trim((string) config('inertia-table.queue.path', 'table-exports'), '/');

        return [
            'connection' => is_string($connection) && $connection !== '' ? $connection : null,
            'queue' => is_string($queue) && $queue !== '' ? $queue : null,
            'delay' => $this->queueDelay ?? max((int) config('inertia-table.queue.delay', 0), 0),
            'disk' => is_string($disk) && $disk !== '' ? $disk : 'local',
            'expiresAfter' => $this->queueExpiry ?? max((int) config('inertia-table.queue.expires_after', 604800), 1),
            'path' => $path !== '' ? $path : 'table-exports',
        ];
    }

    /** @return array<string, mixed> */
    public function resolvedScopeAttributes(Request $request, Table $table): array
    {
        $attributes = $this->scopeAttributes instanceof Closure
            ? app()->call($this->scopeAttributes, compact('request', 'table'))
            : $this->scopeAttributes;

        if (! is_array($attributes)) {
            throw new LogicException('Queued export scope attributes must resolve to an array.');
        }

        return $this->normalizeScopeAttributes($attributes);
    }

    /** @return class-string<ExportContext> */
    public function contextClass(): string
    {
        return $this->contextClass;
    }

    public function resolvedDispatchRedirect(Request $request, Table $table): ?string
    {
        $redirect = $this->dispatchRedirect instanceof Closure
            ? app()->call($this->dispatchRedirect, compact('request', 'table'))
            : $this->dispatchRedirect;

        return is_string($redirect) && $redirect !== '' ? $redirect : null;
    }

    public function resolvedDeliveryUrl(QueuedExportSnapshot $snapshot): ?string
    {
        if ($this->deliveryUrlResolver instanceof Closure) {
            $url = app()->call($this->deliveryUrlResolver, [
                'snapshot' => $snapshot,
                'path' => $snapshot->path,
            ]);

            return is_string($url) && $url !== '' ? $url : null;
        }

        try {
            return Storage::disk($snapshot->disk)->url($snapshot->path);
        } catch (Throwable) {
            return null;
        }
    }

    public function notifyReady(QueuedExportSnapshot $snapshot, ?string $url): void
    {
        if ($this->readyCallback instanceof Closure) {
            app()->call($this->readyCallback, compact('snapshot', 'url'));
        }
    }

    public function notifyFailure(QueuedExportSnapshot $snapshot, Throwable $exception): void
    {
        if ($this->failureCallback instanceof Closure) {
            app()->call($this->failureCallback, compact('snapshot', 'exception'));
        }
    }

    /** @return array<int, object> */
    public function resolvedChain(Request $request, Table $table, QueuedExportSnapshot $snapshot): array
    {
        $jobs = $this->chainedJobs instanceof Closure
            ? app()->call($this->chainedJobs, compact('request', 'table', 'snapshot'))
            : $this->chainedJobs;

        if (! is_array($jobs) || collect($jobs)->contains(
            fn (mixed $job) => ! is_object($job) || $job instanceof Closure,
        )) {
            throw new LogicException('Queued export chains must resolve to an array of job objects.');
        }

        return array_values($jobs);
    }

    /** @return array<string, mixed> */
    public function metadata(): array
    {
        return $this->meta;
    }

    /** @return array<string, mixed> */
    public function resolve(Request $request, Table $table, string $endpoint): array
    {
        return [
            'key' => $this->key,
            'label' => $this->resolvedLabel($request, $table),
            'filename' => $this->resolvedFilename($request, $table),
            'type' => $this->type,
            'scope' => $this->scope->value,
            'requiresSelection' => $this->scope === ExportScope::Selected,
            'queued' => $this->queued,
            'endpoint' => $endpoint,
            'meta' => $this->meta,
        ];
    }

    private function normalizeType(string $type): string
    {
        $type = strtolower(trim($type));

        if (! preg_match('/^[a-z0-9_-]+$/', $type)) {
            throw new LogicException('Table export types may contain only lowercase letters, numbers, dashes, and underscores.');
        }

        return $type;
    }

    /** @return array<string, mixed> */
    private function normalizeScopeAttributes(array $values): array
    {
        foreach ($values as $key => $value) {
            if (is_array($value)) {
                $values[$key] = $this->normalizeScopeAttributes($value);
            } elseif (! is_string($value) && ! is_int($value) && ! is_float($value) && ! is_bool($value) && $value !== null) {
                throw new LogicException('Queued export scope attributes may contain only scalar, array, or null values.');
            }
        }

        if (! array_is_list($values)) {
            ksort($values);
        }

        return $values;
    }
}
