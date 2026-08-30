<?php

namespace Musing\InertiaTable\Support;

use LogicException;

final class DataAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, string>  $reserved
     * @return array<string, string|int|float|bool|null>
     */
    public static function normalize(array $attributes, array $reserved = []): array
    {
        $normalized = [];

        foreach ($attributes as $key => $value) {
            $name = strtolower(str_starts_with($key, 'data-') ? substr($key, 5) : $key);

            if (
                ! preg_match('/^[a-z][a-z0-9_.:-]*$/', $name)
                || in_array($name, $reserved, true)
            ) {
                throw new LogicException("Invalid table data attribute [{$key}].");
            }

            if (! is_scalar($value) && $value !== null) {
                throw new LogicException("Table data attribute [{$key}] must be scalar or null.");
            }

            $normalized["data-{$name}"] = $value;
        }

        return $normalized;
    }
}
