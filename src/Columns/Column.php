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

    public function isSearchable(): bool
    {
        return $this->searchable;
    }

    public function isSortable(): bool
    {
        return $this->sortable;
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
            'label' => $this->label,
            'type' => 'text',
            'searchable' => $this->searchable,
            'sortable' => $this->sortable,
            'toggleable' => $this->toggleable,
        ];
    }
}
