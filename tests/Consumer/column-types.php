<?php

namespace Musing\InertiaTable\Tests\Consumer;

use Illuminate\Database\Eloquent\Model;
use Musing\InertiaTable\Columns\TextColumn;
use Musing\InertiaTable\Url;

use function PHPStan\Testing\assertType;

// Analyse this file explicitly alongside the package's normal PHPStan config.
$column = TextColumn::make('name')
    ->mapAs(function (mixed $value, Model $model): string {
        return (string) $value;
    })
    ->url(function (Model $model, Url $url): Url {
        return $url->to('/topics/'.$model->getKey());
    })
    ->exportAs(fn (mixed $value, Model $model): string => (string) $value);

assertType(TextColumn::class, $column);

$namedArguments = TextColumn::make(
    'name',
    mapAs: fn (mixed $value, Model $model): string => (string) $value,
    url: fn (Model $model, Url $url): ?string => null,
);

assertType(TextColumn::class, $namedArguments);
