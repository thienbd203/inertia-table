<?php

namespace Toolbelt\InertiaTable\Filters;

use Illuminate\Contracts\Support\Arrayable;
use Spatie\QueryBuilder\AllowedFilter;

/** @implements Arrayable<string, mixed> */
abstract class Filter implements Arrayable
{
    final public function __construct(
        public readonly string $attribute,
        public readonly string $label,
    ) {}

    public static function make(string $attribute, ?string $label = null): static
    {
        return new static($attribute, $label ?? str($attribute)->headline()->toString());
    }

    abstract public function allowedFilter(): AllowedFilter;

    public function normalize(mixed $value): mixed
    {
        return $value;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'attribute' => $this->attribute,
            'label' => $this->label,
        ];
    }
}
