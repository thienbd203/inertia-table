<?php

namespace Musing\InertiaTable\Sorters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use LogicException;
use Musing\InertiaTable\Contracts\RelationshipSorter;
use Musing\InertiaTable\SortDirection;
use Musing\InertiaTable\Support\RelationshipPath;

final class PowerJoinsRelationshipSorter implements RelationshipSorter
{
    public function sort(Builder $query, string $path, SortDirection $direction): void
    {
        [$relationship, $attribute] = RelationshipPath::split($path)
            ?? throw new LogicException("Relationship sort path [{$path}] is invalid.");

        if ($this->containsToManyRelation($query->getModel(), $relationship)) {
            $aggregate = $direction === SortDirection::Ascending ? 'min' : 'max';
            $alias = '__inertia_table_relationship_sort';
            $query->withAggregate(
                ["{$relationship} as {$alias}"],
                $attribute,
                $aggregate,
            )->orderBy($alias, $direction->value);

            return;
        }

        if (
            ! class_exists('Kirschbaum\\PowerJoins\\PowerJoinsServiceProvider')
            || ! Builder::hasGlobalMacro('orderByLeftPowerJoins')
        ) {
            throw new LogicException(
                'Install kirschbaum-development/eloquent-power-joins before sorting relationship columns, or use sortUsing().',
            );
        }

        call_user_func([$query, 'orderByLeftPowerJoins'], $path, $direction->value);
    }

    private function containsToManyRelation(Model $model, string $path): bool
    {
        $containsToMany = false;

        foreach (explode('.', $path) as $segment) {
            $modelClass = $model::class;

            if (! method_exists($model, $segment)) {
                throw new LogicException("Relationship [{$segment}] does not exist on [{$modelClass}].");
            }

            $relation = $model->{$segment}();

            if (! $relation instanceof Relation) {
                throw new LogicException("Method [{$modelClass}::{$segment}] is not an Eloquent relationship.");
            }

            $containsToMany = $containsToMany || $relation instanceof HasMany
                || $relation instanceof HasManyThrough
                || $relation instanceof BelongsToMany
                || $relation instanceof MorphMany;
            $model = $relation->getRelated();
        }

        return $containsToMany;
    }
}
