<?php

namespace Musing\InertiaTable;

enum SortDirection: string
{
    case Ascending = 'asc';
    case Descending = 'desc';

    public static function fromDescending(bool $descending): self
    {
        return $descending ? self::Descending : self::Ascending;
    }
}
