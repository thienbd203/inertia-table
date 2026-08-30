<?php

namespace Musing\InertiaTable\Actions;

use Closure;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use LogicException;
use Musing\InertiaTable\Selection;

/** @implements Arrayable<string, mixed> */
final class Action implements Arrayable
{
    private string $scope = 'row';

    private bool|Closure $authorized = true;

    private bool|Closure $disabled = false;

    private bool|Closure $hidden = false;

    private ?string $disabledTooltip = null;

    private string $variant = 'default';

    private ?string $icon = null;

    private bool $labelHidden = false;

    private string|Closure|null $tooltip = null;

    private string|Closure|null $buttonClass = null;

    /** @var array<string, string>|null */
    private ?array $confirmation = null;

    private ?string $method = null;

    private string|Closure|null $url = null;

    private ?Closure $handler = null;

    private bool $handlesSelection = false;

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
        $this->scope = 'both';

        return $this;
    }

    public function authorized(bool|Closure $authorized = true): self
    {
        $this->authorized = $authorized;

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

    public function confirm(
        ?string $title = null,
        ?string $message = null,
        ?string $confirmLabel = null,
        ?string $cancelLabel = null,
    ): self {
        $title ??= (string) trans('inertia-table::messages.actions.confirm_title');
        $message ??= (string) trans('inertia-table::messages.actions.confirm_message');
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

    public function execute(Selection $selection): mixed
    {
        if (! $this->handler instanceof Closure) {
            throw new LogicException('This table action does not have a server-side handler.');
        }

        if ($this->handlesSelection) {
            return ($this->handler)($selection);
        }

        $result = null;

        $selection->each(function (Model $model, Selection $selection) use (&$result) {
            $result = ($this->handler)($model, $selection);
        });

        return $result;
    }

    /** @return array<string, mixed> */
    public function resolve(?Model $model = null, ?string $handlerUrl = null): array
    {
        $authorized = $this->authorized instanceof Closure
            ? ($model !== null && (bool) ($this->authorized)($model))
            : $this->authorized;
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
            'endpoint' => $handlerUrl !== null
                ? ['method' => 'post', 'url' => $handlerUrl]
                : ($url === null ? null : ['method' => $this->method, 'url' => $url]),
            'meta' => $this->meta,
        ];
    }

    private function ensureHandlerCanBeDefined(): void
    {
        if ($this->url !== null) {
            throw new LogicException('A table action cannot define both an endpoint and a server-side handler.');
        }
    }

    private function resolveCondition(bool|Closure $condition, ?Model $model): bool
    {
        return $condition instanceof Closure
            ? ($model !== null && (bool) $condition($model))
            : $condition;
    }
}
