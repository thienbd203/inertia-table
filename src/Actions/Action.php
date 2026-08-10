<?php

namespace Toolbelt\InertiaTable\Actions;

use Closure;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/** @implements Arrayable<string, mixed> */
final class Action implements Arrayable
{
    private string $scope = 'row';

    private bool|Closure $authorized = true;

    private string $variant = 'default';

    /** @var array<string, string>|null */
    private ?array $confirmation = null;

    private ?string $method = null;

    private string|Closure|null $url = null;

    /** @var array<string, mixed> */
    private array $meta = [];

    private function __construct(
        public readonly string $key,
        public readonly string $label,
    ) {}

    public static function make(string $key, ?string $label = null): self
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

    public function destructive(bool $destructive = true): self
    {
        $this->variant = $destructive ? 'destructive' : 'default';

        return $this;
    }

    public function endpoint(string $method, string|Closure $url): self
    {
        $method = strtolower($method);

        if (! in_array($method, ['get', 'post', 'patch', 'delete'], true)) {
            throw new LogicException('Table actions support only GET, POST, PATCH, and DELETE endpoints.');
        }

        $this->method = $method;
        $this->url = $url;

        return $this;
    }

    public function confirm(
        string $title,
        string $message,
        string $confirmLabel = 'Confirm',
        string $cancelLabel = 'Cancel',
    ): self {
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

    /** @return array<string, mixed> */
    public function resolve(?Model $model = null): array
    {
        if ($this->method === null || $this->url === null) {
            throw new LogicException("Table action [{$this->key}] must define an endpoint.");
        }

        $authorized = $this->authorized instanceof Closure
            ? ($model !== null && (bool) ($this->authorized)($model))
            : $this->authorized;
        $url = $this->url instanceof Closure
            ? ($model === null ? null : ($this->url)($model))
            : $this->url;

        return [
            'key' => $this->key,
            'label' => $this->label,
            'scope' => $this->scope,
            'authorized' => $authorized,
            'variant' => $this->variant,
            'confirmation' => $this->confirmation,
            'endpoint' => $url === null ? null : ['method' => $this->method, 'url' => $url],
            'meta' => $this->meta,
        ];
    }
}
