<?php

namespace Toolbelt\InertiaTable\Columns;

class BooleanColumn extends Column
{
    public function toArray(): array
    {
        return [...parent::toArray(), 'type' => 'boolean'];
    }
}
