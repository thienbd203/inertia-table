<?php

namespace Toolbelt\InertiaTable\Columns;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

class DateColumn extends Column
{
    protected string $format = 'Y-m-d';

    public function format(string $format): static
    {
        $this->format = $format;

        return $this;
    }

    public function resolveValue(Model $model): mixed
    {
        $value = parent::resolveValue($model);

        return $value instanceof DateTimeInterface ? $value->format($this->format) : $value;
    }

    public function toArray(): array
    {
        return [...parent::toArray(), 'type' => 'date', 'format' => $this->format];
    }
}
