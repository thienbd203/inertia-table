<?php

namespace Toolbelt\InertiaTable\Columns;

use Illuminate\Database\Eloquent\Model;
use Toolbelt\InertiaTable\Image;

class ImageColumn extends Column
{
    protected function resolveImage(Model $model): ?Image
    {
        return parent::resolveImage($model) ?? (new Image)->url(parent::resolveValue($model));
    }

    public function toArray(): array
    {
        return [...parent::toArray(), 'type' => 'image'];
    }
}
