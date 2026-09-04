<?php

namespace Musing\InertiaTable\Columns;

use Closure;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use LogicException;
use Musing\InertiaTable\Contracts\RelationshipSorter;
use Musing\InertiaTable\Image;
use Musing\InertiaTable\SortDirection;
use Musing\InertiaTable\Sorters\PowerJoinsRelationshipSorter;
use Musing\InertiaTable\Summaries\Summary;
use Musing\InertiaTable\Summaries\SummaryAggregate;
use Musing\InertiaTable\Support\RelationshipPath;
use Musing\InertiaTable\Table;
use Musing\InertiaTable\Url;
use Spatie\QueryBuilder\AllowedSort;

/** @implements Arrayable<string, mixed> */
class Column implements Arrayable
{
    private const MAX_PIXEL_WIDTH = 10000;

    protected bool $searchable = false;

    protected bool $sortable = false;

    protected bool $toggleable = true;

    protected bool $visibleByDefault = true;

    protected bool $stickable = false;

    protected bool $sticky = false;

    protected ?int $width = null;

    protected ?int $minWidth = null;

    protected ?int $maxWidth = null;

    protected bool $resizable = false;

    protected bool $reorderable = false;

    protected ColumnAlignment $alignment = ColumnAlignment::Left;

    protected bool $wrap = false;

    protected ?int $truncate = null;

    protected ?string $tooltip = null;

    protected ?string $headerClass = null;

    protected ?string $cellClass = null;

    /** @var Closure|array<string|int, mixed>|null */
    protected Closure|array|null $valueMapper = null;

    /** @var Closure(Builder, SortDirection): void|null */
    protected ?Closure $sortResolver = null;

    /** @var array<string|int, mixed>|null */
    protected ?array $sortMap = null;

    /** @var array<int, string|int|float|bool> */
    protected array $sortPriority = [];

    /** @var array<string, mixed> */
    protected array $meta = [];

    protected ?Closure $urlResolver = null;

    protected string|Closure|null $imageResolver = null;

    protected ?Closure $imageConfigurator = null;

    protected bool $exportable = true;

    protected ?Closure $exportMapper = null;

    protected string|Closure|null $exportFormatter = null;

    /** @var array<string, mixed> */
    protected array $exportMeta = [];

    protected ?Summary $summary = null;

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
        bool $stickable = false,
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
            ->cellClass($cellClass)
            ->stickable($stickable);

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

    public function stickable(bool $stickable = true): static
    {
        $this->stickable = $stickable;

        if (! $stickable) {
            $this->sticky = false;
        }

        return $this;
    }

    public function sticky(bool $sticky = true): static
    {
        $this->sticky = $sticky;

        if ($sticky) {
            $this->stickable = true;
        }

        return $this;
    }

    public function width(int $width): static
    {
        $this->width = $this->positiveWidth($width);
        $this->normalizeDeclaredWidth();

        return $this;
    }

    public function minWidth(int $width): static
    {
        $width = $this->positiveWidth($width);

        if ($this->maxWidth !== null && $width > $this->maxWidth) {
            throw new LogicException('A column minimum width cannot exceed its maximum width.');
        }

        $this->minWidth = $width;
        $this->normalizeDeclaredWidth();

        return $this;
    }

    public function maxWidth(int $width): static
    {
        $width = $this->positiveWidth($width);

        if ($this->minWidth !== null && $width < $this->minWidth) {
            throw new LogicException('A column maximum width cannot be smaller than its minimum width.');
        }

        $this->maxWidth = $width;
        $this->normalizeDeclaredWidth();

        return $this;
    }

    public function resizable(bool $resizable = true): static
    {
        $this->resizable = $resizable;

        return $this;
    }

    public function reorderable(bool $reorderable = true): static
    {
        $this->reorderable = $reorderable;

        return $this;
    }

    public function summary(
        string|SummaryAggregate $aggregate = SummaryAggregate::Sum,
        ?string $attribute = null,
    ): static {
        $this->summary = Summary::aggregate($aggregate, $this->attribute, $attribute);

        return $this;
    }

    /** @param Closure(Builder<Model>, self, Table): mixed $resolver */
    public function summaryUsing(Closure $resolver): static
    {
        $this->summary = Summary::custom($resolver);

        return $this;
    }

    public function summaryFormat(?string $format): static
    {
        if (! $this->summary instanceof Summary) {
            throw new LogicException('summaryFormat() requires a summary definition.');
        }

        $this->summary->format($format);

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

    /** @param Closure(Builder, SortDirection): void $resolver */
    public function sortUsing(Closure $resolver): static
    {
        $this->sortResolver = $resolver;

        return $this;
    }

    /** @param array<string|int, mixed>|null $map */
    public function sortUsingMap(?array $map = null): static
    {
        $map ??= is_array($this->valueMapper) ? $this->valueMapper : null;

        if ($map === null) {
            throw new LogicException('sortUsingMap() requires an array passed to mapAs(), or an explicit map.');
        }

        $this->sortMap = $map;

        return $this;
    }

    /** @param array<int, string|int|float|bool> $values */
    public function sortUsingPriority(array $values): static
    {
        $this->sortPriority = array_values(array_unique($values, SORT_REGULAR));

        if ($this->sortPriority === []) {
            throw new LogicException('sortUsingPriority() requires at least one value.');
        }

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

    /** @param Closure(mixed, Model): mixed $mapper */
    public function exportAs(Closure $mapper): static
    {
        $this->exportMapper = $mapper;
        $this->exportable = true;

        return $this;
    }

    public function exportable(bool $exportable = true): static
    {
        $this->exportable = $exportable;

        return $this;
    }

    public function dontExport(): static
    {
        return $this->exportable(false);
    }

    /** @param string|Closure(self): string|null $format */
    public function exportFormat(string|Closure|null $format): static
    {
        $this->exportFormatter = $format;

        return $this;
    }

    /** @param array<string, mixed> $meta */
    public function exportMeta(array $meta): static
    {
        $this->exportMeta = $meta;

        return $this;
    }

    /** @return array<string, bool|string>|null */
    public function resolveUrl(Model $model): ?array
    {
        if ($this->urlResolver === null) {
            return null;
        }

        $url = ($this->urlResolver)($model, Url::make());

        if (is_string($url)) {
            $url = Url::make()->to($url);
        }

        return $url instanceof Url && $url->hasUrl() && ! $url->isHidden()
            ? $url->toArray()
            : null;
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

    public function resolveExportValue(Model $model): mixed
    {
        $value = $this->resolveValue($model);

        return $this->exportMapper instanceof Closure
            ? ($this->exportMapper)($value, $model)
            : $value;
    }

    public function isExportable(): bool
    {
        return $this->exportable;
    }

    public function resolvedExportFormat(): ?string
    {
        $format = $this->exportFormatter instanceof Closure
            ? ($this->exportFormatter)($this)
            : $this->exportFormatter;

        return is_string($format) && $format !== '' ? $format : null;
    }

    /** @return array<string, mixed> */
    public function exportMetadata(): array
    {
        return $this->exportMeta;
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

    public function isStickable(): bool
    {
        return $this->stickable;
    }

    public function isSticky(): bool
    {
        return $this->sticky;
    }

    public function isResizable(): bool
    {
        return $this->resizable;
    }

    public function isReorderable(): bool
    {
        return $this->reorderable;
    }

    public function defaultWidth(): ?int
    {
        return $this->width;
    }

    public function minimumWidth(): ?int
    {
        return $this->minWidth;
    }

    public function maximumWidth(): ?int
    {
        return $this->maxWidth;
    }

    public function summaryDefinition(): ?Summary
    {
        return $this->summary;
    }

    public function clampWidth(int $width): int
    {
        $width = $this->positiveWidth($width);

        if ($this->minWidth !== null) {
            $width = max($width, $this->minWidth);
        }

        if ($this->maxWidth !== null) {
            $width = min($width, $this->maxWidth);
        }

        return min($width, self::MAX_PIXEL_WIDTH);
    }

    public function applySearch(Builder $query, string $search, string $boolean = 'or'): void
    {
        RelationshipPath::where(
            $query,
            $this->attribute,
            function (Builder $target, string $attribute) use ($search): void {
                $target->where($attribute, 'like', "%{$search}%");
            },
            $boolean,
        );
    }

    public function applySort(Builder $query, string $direction): void
    {
        $sortDirection = SortDirection::from($direction);

        if ($this->sortResolver !== null) {
            ($this->sortResolver)($query, $sortDirection);

            return;
        }

        if ($this->sortMap !== null) {
            $this->applyMappedSort($query, $sortDirection);

            return;
        }

        if ($this->sortPriority !== []) {
            $this->applyPrioritySort($query, $sortDirection);

            return;
        }

        if (RelationshipPath::split($this->attribute) !== null) {
            $sorter = app(config(
                'inertia-table.relationship_sorter',
                PowerJoinsRelationshipSorter::class,
            ));

            if (! $sorter instanceof RelationshipSorter) {
                throw new LogicException('The configured relationship sorter must implement '.RelationshipSorter::class.'.');
            }

            $sorter->sort($query, $this->attribute, $sortDirection);

            return;
        }

        $query->orderBy($query->qualifyColumn($this->attribute), $sortDirection->value);
    }

    public function allowedSort(): AllowedSort
    {
        return AllowedSort::callback(
            $this->attribute,
            fn (Builder $query, bool $descending) => $this->applySort(
                $query,
                $descending ? SortDirection::Descending->value : SortDirection::Ascending->value,
            ),
        );
    }

    private function applyMappedSort(Builder $query, SortDirection $direction): void
    {
        if (RelationshipPath::split($this->attribute) !== null) {
            throw new LogicException('Mapped relationship sorts require an explicit sortUsing() resolver.');
        }

        $grammar = $query->getQuery()->getGrammar();
        $attribute = $grammar->wrap($query->qualifyColumn($this->attribute));
        $cases = [];
        $bindings = [];

        foreach ($this->sortMap as $value => $mappedValue) {
            $cases[] = 'when ? then ?';
            $bindings[] = $value;
            $bindings[] = $mappedValue;
        }

        $query->orderByRaw(
            "case {$attribute} ".implode(' ', $cases)." else ? end {$direction->value}",
            [...$bindings, $this->attribute],
        );
    }

    private function applyPrioritySort(Builder $query, SortDirection $direction): void
    {
        if (RelationshipPath::split($this->attribute) !== null) {
            throw new LogicException('Priority relationship sorts require an explicit sortUsing() resolver.');
        }

        $grammar = $query->getQuery()->getGrammar();
        $attribute = $grammar->wrap($query->qualifyColumn($this->attribute));
        $cases = [];
        $bindings = [];

        foreach ($this->sortPriority as $priority => $value) {
            $cases[] = 'when ? then ?';
            $bindings[] = $value;
            $bindings[] = $priority;
        }

        $fallback = $direction === SortDirection::Ascending
            ? count($this->sortPriority)
            : -1;
        $query->orderByRaw(
            "case {$attribute} ".implode(' ', $cases)." else ? end {$direction->value}",
            [...$bindings, $fallback],
        );
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
            'stickable' => $this->stickable,
            'sticky' => $this->sticky,
            'width' => $this->width,
            'minWidth' => $this->minWidth,
            'maxWidth' => $this->maxWidth,
            'resizable' => $this->resizable,
            'reorderable' => $this->reorderable,
            'summary' => $this->summary?->toArray(),
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

    private function positiveWidth(int $width): int
    {
        if ($width < 1) {
            throw new LogicException('Column widths must be positive pixel values.');
        }

        return min($width, self::MAX_PIXEL_WIDTH);
    }

    private function normalizeDeclaredWidth(): void
    {
        if ($this->width !== null) {
            $this->width = $this->clampWidth($this->width);
        }
    }
}
