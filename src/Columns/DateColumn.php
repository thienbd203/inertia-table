<?php

namespace Toolbelt\InertiaTable\Columns;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class DateColumn extends Column
{
    protected static string $defaultFormat = 'Y-m-d';

    protected static bool $defaultTranslate = false;

    protected ?string $format = null;

    protected ?bool $translated = null;

    public static function setDefaultFormat(string $format): void
    {
        static::$defaultFormat = $format;
    }

    public static function setDefaultTranslate(bool $translate): void
    {
        static::$defaultTranslate = $translate;
    }

    public function format(string $format): static
    {
        $this->format = $format;

        return $this;
    }

    public function translate(bool $translate = true): static
    {
        $this->translated = $translate;

        return $this;
    }

    public function resolveValue(Model $model): mixed
    {
        $value = parent::resolveValue($model);

        if (! $value instanceof DateTimeInterface) {
            return $value;
        }

        $format = $this->format ?? static::$defaultFormat;

        return ($this->translated ?? static::$defaultTranslate)
            ? Carbon::instance($value)->translatedFormat($format)
            : $value->format($format);
    }

    public function toArray(): array
    {
        return [
            ...parent::toArray(),
            'type' => 'date',
            'format' => $this->format ?? static::$defaultFormat,
            'translated' => $this->translated ?? static::$defaultTranslate,
        ];
    }
}
