<?php

namespace Musing\InertiaTable\Tests\Consumer;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Musing\InertiaTable\Actions\Action;
use Musing\InertiaTable\Selection;

use function PHPStan\Testing\assertType;

$action = Action::make('update')
    ->authorize(fn (Request $request): bool => $request->user() !== null)
    ->authorized(fn (Model $model): bool => $model->exists)
    ->disabled(fn (Model $model): bool => ! $model->exists)
    ->handle(fn (Model $model, Selection $selection): string => (string) $model->getKey())
    ->before(function (Selection $selection): void {})
    ->after(fn (Selection $selection, mixed $result): mixed => $result);

assertType(Action::class, $action);

$selectionAction = Action::make('batch')->bulk()
    ->handleSelection(fn (Selection $selection): null => null);
assertType(Action::class, $selectionAction);
