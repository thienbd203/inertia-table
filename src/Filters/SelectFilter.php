<?php

namespace Musing\InertiaTable\Filters;

/** @deprecated Use SetFilter instead. */
class SelectFilter extends SetFilter
{
    protected function defaultClauses(): array
    {
        return [Clause::Equals];
    }

    public function toArray(): array
    {
        return [...parent::toArray(), 'type' => 'select'];
    }
}
