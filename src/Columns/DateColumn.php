<?php

namespace Toolbelt\InertiaTable\Columns;

class DateColumn extends Column
{
    public function toArray(): array
    {
        return [...parent::toArray(), 'type' => 'date'];
    }
}
