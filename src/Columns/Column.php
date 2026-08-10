<?php

namespace Toolbelt\InertiaTable\Columns;

use Closure;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\QueryBuilder\AllowedSort;
use Toolbelt\InertiaTable\Image;

/** @implements Arrayable<string, mixed> */
class Column implements Arrayable
{
    protected bool $searchable = false;

    protected bool $sortable = false;

    protected bool $toggleable = true;

    protected bool $visibleByDefault = true;

    protected ColumnAlignment $alignment = ColumnAlignment::Left;

    protected bool $wrap = false;

    protected ?int $truncate = null;

    protected ?string $tooltip = null;

    protected ?string $headerClass = null;

    protected ?string $cellClass = null;

    /** @var Closure|array<string|int, mixed>|null */
    protected Closure|array|null $valueMapper = null;

    /** @var array<string, mixed> */
    protected array $meta = [];

    protected ?Closure $urlResolver = null;

    protected string|Closure|null $imageResolver = null;

    protected ?Closure $imageConfigurator = null;

    final public function __construct(
        public readonly string $attribute,
        public string $label,
    ) {}

    public static function make(
        string $attribute,
        ?string $label = null,
        bool $sortable = false,
        bool $toggleable = true,
        bool $searchable = false,
        bool $visible = true,
        ColumnAlignment $alignment = ColumnAlignment::Left,
        bool $wrap = false,
        ?int $truncate = null,
        Closure|array|null $mapAs = null,
        ?string $tooltip = null,
        string|array|null $headerClass = null,
        string|array|null $cellClass = null,
        ?Closure $url = null,
    ): static {
        $column = new static($attribute, $label ?? str($attribute)->headline()->toString());
        $column->sortable($sortable)
            ->toggleable($toggleable)
            ->searchable($searchable)
            ->visible($visible)
            ->align($alignment)
            ->wrap($wrap)
            ->tooltip($tooltip)
            ->headerClass($headerClass)
            ->cellClass($cellClass);

        if ($truncate !== null) {
            $column->truncate($truncate);
        }
        if ($mapAs !== null) {
            $column->mapAs($mapAs);
        }
        if ($url !== null) {
            $column->url($url);
        }

        return $column;
    }

    public function searchable(bool $searchable = true): static
    {
        $this->searchable = $searchable;

        return $this;
    }

    public function notSearchable(): static
    {
        return $this->searchable(false);
    }

    public function sortable(bool $sortable = true): static
    {
        $this->sortable = $sortable;

        return $this;
    }

    public function notSortable(): static
    {
        return $this->sortable(false);
    }

    public function toggleable(bool $toggleable = true): static
    {
        $this->toggleable = $toggleable;

        return $this;
    }

    public function notToggleable(): static
    {
        return $this->toggleable(false);
    }

    public function header(string $header): static
    {
        $this->label = $header;

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

    public function wrap(bool $wrap = true): static
    {
        $this->wrap = $wrap;

        return $this;
    }

    public function truncate(?int $lines = 1): static
    {
        $this->truncate = $lines !== null && $lines > 0 ? $lines : null;
        if ($this->truncate !== null) {
            $this->wrap = true;
        }

        return $this;
    }

    public function tooltip(?string $tooltip): static
    {
        $this->tooltip = $tooltip;

        return $this;
    }

    public function headerClass(string|array|null $class): static
    {
        $this->headerClass = $this->normalizeClasses($class);

        return $this;
    }

    public function cellClass(string|array|null $class): static
    {
        $this->cellClass = $this->normalizeClasses($class);

        return $this;
    }

    /** @param Closure|array<string|int, mixed> $mapper */
    public function mapAs(Closure|array $mapper): static
    {
        $this->valueMapper = $mapper;

        return $this;
    }

    /** @param array<string, mixed> $meta */
    public function meta(array $meta): static
    {
        $this->meta = $meta;

        return $this;
    }

    public function url(Closure $resolver): static
    {
        $this->urlResolver = $resolver;

        return $this;
    }

    public function image(string|Closure $resolver, ?Closure $configure = null): static
    {
        $this->imageResolver = $resolver;
        $this->imageConfigurator = $configure;

        return $this;
    }

    public function resolveUrl(Model $model): ?string
    {
        if ($this->urlResolver === null) {
            return null;
        }

        $url = ($this->urlResolver)($model);

        return is_string($url) && $url !== '' ? $url : null;
    }

    public function resolveValue(Model $model): mixed
    {
        $value = data_get($model, $this->attribute);

        if ($this->valueMapper instanceof Closure) {
            return ($this->valueMapper)($value, $model);
        }

        if (is_array($this->valueMapper) && array_key_exists((string) $value, $this->valueMapper)) {
            return $this->valueMapper[(string) $value];
        }

        return $value;
    }

    /** @return array<string, mixed> */
    public function resolveCellMeta(Model $model): array
    {
        $image = $this->resolveImage($model);

        return $image === null ? [] : ['image' => $image->toArray()];
    }

    protected function resolveImage(Model $model): ?Image
    {
        if ($this->imageResolver === null) {
            return null;
        }

        $image = new Image;
        if (is_string($this->imageResolver)) {
            $image->url(data_get($model, $this->imageResolver));
            $configured = $this->imageConfigurator ? ($this->imageConfigurator)($image, $model) : $image;
        } else {
            $configured = ($this->imageResolver)($model, $image);
        }

        return $configured instanceof Image ? $configured : $image;
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
            'wrap' => $this->wrap,
            'truncate' => $this->truncate,
            'tooltip' => $this->tooltip,
            'headerClass' => $this->headerClass,
            'cellClass' => $this->cellClass,
            'meta' => $this->meta,
        ];
    }

    protected function normalizeClasses(string|array|null $class): ?string
    {
        if (is_array($class)) {
            $class = implode(' ', array_filter($class, fn (mixed $value) => is_string($value) && $value !== ''));
        }

        return is_string($class) && trim($class) !== '' ? trim($class) : null;
    }
}
