<?php

namespace Musing\InertiaTable\Support;

use Musing\InertiaTable\Table;

final class TableReference
{
    /** @param class-string<Table> $table */
    public static function encode(string $table): string
    {
        return rtrim(strtr(base64_encode($table), '+/', '-_'), '=');
    }

    /** @return class-string<Table>|null */
    public static function decode(string $reference): ?string
    {
        $padding = strlen($reference) % 4;
        $encoded = strtr($reference, '-_', '+/');

        if ($padding !== 0) {
            $encoded .= str_repeat('=', 4 - $padding);
        }

        $table = base64_decode($encoded, true);

        if (! is_string($table) || ! class_exists($table) || ! is_subclass_of($table, Table::class)) {
            return null;
        }

        return $table;
    }
}
