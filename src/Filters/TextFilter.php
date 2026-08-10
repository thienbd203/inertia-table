<?php

namespace Toolbelt\InertiaTable\Filters;

use Spatie\QueryBuilder\AllowedFilter;

class TextFilter extends Filter
{
    public function allowedFilter(): AllowedFilter
    {
        return AllowedFilter::partial($this->attribute);
    }

    public function normalize(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    public function toArray(): array
    {
        return [...parent::toArray(), 'type' => 'text'];
    }
}
