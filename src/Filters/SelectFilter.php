<?php

namespace Musing\InertiaTable\Filters;

/** @deprecated Use SetFilter instead. */
class SelectFilter extends SetFilter
{
    protected function defaultClauses(): array
    {
        return [Clause::Equals];
    }

    public function toArray(bool $loadLazyOptions = false): array
    {
        return [...parent::toArray($loadLazyOptions), 'type' => 'select'];
    }
}
