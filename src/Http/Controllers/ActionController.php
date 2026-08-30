<?php

namespace Musing\InertiaTable\Http\Controllers;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Musing\InertiaTable\Actions\Action;
use Musing\InertiaTable\Selection;
use Musing\InertiaTable\Support\TableReference;
use Musing\InertiaTable\Table;
use Symfony\Component\HttpFoundation\Response;

final class ActionController
{
    public function __invoke(Request $request, string $table, string $action): Response|Responsable
    {
        $tableClass = TableReference::decode($table);
        abort_if($tableClass === null, 404);

        $tableInstance = app($tableClass);
        abort_unless($tableInstance instanceof Table, 404);

        $tableAction = $tableInstance->action($action);
        abort_unless($tableAction instanceof Action && $tableAction->hasHandler(), 404);

        $isRowAction = $request->exists('id');
        $selection = $this->resolveSelection($request, $tableInstance, $isRowAction);

        if ($isRowAction) {
            abort_unless($tableAction->isRowAction(), 404);
            $model = $selection->query()->first();
            abort_unless($model instanceof Model, 404);
            $this->ensureAvailable($tableAction, $model);
        } else {
            abort_unless($tableAction->isBulkAction(), 404);
            $this->ensureAvailable($tableAction);
        }

        $response = $tableAction->execute(
            $selection,
            skipUnavailableModels: ! $isRowAction,
        );

        if ($response instanceof Response || $response instanceof Responsable) {
            return $response;
        }

        return back();
    }

    private function resolveSelection(Request $request, Table $table, bool $isRowAction): Selection
    {
        if ($isRowAction) {
            return Selection::forRow($table, $request->input('id'));
        }

        $selection = $request->input('selection');

        if (is_array($selection)) {
            return $table->selection($selection);
        }

        return $table->selection([
            'all' => false,
            'keys' => $request->input('ids', []),
            'except' => [],
            'table' => $table->name(),
            'state' => [],
        ]);
    }

    private function ensureAvailable(Action $action, ?Model $model = null): void
    {
        $resolved = $action->resolve($model);

        abort_if(! $resolved['authorized'] || $resolved['hidden'], 403);

        if ($resolved['disabled']) {
            throw ValidationException::withMessages([
                'action' => $resolved['disabledTooltip'] ?? 'This action is disabled.',
            ]);
        }
    }
}
