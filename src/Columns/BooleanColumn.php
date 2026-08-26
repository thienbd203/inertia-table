<?php

namespace Musing\InertiaTable\Columns;

class BooleanColumn extends Column
{
    protected static ?string $defaultTrueLabel = null;

    protected static ?string $defaultFalseLabel = null;

    protected static ?string $defaultTrueIcon = null;

    protected static ?string $defaultFalseIcon = null;

    protected ?string $trueLabel = null;

    protected ?string $falseLabel = null;

    protected ?string $trueIcon = null;

    protected ?string $falseIcon = null;

    public static function setDefaultTrueLabel(string $label): void
    {
        static::$defaultTrueLabel = $label;
    }

    public static function setDefaultFalseLabel(string $label): void
    {
        static::$defaultFalseLabel = $label;
    }

    public static function setDefaultTrueIcon(?string $icon): void
    {
        static::$defaultTrueIcon = $icon;
    }

    public static function setDefaultFalseIcon(?string $icon): void
    {
        static::$defaultFalseIcon = $icon;
    }

    public function trueLabel(string $label): static
    {
        $this->trueLabel = $label;

        return $this;
    }

    public function falseLabel(string $label): static
    {
        $this->falseLabel = $label;

        return $this;
    }

    public function trueIcon(?string $icon): static
    {
        $this->trueIcon = $icon;

        return $this;
    }

    public function falseIcon(?string $icon): static
    {
        $this->falseIcon = $icon;

        return $this;
    }

    public function toArray(): array
    {
        return [
            ...parent::toArray(),
            'type' => 'boolean',
            'trueLabel' => $this->trueLabel
                ?? static::$defaultTrueLabel
                ?? (string) trans('inertia-table::messages.boolean.yes'),
            'falseLabel' => $this->falseLabel
                ?? static::$defaultFalseLabel
                ?? (string) trans('inertia-table::messages.boolean.no'),
            'trueIcon' => $this->trueIcon ?? static::$defaultTrueIcon,
            'falseIcon' => $this->falseIcon ?? static::$defaultFalseIcon,
        ];
    }
}
