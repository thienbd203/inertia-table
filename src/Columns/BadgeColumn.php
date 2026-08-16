<?php

namespace Musing\InertiaTable\Columns;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Musing\InertiaTable\Variant;

class BadgeColumn extends Column
{
    /** @var Closure|array<string|int, string|Variant>|string|Variant */
    protected Closure|array|string|Variant $variant = 'default';

    /** @var Closure|array<string|int, string|null>|string|null */
    protected Closure|array|string|null $icon = null;

    /** @var Closure|array<string|int, string|null>|string|null */
    protected Closure|array|string|null $badgeClass = null;

    public function variant(Closure|array|string|Variant $variant): static
    {
        $this->variant = $variant;

        return $this;
    }

    public function icon(Closure|array|string|null $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    /**
     * Apply CSS classes to the badge itself.
     *
     * Use cellClass() to style the table cell instead.
     *
     * @param  Closure|array<string|int, string|null>|string|null  $class
     */
    public function badgeClass(Closure|array|string|null $class): static
    {
        $this->badgeClass = $class;

        return $this;
    }

    public function resolveCellMeta(Model $model): array
    {
        $raw = data_get($model, $this->attribute);

        $variant = $this->resolveMappedProperty($this->variant, $raw, $model);

        return [
            'variant' => $variant instanceof Variant ? $variant->value : $variant,
            'icon' => $this->resolveMappedProperty($this->icon, $raw, $model),
            'badgeClass' => $this->resolveMappedProperty($this->badgeClass, $raw, $model),
        ];
    }

    public function toArray(): array
    {
        return [...parent::toArray(), 'type' => 'badge'];
    }

    private function resolveMappedProperty(Closure|array|string|null $property, mixed $value, Model $model): mixed
    {
        if ($property instanceof Closure) {
            return $property($value, $model);
        }

        if (is_array($property)) {
            return $property[(string) $value] ?? null;
        }

        return $property;
    }
}
