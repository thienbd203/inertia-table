<?php

namespace Toolbelt\InertiaTable\Columns;

use Closure;
use Illuminate\Database\Eloquent\Model;

class BadgeColumn extends Column
{
    /** @var Closure|array<string|int, string>|string */
    protected Closure|array|string $variant = 'default';

    /** @var Closure|array<string|int, string|null>|string|null */
    protected Closure|array|string|null $icon = null;

    public function variant(Closure|array|string $variant): static
    {
        $this->variant = $variant;

        return $this;
    }

    public function icon(Closure|array|string|null $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    public function resolveCellMeta(Model $model): array
    {
        $raw = data_get($model, $this->attribute);

        return [
            'variant' => $this->resolveMappedProperty($this->variant, $raw, $model),
            'icon' => $this->resolveMappedProperty($this->icon, $raw, $model),
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
