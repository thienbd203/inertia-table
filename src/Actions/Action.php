<?php

namespace Musing\InertiaTable\Actions;

use Closure;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use LogicException;
use Musing\InertiaTable\Contracts\ActionContext;
use Musing\InertiaTable\Selection;
use Musing\InertiaTable\Table;
use ReflectionFunction;
use Throwable;

/** @implements Arrayable<string, mixed> */
final class Action implements Arrayable
{
    private string $scope = 'row';

    private bool|Closure $authorized = true;

    private bool|Closure $authorize = true;

    private bool|Closure $disabled = false;

    private bool|Closure $hidden = false;

    private ?string $disabledTooltip = null;

    private string $variant = 'default';

    private ?string $icon = null;

    private bool $labelHidden = false;

    private string|Closure|null $tooltip = null;

    private string|Closure|null $buttonClass = null;

    /** @var array{title: string|array<int, string>, message: string|array<int, string>, confirmLabel: string, cancelLabel: string}|null */
    private ?array $confirmation = null;

    private ?string $method = null;

    private string|Closure|null $url = null;

    private ?Closure $handler = null;

    private bool $handlesSelection = false;

    private ?Closure $before = null;

    private string|Closure|null $after = null;

    private int $chunkSize = 1000;

    private bool $queued = false;

    private ?string $queueConnection = null;

    private ?string $queueName = null;

    private ?int $queueDelay = null;

    private ?int $queueExpiry = null;

    private ?bool $queueAfterCommit = null;

    /** @var array<string, mixed>|Closure */
    private array|Closure $scopeAttributes = [];

    /** @var class-string<ActionContext> */
    private string $contextClass = AuthenticatedActionContext::class;

    /** @var array<int, object>|Closure */
    private array|Closure $middleware = [];

    /** @var array<int, string>|Closure */
    private array|Closure $tags = [];

    /** @var array<int, object>|Closure */
    private array|Closure $chainedJobs = [];

    private string|Closure|null $dispatchRedirect = null;

    private ?Closure $completedCallback = null;

    private ?Closure $failureCallback = null;

    private string $failureMessage = 'The queued action failed.';

    /** @var array<string, mixed> */
    private array $meta = [];

    private function __construct(
        public readonly string $key,
        private string|Closure $label,
    ) {}

    public static function make(string $key, string|Closure|null $label = null): self
    {
        return new self($key, $label ?? str($key)->headline()->toString());
    }

    public function row(): self
    {
        if ($this->queued) {
            throw new LogicException('Queued table actions must keep bulk scope.');
        }

        $this->scope = 'row';

        return $this;
    }

    public function bulk(): self
    {
        $this->scope = 'bulk';

        return $this;
    }

    public function rowAndBulk(): self
    {
        if ($this->queued) {
            throw new LogicException('Queued table actions must keep bulk scope.');
        }

        $this->scope = 'both';

        return $this;
    }

    public function authorized(bool|Closure $authorized = true): self
    {
        $this->authorized = $authorized;

        return $this;
    }

    /** @param bool|Closure(Request): bool $authorized */
    public function authorize(bool|Closure $authorized = true): self
    {
        $this->authorize = $authorized;

        return $this;
    }

    public function disabled(bool|Closure $disabled = true): self
    {
        $this->disabled = $disabled;

        return $this;
    }

    public function hidden(bool|Closure $hidden = true): self
    {
        $this->hidden = $hidden;

        return $this;
    }

    public function disabledAndHidden(bool|Closure $condition = true): self
    {
        return $this->disabled($condition)->hidden($condition);
    }

    public function disabledTooltip(?string $tooltip): self
    {
        $this->disabledTooltip = $tooltip;

        return $this;
    }

    public function destructive(bool $destructive = true): self
    {
        $this->variant = $destructive ? 'destructive' : 'default';

        return $this;
    }

    public function icon(?string $icon): self
    {
        $this->icon = $icon;

        return $this;
    }

    public function hideLabel(bool $hidden = true): self
    {
        $this->labelHidden = $hidden;

        return $this;
    }

    /** @param string|Closure(Model|null): string|null $tooltip */
    public function tooltip(string|Closure|null $tooltip): self
    {
        $this->tooltip = $tooltip;

        return $this;
    }

    /** @param string|Closure(Model|null): string|null $class */
    public function buttonClass(string|Closure|null $class): self
    {
        $this->buttonClass = $class;

        return $this;
    }

    public function endpoint(string $method, string|Closure $url): self
    {
        if ($this->handler instanceof Closure) {
            throw new LogicException('A table action cannot define both an endpoint and a server-side handler.');
        }

        $method = strtolower($method);

        if (! in_array($method, ['get', 'post', 'put', 'patch', 'delete'], true)) {
            throw new LogicException('Table actions support only GET, POST, PUT, PATCH, and DELETE endpoints.');
        }

        $this->method = $method;
        $this->url = $url;

        return $this;
    }

    /** @param Closure(Model, Selection): mixed $handler */
    public function handle(Closure $handler): self
    {
        $this->ensureHandlerCanBeDefined();
        $this->handler = $handler;
        $this->handlesSelection = false;

        return $this;
    }

    /** @param Closure(Selection): mixed $handler */
    public function handleSelection(Closure $handler): self
    {
        $this->ensureHandlerCanBeDefined();
        $this->handler = $handler;
        $this->handlesSelection = true;

        return $this;
    }

    /** @param Closure(Selection): mixed $callback */
    public function before(Closure $callback): self
    {
        $this->before = $callback;

        return $this;
    }

    /** @param string|Closure(Selection, mixed): mixed $callback */
    public function after(string|Closure $callback): self
    {
        $this->after = $callback;

        return $this;
    }

    public function chunkSize(int $chunkSize): self
    {
        if ($chunkSize < 1 || $chunkSize > 10_000) {
            throw new LogicException('The table action chunk size must be between 1 and 10,000.');
        }

        $this->chunkSize = $chunkSize;

        return $this;
    }

    public function queue(
        ?string $connection = null,
        ?string $queue = null,
        ?int $delay = null,
        ?int $expiresAfter = null,
        ?bool $afterCommit = null,
    ): self {
        if (! $this->handler instanceof Closure || $this->scope !== 'bulk') {
            throw new LogicException('Only server-managed bulk table actions may be queued. Define bulk() and handle() or handleSelection() before queue().');
        }

        if (($delay !== null && $delay < 0) || ($expiresAfter !== null && $expiresAfter < 1)) {
            throw new LogicException('Queued action delay must be non-negative and expiry must be positive.');
        }

        $this->queued = true;
        $this->queueConnection = $connection;
        $this->queueName = $queue;
        $this->queueDelay = $delay;
        $this->queueExpiry = $expiresAfter;
        $this->queueAfterCommit = $afterCommit;

        return $this;
    }

    /** @param array<string, mixed>|Closure $attributes */
    public function scopeAttributes(array|Closure $attributes): self
    {
        $this->scopeAttributes = $attributes;

        return $this;
    }

    /** @param class-string<ActionContext> $contextClass */
    public function context(string $contextClass): self
    {
        if (! is_subclass_of($contextClass, ActionContext::class)) {
            throw new LogicException('Queued action contexts must implement '.ActionContext::class.'.');
        }

        $this->contextClass = $contextClass;

        return $this;
    }

    /** @param array<int, object>|Closure $middleware */
    public function middleware(array|Closure $middleware): self
    {
        $this->middleware = $middleware;

        return $this;
    }

    /** @param array<int, string>|Closure $tags */
    public function tags(array|Closure $tags): self
    {
        $this->tags = $tags;

        return $this;
    }

    /** @param array<int, object>|Closure $jobs */
    public function chain(array|Closure $jobs): self
    {
        $this->chainedJobs = $jobs;

        return $this;
    }

    public function redirectAfterDispatch(string|Closure $redirect): self
    {
        $this->dispatchRedirect = $redirect;

        return $this;
    }

    /** @param Closure(QueuedActionSnapshot, mixed): mixed $callback */
    public function onCompleted(Closure $callback): self
    {
        $this->completedCallback = $callback;

        return $this;
    }

    /** @param Closure(QueuedActionSnapshot, Throwable): mixed $callback */
    public function onFailure(Closure $callback): self
    {
        $this->failureCallback = $callback;

        return $this;
    }

    public function failureMessage(string $message): self
    {
        $this->failureMessage = trim($message) !== '' ? $message : 'The queued action failed.';

        return $this;
    }

    public function confirm(
        string|array|null $title = null,
        string|array|null $message = null,
        ?string $confirmLabel = null,
        ?string $cancelLabel = null,
    ): self {
        $title = $this->normalizeConfirmationCopy(
            $title,
            (string) trans('inertia-table::messages.actions.confirm_title'),
        );
        $message = $this->normalizeConfirmationCopy(
            $message,
            (string) trans('inertia-table::messages.actions.confirm_message'),
        );
        $confirmLabel ??= (string) trans('inertia-table::messages.actions.confirm');
        $cancelLabel ??= (string) trans('inertia-table::messages.actions.cancel');

        $this->confirmation = compact('title', 'message', 'confirmLabel', 'cancelLabel');

        return $this;
    }

    /** @param array<string, mixed> $meta */
    public function meta(array $meta): self
    {
        $this->meta = $meta;

        return $this;
    }

    public function toArray(): array
    {
        return $this->resolve();
    }

    public function isRowAction(): bool
    {
        return in_array($this->scope, ['row', 'both'], true);
    }

    public function isBulkAction(): bool
    {
        return in_array($this->scope, ['bulk', 'both'], true);
    }

    public function hasHandler(): bool
    {
        return $this->handler instanceof Closure;
    }

    public function handlesSelection(): bool
    {
        return $this->handlesSelection;
    }

    public function isQueued(): bool
    {
        return $this->queued;
    }

    public function execute(
        Selection $selection,
        bool $skipUnavailableModels = false,
        ?Request $request = null,
        ?Closure $onProgress = null,
    ): mixed {
        if (! $this->handler instanceof Closure) {
            throw new LogicException('This table action does not have a server-side handler.');
        }

        if ($this->before instanceof Closure) {
            ($this->before)($selection);
        }

        if ($this->handlesSelection) {
            $result = ($this->handler)($selection);
        } else {
            $result = null;
            $handler = $this->handler;
            $processed = 0;
            $succeeded = 0;
            $skipped = 0;

            $selection->each(function (Model $model, Selection $selection) use (
                &$processed,
                &$result,
                &$skipped,
                &$succeeded,
                $handler,
                $onProgress,
                $request,
                $skipUnavailableModels,
            ) {
                $processed++;

                if ($skipUnavailableModels && (
                    ! $selection->isSelectable($model)
                    || ! $this->isAvailableFor($model, $request)
                )) {
                    $skipped++;

                    if ($onProgress instanceof Closure && $processed % $this->chunkSize === 0) {
                        $onProgress($processed, $succeeded, $skipped);
                    }

                    return;
                }

                $result = $handler($model, $selection);
                $succeeded++;

                if ($onProgress instanceof Closure && $processed % $this->chunkSize === 0) {
                    $onProgress($processed, $succeeded, $skipped);
                }
            }, $this->chunkSize);

            if ($onProgress instanceof Closure && $processed % $this->chunkSize !== 0) {
                $onProgress($processed, $succeeded, $skipped);
            }
        }

        if (is_string($this->after)) {
            return redirect()->to($this->after);
        }

        if ($this->after instanceof Closure) {
            $afterResult = ($this->after)($selection, $result);

            if (is_string($afterResult) && $afterResult !== '') {
                return redirect()->to($afterResult);
            }

            return $afterResult ?? $result;
        }

        return $result;
    }

    /** @return array<string, mixed> */
    public function resolve(?Model $model = null, ?string $handlerUrl = null, ?Request $request = null): array
    {
        $authorized = $this->resolveAuthorization($model, $request);
        $disabled = $this->resolveCondition($this->disabled, $model);
        $hidden = $this->resolveCondition($this->hidden, $model);
        $label = $this->label instanceof Closure
            ? ($this->label)($model)
            : $this->label;
        $url = $this->url instanceof Closure
            ? ($model === null ? null : ($this->url)($model))
            : $this->url;
        $buttonClass = $this->buttonClass instanceof Closure
            ? ($this->buttonClass)($model)
            : $this->buttonClass;
        $tooltip = $this->tooltip instanceof Closure
            ? ($this->tooltip)($model)
            : $this->tooltip;

        return [
            'key' => $this->key,
            'label' => $label,
            'scope' => $this->scope,
            'authorized' => $authorized,
            'disabled' => $disabled,
            'hidden' => $hidden,
            'variant' => $this->variant,
            'icon' => $this->icon,
            'labelHidden' => $this->labelHidden,
            'tooltip' => is_string($tooltip) && $tooltip !== '' ? $tooltip : null,
            'buttonClass' => is_string($buttonClass) && $buttonClass !== '' ? $buttonClass : null,
            'disabledTooltip' => $this->disabledTooltip,
            'confirmation' => $this->confirmation,
            ...($this->queued ? ['queued' => true] : []),
            'endpoint' => $handlerUrl !== null
                ? ['method' => 'post', 'url' => $handlerUrl]
                : ($url === null ? null : ['method' => $this->method, 'url' => $url]),
            'meta' => $this->meta,
        ];
    }

    /** @return array{connection: string|null, queue: string|null, delay: int, expiresAfter: int, statusRetention: int, afterCommit: bool} */
    public function queueConfiguration(): array
    {
        $this->ensureQueueDefinitionIsValid();
        $connection = $this->queueConnection
            ?? config('inertia-table.actions.queue.connection')
            ?? config('inertia-table.queue.connection');
        $queue = $this->queueName
            ?? config('inertia-table.actions.queue.queue')
            ?? config('inertia-table.queue.queue');

        return [
            'connection' => is_string($connection) && $connection !== '' ? $connection : null,
            'queue' => is_string($queue) && $queue !== '' ? $queue : null,
            'delay' => $this->queueDelay
                ?? max((int) config('inertia-table.actions.queue.delay', config('inertia-table.queue.delay', 0)), 0),
            'expiresAfter' => $this->queueExpiry
                ?? max((int) config('inertia-table.actions.queue.expires_after', 86400), 1),
            'statusRetention' => max((int) config('inertia-table.actions.queue.status_retention', 86400), 1),
            'afterCommit' => $this->queueAfterCommit
                ?? (bool) config('inertia-table.actions.queue.after_commit', true),
        ];
    }

    /** @return array<string, mixed> */
    public function resolvedScopeAttributes(Request $request, Table $table): array
    {
        $attributes = $this->scopeAttributes instanceof Closure
            ? app()->call($this->scopeAttributes, compact('request', 'table'))
            : $this->scopeAttributes;

        if (! is_array($attributes)) {
            throw new LogicException('Queued action scope attributes must resolve to an array.');
        }

        return $this->normalizeScopeAttributes($attributes);
    }

    /** @return class-string<ActionContext> */
    public function contextClass(): string
    {
        return $this->contextClass;
    }

    /** @return array<int, object> */
    public function resolvedMiddleware(Request $request, Table $table, QueuedActionSnapshot $snapshot): array
    {
        $middleware = $this->middleware instanceof Closure
            ? app()->call($this->middleware, compact('request', 'table', 'snapshot'))
            : $this->middleware;

        return $this->normalizeJobObjects($middleware, 'middleware');
    }

    /** @return array<int, string> */
    public function resolvedTags(Request $request, Table $table, QueuedActionSnapshot $snapshot): array
    {
        $tags = $this->tags instanceof Closure
            ? app()->call($this->tags, compact('request', 'table', 'snapshot'))
            : $this->tags;

        if (! is_array($tags) || collect($tags)->contains(fn (mixed $tag) => ! is_string($tag) || trim($tag) === '')) {
            throw new LogicException('Queued action tags must resolve to an array of non-empty strings.');
        }

        return array_values(array_unique($tags));
    }

    /** @return array<int, object> */
    public function resolvedChain(Request $request, Table $table, QueuedActionSnapshot $snapshot): array
    {
        $jobs = $this->chainedJobs instanceof Closure
            ? app()->call($this->chainedJobs, compact('request', 'table', 'snapshot'))
            : $this->chainedJobs;

        return $this->normalizeJobObjects($jobs, 'chains');
    }

    public function resolvedDispatchRedirect(Request $request, Table $table): ?string
    {
        $redirect = $this->dispatchRedirect instanceof Closure
            ? app()->call($this->dispatchRedirect, compact('request', 'table'))
            : $this->dispatchRedirect;

        return is_string($redirect) && trim($redirect) !== '' ? $redirect : null;
    }

    public function notifyCompleted(QueuedActionSnapshot $snapshot, mixed $result): void
    {
        if ($this->completedCallback instanceof Closure) {
            app()->call($this->completedCallback, compact('snapshot', 'result'));
        }
    }

    public function notifyFailure(QueuedActionSnapshot $snapshot, Throwable $exception): void
    {
        if ($this->failureCallback instanceof Closure) {
            app()->call($this->failureCallback, compact('snapshot', 'exception'));
        }
    }

    public function publicFailureMessage(): string
    {
        return $this->failureMessage;
    }

    public function definitionFingerprint(): string
    {
        return hash('sha256', json_encode([
            'key' => $this->key,
            'scope' => $this->scope,
            'selectionHandler' => $this->handlesSelection,
            'chunkSize' => $this->chunkSize,
            'context' => $this->contextClass,
            'handler' => $this->callbackFingerprint($this->handler),
            'before' => $this->callbackFingerprint($this->before),
            'after' => $this->callbackFingerprint($this->after),
        ], JSON_THROW_ON_ERROR));
    }

    private function ensureHandlerCanBeDefined(): void
    {
        if ($this->url !== null) {
            throw new LogicException('A table action cannot define both an endpoint and a server-side handler.');
        }
    }

    private function ensureQueueDefinitionIsValid(): void
    {
        if (! $this->queued || ! $this->handler instanceof Closure || $this->scope !== 'bulk') {
            throw new LogicException('Only server-managed bulk table actions may use queued execution.');
        }
    }

    /**
     * @param  string|array<int, mixed>|null  $copy
     * @return string|array<int, string>
     */
    private function normalizeConfirmationCopy(string|array|null $copy, string $default): string|array
    {
        if ($copy === null) {
            return $default;
        }

        if (is_string($copy)) {
            return $copy;
        }

        $variants = [];

        foreach ($copy as $variant) {
            if (! is_string($variant)) {
                throw new LogicException('Confirmation copy arrays must contain singular, plural, and optionally all-matching strings.');
            }

            $variants[] = $variant;
        }

        if (count($variants) < 2 || count($variants) > 3) {
            throw new LogicException('Confirmation copy arrays must contain singular, plural, and optionally all-matching strings.');
        }

        return $variants;
    }

    private function isAvailableFor(Model $model, ?Request $request = null): bool
    {
        return $this->resolveAuthorization($model, $request)
            && ! $this->resolveCondition($this->disabled, $model)
            && ! $this->resolveCondition($this->hidden, $model);
    }

    private function resolveAuthorization(?Model $model, ?Request $request = null): bool
    {
        $request ??= request();
        $globallyAuthorized = $this->authorize instanceof Closure
            ? (bool) ($this->authorize)($request)
            : $this->authorize;
        $modelAuthorized = $this->authorized instanceof Closure
            ? ($model !== null && (bool) ($this->authorized)($model))
            : $this->authorized;

        return $globallyAuthorized && $modelAuthorized;
    }

    private function resolveCondition(bool|Closure $condition, ?Model $model): bool
    {
        return $condition instanceof Closure
            ? ($model !== null && (bool) $condition($model))
            : $condition;
    }

    /** @return array<string, mixed> */
    private function normalizeScopeAttributes(array $values): array
    {
        foreach ($values as $key => $value) {
            if (is_array($value)) {
                $values[$key] = $this->normalizeScopeAttributes($value);
            } elseif (! is_string($value) && ! is_int($value) && ! is_float($value) && ! is_bool($value) && $value !== null) {
                throw new LogicException('Queued action scope attributes may contain only scalar, array, or null values.');
            }
        }

        if (! array_is_list($values)) {
            ksort($values);
        }

        return $values;
    }

    /** @return array<int, object> */
    private function normalizeJobObjects(mixed $objects, string $kind): array
    {
        if (! is_array($objects) || collect($objects)->contains(
            fn (mixed $object) => ! is_object($object) || $object instanceof Closure,
        )) {
            throw new LogicException("Queued action {$kind} must resolve to an array of objects.");
        }

        return array_values($objects);
    }

    /** @return array<string, int|string|null>|string|null */
    private function callbackFingerprint(string|Closure|null $callback): array|string|null
    {
        if (! $callback instanceof Closure) {
            return $callback;
        }

        $reflection = new ReflectionFunction($callback);
        $file = $reflection->getFileName();
        $source = null;

        if (is_string($file) && is_file($file)) {
            $lines = file($file);

            if (is_array($lines)) {
                $source = hash('sha256', implode('', array_slice(
                    $lines,
                    max($reflection->getStartLine() - 1, 0),
                    max($reflection->getEndLine() - $reflection->getStartLine() + 1, 1),
                )));
            }
        }

        return [
            'source' => $source,
            'start' => $reflection->getStartLine(),
            'end' => $reflection->getEndLine(),
        ];
    }
}
