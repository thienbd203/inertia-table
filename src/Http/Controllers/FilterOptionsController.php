<?php

namespace Musing\InertiaTable\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Musing\InertiaTable\Filters\FilterOptionRequest;
use Musing\InertiaTable\Filters\SetFilter;
use Musing\InertiaTable\Support\TableReference;
use Musing\InertiaTable\Table;

final class FilterOptionsController
{
    public function __invoke(Request $request, string $table, string $filter): JsonResponse
    {
        $tableClass = TableReference::decode($table);
        abort_if($tableClass === null, 404);

        $tableInstance = app($tableClass);
        abort_unless($tableInstance instanceof Table, 404);

        $definition = $tableInstance->filter($filter);
        abort_unless($definition instanceof SetFilter && $definition->hasRemoteOptions(), 404);

        $validated = $request->validate([
            'search' => ['sometimes', 'nullable', 'string', 'max:200'],
            'cursor' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'selected' => ['sometimes', 'array', 'max:100'],
            'state' => ['sometimes', 'array'],
            'perPage' => ['sometimes', 'integer', 'min:1'],
        ]);
        $state = $tableInstance->normalizeSelectionState(
            is_array($validated['state'] ?? null) ? $validated['state'] : [],
        );
        $selected = $definition->normalizeOptionValues($validated['selected'] ?? []);
        $optionRequest = new FilterOptionRequest(
            request: $request,
            table: $tableInstance,
            filter: $definition,
            search: trim((string) ($validated['search'] ?? '')),
            cursor: filled($validated['cursor'] ?? null) ? (string) $validated['cursor'] : null,
            dependencies: $definition->dependenciesFromState($state),
            selected: $selected,
            state: $state,
            perPage: $definition->resolveOptionPageSize($validated['perPage'] ?? null),
        );

        abort_unless($definition->isOptionLoadingAuthorized($optionRequest), 403);

        return response()->json($definition->resolveRemoteOptions($optionRequest));
    }
}
