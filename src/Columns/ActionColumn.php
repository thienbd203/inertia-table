<?php

namespace Toolbelt\InertiaTable\Columns;

final class ActionColumn extends Column
{
    public static function new(string $header = 'Actions'): static
    {
        return self::make('__actions', $header)
            ->toggleable(false)
            ->rightAligned();
    }

    public function toArray(): array
    {
        return [...parent::toArray(), 'type' => 'action'];
    }
}
