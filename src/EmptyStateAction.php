<?php

namespace Musing\InertiaTable;

use Closure;
use Musing\InertiaTable\Support\DataAttributes;

final class EmptyStateAction
{
    /**
     * @param  string|Closure(Url): (Url|string|null)  $url
     * @param  array<string, mixed>  $dataAttributes
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public readonly string $label,
        private string|Closure $url,
        public readonly Variant $variant = Variant::Default,
        public readonly ?string $icon = null,
        public readonly ?string $buttonClass = null,
        private array $dataAttributes = [],
        public readonly array $meta = [],
    ) {}

    /** @return array<string, mixed>|null */
    public function resolve(): ?array
    {
        $url = Url::make();
        $resolved = $this->url instanceof Closure
            ? ($this->url)($url)
            : $this->url;

        if (is_string($resolved)) {
            $url->to($resolved);
        } elseif ($resolved instanceof Url) {
            $url = $resolved;
        }

        if (! $url->hasUrl() || $url->isHidden()) {
            return null;
        }

        return [
            'label' => $this->label,
            'url' => $url->toArray(),
            'variant' => $this->variant->value,
            'icon' => $this->icon,
            'buttonClass' => $this->buttonClass,
            'dataAttributes' => DataAttributes::normalize($this->dataAttributes),
            'meta' => $this->meta,
        ];
    }
}
