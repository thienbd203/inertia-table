<?php

namespace Musing\InertiaTable;

use Closure;
use Illuminate\Contracts\Support\Arrayable;
use Musing\InertiaTable\Support\DataAttributes;

/** @implements Arrayable<string, mixed> */
final class EmptyState implements Arrayable
{
    /** @var array<int, EmptyStateAction> */
    private array $actions = [];

    /**
     * @param  array<string, mixed>  $dataAttributes
     * @param  array<string, mixed>  $meta
     */
    private function __construct(
        private ?string $title = null,
        private ?string $message = null,
        private string|false|null $icon = 'Inbox',
        private array $dataAttributes = [],
        private array $meta = [],
    ) {}

    /**
     * @param  array<string, mixed>  $dataAttributes
     * @param  array<string, mixed>  $meta
     */
    public static function make(
        ?string $title = null,
        ?string $message = null,
        string|false|null $icon = 'Inbox',
        array $dataAttributes = [],
        array $meta = [],
    ): self {
        return new self($title, $message, $icon, $dataAttributes, $meta);
    }

    public function title(?string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function message(?string $message): self
    {
        $this->message = $message;

        return $this;
    }

    public function icon(string|false|null $icon): self
    {
        $this->icon = $icon;

        return $this;
    }

    /**
     * @param  string|Closure(Url): (Url|string|null)  $url
     * @param  array<string, mixed>  $dataAttributes
     * @param  array<string, mixed>  $meta
     */
    public function action(
        string $label,
        string|Closure $url,
        Variant $variant = Variant::Default,
        ?string $icon = null,
        ?string $buttonClass = null,
        array $dataAttributes = [],
        array $meta = [],
    ): self {
        $this->actions[] = new EmptyStateAction(
            $label,
            $url,
            $variant,
            $icon,
            $buttonClass,
            $dataAttributes,
            $meta,
        );

        return $this;
    }

    /** @param array<string, mixed> $attributes */
    public function dataAttributes(array $attributes): self
    {
        $this->dataAttributes = $attributes;

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
        return [
            'title' => $this->title ?? (string) trans('inertia-table::messages.empty_state.title'),
            'message' => $this->message,
            'icon' => $this->icon,
            'actions' => collect($this->actions)
                ->map(fn (EmptyStateAction $action) => $action->resolve())
                ->filter()
                ->values()
                ->all(),
            'dataAttributes' => DataAttributes::normalize($this->dataAttributes),
            'meta' => $this->meta,
        ];
    }
}
