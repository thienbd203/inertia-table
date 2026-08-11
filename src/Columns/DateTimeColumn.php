<?php

namespace Toolbelt\InertiaTable\Columns;

class DateTimeColumn extends DateColumn
{
    protected static string $defaultFormat = 'Y-m-d H:i:s';

    protected static bool $defaultTranslate = false;

    public function toArray(): array
    {
        return [...parent::toArray(), 'type' => 'datetime'];
    }
}
