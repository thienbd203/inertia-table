<?php

namespace Musing\InertiaTable\Tests\Consumer;

use Illuminate\Database\Eloquent\Model;
use Musing\InertiaTable\Columns\TextColumn;
use Musing\InertiaTable\Table;
use Spatie\QueryBuilder\QueryBuilder;

// Deliberately invalid: analysed by verify-invalid-callback-types.php only.
class InvalidCallbackRecord extends Model {}

TextColumn::make('name')->url(fn (Model $model): int => 42);
Table::build(InvalidCallbackRecord::class, transformModelUsing: fn (Model $model): string => 'not a row');
Table::build(InvalidCallbackRecord::class, withQueryBuilder: fn (QueryBuilder $query): string => 'not a builder');
