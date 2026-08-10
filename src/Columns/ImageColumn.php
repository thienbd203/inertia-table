<?php

namespace Toolbelt\InertiaTable\Columns;

class ImageColumn extends Column
{
    public function toArray(): array
    {
        return [...parent::toArray(), 'type' => 'image'];
    }
}
