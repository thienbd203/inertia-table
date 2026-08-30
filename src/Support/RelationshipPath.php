<?php

namespace Musing\InertiaTable\Support;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use LogicException;

final class RelationshipPath
{
    /**
     * Apply a constraint to a qualified base column or an allowlisted relationship path.
     *
     * @param  Closure(Builder, string): void  $constraint
     */
    public static function where(
        Builder $query,
        string $path,
        Closure $constraint,
        string $boolean = 'and',
    ): void {
        $relationship = self::split($path);

        if ($relationship === null) {
            $constraint($query, $query->qualifyColumn($path));

            return;
        }

        [$relation, $attribute] = $relationship;
        $callback = fn (Builder $related) => $constraint(
            $related,
            $related->qualifyColumn($attribute),
        );

        if ($boolean === 'or') {
            $query->orWhereHas($relation, $callback);
        } else {
            $query->whereHas($relation, $callback);
        }
    }

    public static function whereMissing(Builder $query, string $path): void
    {
        $relationship = self::split($path);

        if ($relationship === null) {
            $query->whereNull($query->qualifyColumn($path));

            return;
        }

        [$relation, $attribute] = $relationship;
        $query->whereDoesntHave(
            $relation,
            fn (Builder $related) => $related->whereNotNull(
                $related->qualifyColumn($attribute),
            ),
        );
    }

    /** @return array{string, string}|null */
    public static function split(string $path): ?array
    {
        if (! str_contains($path, '.')) {
            return null;
        }

        $segments = explode('.', $path);

        if (count($segments) < 2 || collect($segments)->contains(
            fn (string $segment) => ! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $segment),
        )) {
            throw new LogicException("Invalid relationship attribute path [{$path}].");
        }

        $attribute = array_pop($segments);

        return [implode('.', $segments), $attribute];
    }
}
