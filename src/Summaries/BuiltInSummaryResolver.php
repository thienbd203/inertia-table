<?php

namespace Musing\InertiaTable\Summaries;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use LogicException;
use Musing\InertiaTable\Columns\Column;

/** @internal Executes built-in aggregates against an already scoped query. */
final class BuiltInSummaryResolver
{
    /**
     * @param  Builder<Model>  $query
     * @param  array<int, Column>  $columns
     * @return array<string, mixed>
     */
    public function resolve(Builder $query, array $columns): array
    {
        $builtIns = array_values(array_filter(
            $columns,
            fn (Column $column) => $column->summaryDefinition()?->aggregateType() !== SummaryAggregate::Custom,
        ));

        if ($builtIns === []) {
            return [];
        }

        $base = clone $query;
        $base->reorder();
        $baseQuery = $base->toBase();
        $grammar = $baseQuery->getGrammar();
        $summaryQuery = $baseQuery->newQuery()->fromSub($baseQuery, 'inertia_table_summary');
        $aliases = [];

        foreach ($builtIns as $index => $column) {
            $summary = $column->summaryDefinition();
            $aggregate = $summary?->aggregateType();
            $attribute = $summary?->attribute();
            $wrappedAttribute = $attribute === null ? null : $grammar->wrap($attribute);
            $alias = "inertia_table_summary_{$index}";
            $expression = $aggregate?->expression($wrappedAttribute)
                ?? throw new LogicException('Unsupported built-in table summary.');
            $summaryQuery->selectRaw("{$expression} AS {$grammar->wrap($alias)}");
            $aliases[$column->attribute] = [$alias, $aggregate];
        }

        $row = (array) $summaryQuery->first();
        $values = [];

        foreach ($aliases as $attribute => [$alias, $aggregate]) {
            $value = $row[$alias] ?? null;
            $values[$attribute] = in_array($aggregate, [SummaryAggregate::Count, SummaryAggregate::CountDistinct], true)
                ? (int) $value
                : $value;
        }

        return $values;
    }
}
