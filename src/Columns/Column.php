<?php

namespace Toolbelt\InertiaTable\Columns;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\AllowedSort;

/** @implements Arrayable<string, mixed> */
class Column implements Arrayable
{
    protected bool $searchable = false;

    protected bool $sortable = false;

    protected bool $toggleable = true;

    protected bool $visibleByDefault = true;

    protected ColumnAlignment $alignment = ColumnAlignment::Left;

    /** @var array<string, mixed> */
    protected array $meta = [];

    final public function __construct(
        public readonly string $attribute,
        public readonly string $label,
    ) {}

    public static function make(string $attribute, ?string $label = null): static
    {
        return new static($attribute, $label ?? str($attribute)->headline()->toString());
    }

    public function searchable(bool $searchable = true): static
    {
        $this->searchable = $searchable;

        return $this;
    }

    public function sortable(bool $sortable = true): static
    {
        $this->sortable = $sortable;

        return $this;
    }

    public function toggleable(bool $toggleable = true): static
    {
        $this->toggleable = $toggleable;

        return $this;
    }

    public function visible(bool $visible = true): static
    {
        $this->visibleByDefault = $visible;

        return $this;
    }

    public function align(ColumnAlignment $alignment): static
    {
        $this->alignment = $alignment;

        return $this;
    }

    public function leftAligned(): static
    {
        return $this->align(ColumnAlignment::Left);
    }

    public function centerAligned(): static
    {
        return $this->align(ColumnAlignment::Center);
    }

    public function rightAligned(): static
    {
        return $this->align(ColumnAlignment::Right);
    }

    /** @param array<string, mixed> $meta */
    public function meta(array $meta): static
    {
        $this->meta = $meta;

        return $this;
    }

    public function isSearchable(): bool
    {
        return $this->searchable;
    }

    public function isSortable(): bool
    {
        return $this->sortable;
    }

    public function isToggleable(): bool
    {
        return $this->toggleable;
    }

    public function isVisibleByDefault(): bool
    {
        return $this->visibleByDefault;
    }

    public function applySearch(Builder $query, string $search, string $boolean = 'or'): void
    {
        $query->where($this->attribute, 'like', "%{$search}%", $boolean);
    }

    public function applySort(Builder $query, string $direction): void
    {
        $query->orderBy($this->attribute, $direction);
    }

    public function allowedSort(): AllowedSort
    {
        return AllowedSort::field($this->attribute);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'attribute' => $this->attribute,
            'header' => $this->label,
            'type' => 'text',
            'sortable' => $this->sortable,
            'toggleable' => $this->toggleable,
            'visibleByDefault' => $this->visibleByDefault,
            'alignment' => $this->alignment->value,
            'meta' => $this->meta,
        ];
    }
}
