<?php

namespace Musing\InertiaTable\Http\Controllers;

use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Musing\InertiaTable\Support\TableReference;
use Musing\InertiaTable\Table;
use Musing\InertiaTable\TableView;
use Musing\InertiaTable\Views;

final class ViewController
{
    public function store(Request $request, string $table): RedirectResponse
    {
        [$tableInstance, $views] = $this->resolveTable($table);
        $views->ensureAuthorized('create', $request, $tableInstance);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'state' => ['required', 'array'],
        ]);
        $name = trim($validated['name']);

        if ($name === '') {
            throw ValidationException::withMessages(['name' => 'The view name is required.']);
        }

        try {
            $views->newQuery()->create(
                $views->valuesFor($tableInstance, $request, $name, $validated['state']),
            );
        } catch (QueryException $exception) {
            $this->throwDuplicateName($exception);
        }

        return back();
    }

    public function update(Request $request, string $table, string $view): RedirectResponse
    {
        [$tableInstance, $views] = $this->resolveTable($table);
        $model = $this->resolveView($views, $tableInstance, $request, $view);
        $views->ensureAuthorized('update', $request, $tableInstance, $model);
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'state' => ['sometimes', 'required', 'array'],
            'version' => ['required', 'integer', 'min:0'],
        ]);

        if (! array_key_exists('name', $validated) && ! array_key_exists('state', $validated)) {
            throw ValidationException::withMessages([
                'view' => 'A name or state change is required.',
            ]);
        }

        try {
            DB::transaction(function () use ($model, $tableInstance, $validated, $views) {
                $locked = $this->lockCurrentView($views, $model, $validated['version']);

                if (array_key_exists('name', $validated)) {
                    $name = trim($validated['name']);

                    if ($name === '') {
                        throw ValidationException::withMessages(['name' => 'The view name is required.']);
                    }

                    $locked->name = $name;
                }

                if (array_key_exists('state', $validated)) {
                    $locked->state = $views->normalizeState($tableInstance, $validated['state']);
                }

                $locked->lock_version++;
                $locked->save();
            });
        } catch (QueryException $exception) {
            $this->throwDuplicateName($exception);
        }

        return back();
    }

    public function destroy(Request $request, string $table, string $view): RedirectResponse
    {
        [$tableInstance, $views] = $this->resolveTable($table);
        $model = $this->resolveView($views, $tableInstance, $request, $view);
        $views->ensureAuthorized('delete', $request, $tableInstance, $model);
        $validated = $request->validate([
            'version' => ['required', 'integer', 'min:0'],
        ]);
        DB::transaction(function () use ($model, $validated, $views) {
            $this->lockCurrentView($views, $model, $validated['version'])->delete();
        });

        return back();
    }

    public function setDefault(Request $request, string $table, string $view): RedirectResponse
    {
        [$tableInstance, $views] = $this->resolveTable($table);
        $model = $this->resolveView($views, $tableInstance, $request, $view);
        $views->ensureAuthorized('default', $request, $tableInstance, $model);
        $validated = $request->validate([
            'version' => ['required', 'integer', 'min:0'],
        ]);

        DB::transaction(function () use ($model, $validated, $views) {
            $locked = $this->lockCurrentView($views, $model, $validated['version']);
            $views->newQuery()
                ->where('scope_hash', $locked->scope_hash)
                ->lockForUpdate()
                ->get();
            $views->newQuery()
                ->where('scope_hash', $locked->scope_hash)
                ->whereKeyNot($locked->getKey())
                ->update(['is_default' => false]);
            $locked->is_default = true;
            $locked->lock_version++;
            $locked->save();
        });

        return back();
    }

    public function share(Request $request, string $table, string $view): RedirectResponse
    {
        [$tableInstance, $views] = $this->resolveTable($table);
        abort_unless($views->usesUserScope(), 404);
        $model = $this->resolveView($views, $tableInstance, $request, $view);
        $views->ensureAuthorized('share', $request, $tableInstance, $model);
        $validated = $request->validate([
            'shared' => ['required', 'boolean'],
            'version' => ['required', 'integer', 'min:0'],
        ]);
        DB::transaction(function () use ($model, $validated, $views) {
            $locked = $this->lockCurrentView($views, $model, $validated['version']);
            $locked->is_shared = $validated['shared'];
            $locked->lock_version++;
            $locked->save();
        });

        return back();
    }

    /** @return array{Table, Views} */
    private function resolveTable(string $reference): array
    {
        $tableClass = TableReference::decode($reference);
        abort_if($tableClass === null, 404);

        $table = app($tableClass);
        abort_unless($table instanceof Table, 404);
        $views = $table->views();
        abort_unless($views instanceof Views, 404);

        return [$table, $views];
    }

    private function resolveView(
        Views $views,
        Table $table,
        Request $request,
        int|string $id,
    ): TableView {
        $view = $views->findVisible($table, $request, $id);
        abort_unless($view instanceof TableView, 404);

        return $view;
    }

    private function lockCurrentView(Views $views, TableView $view, int $version): TableView
    {
        $locked = $views->newQuery()
            ->whereKey($view->getKey())
            ->lockForUpdate()
            ->first();

        if (! $locked instanceof TableView || $locked->lock_version !== $version) {
            throw ValidationException::withMessages([
                'view' => 'This view changed in another request. Reload it and try again.',
            ]);
        }

        return $locked;
    }

    private function throwDuplicateName(QueryException $exception): never
    {
        if (in_array($exception->getCode(), ['19', '23000', '23505'], true)) {
            throw ValidationException::withMessages([
                'name' => 'A view with this name already exists.',
            ]);
        }

        throw $exception;
    }
}
