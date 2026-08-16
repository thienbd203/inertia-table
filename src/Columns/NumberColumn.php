<?php

namespace Musing\InertiaTable\Columns;

class NumberColumn extends Column
{
    public function toArray(): array
    {
        return [...parent::toArray(), 'type' => 'numeric'];
    }
}
