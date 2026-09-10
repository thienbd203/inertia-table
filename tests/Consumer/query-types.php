<?php

namespace Musing\InertiaTable\Tests\Consumer;

use Illuminate\Database\Eloquent\Model;
use Musing\InertiaTable\AnonymousTable;
use Musing\InertiaTable\Table;
use Spatie\QueryBuilder\QueryBuilder;

use function PHPStan\Testing\assertType;

class QueryTypeRecord extends Model {}

$table = Table::build(
    QueryTypeRecord::class,
    transformModelUsing: fn (Model $model): array => ['id' => $model->getKey()],
    withQueryBuilder: function (QueryBuilder $query): void {
        $query->whereNotNull('published_at');
    },
);
assertType(AnonymousTable::class, $table);

$returnedQuery = Table::build(
    QueryTypeRecord::class,
    withQueryBuilder: fn (QueryBuilder $query): QueryBuilder => $query,
);
assertType(AnonymousTable::class, $returnedQuery);
