<?php

namespace Toolbelt\InertiaTable\Actions;

use Illuminate\Contracts\Support\Arrayable;
use LogicException;

/** @implements Arrayable<string, mixed> */
final class Action implements Arrayable
{
    private string $scope = 'row';

    private bool $authorized = true;

    private string $variant = 'default';

    /** @var array<string, string>|null */
    private ?array $confirmation = null;

    private ?string $method = null;

    private ?string $url = null;

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

    public function authorized(bool $authorized = true): self
    {
        $this->authorized = $authorized;

        return $this;
    }

    public function destructive(bool $destructive = true): self
    {
        $this->variant = $destructive ? 'destructive' : 'default';

        return $this;
    }

    public function endpoint(string $method, string $url): self
    {
        $method = strtolower($method);

        if (! in_array($method, ['post', 'patch', 'delete'], true)) {
            throw new LogicException('Table actions support only POST, PATCH, and DELETE endpoints.');
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
        if ($this->method === null || $this->url === null) {
            throw new LogicException("Table action [{$this->key}] must define an endpoint.");
        }

        return [
            'key' => $this->key,
            'label' => $this->label,
            'scope' => $this->scope,
            'authorized' => $this->authorized,
            'variant' => $this->variant,
            'confirmation' => $this->confirmation,
            'endpoint' => ['method' => $this->method, 'url' => $this->url],
            'meta' => $this->meta,
        ];
    }
}
