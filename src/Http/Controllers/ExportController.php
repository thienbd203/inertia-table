<?php

namespace Musing\InertiaTable\Http\Controllers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use LogicException;
use Musing\InertiaTable\Contracts\Exporter;
use Musing\InertiaTable\Exports\Export;
use Musing\InertiaTable\Exports\ExportScope;
use Musing\InertiaTable\Support\TableReference;
use Musing\InertiaTable\Table;
use Symfony\Component\HttpFoundation\Response;

final class ExportController
{
    public function __invoke(Request $request, string $table, string $export): Response
    {
        $tableClass = TableReference::decode($table);
        abort_if($tableClass === null, 404);

        $tableInstance = app($tableClass);
        abort_unless($tableInstance instanceof Table, 404);
        $definition = $tableInstance->export($export);
        abort_unless($definition instanceof Export, 404);
        abort_unless($definition->isAuthorized($request, $tableInstance), 403);

        $validated = $request->validate([
            'state' => ['sometimes', 'array'],
            'selection' => ['sometimes', 'array'],
        ]);
        $state = $validated['state'] ?? [];
        $query = $this->resolveQuery($tableInstance, $definition, $validated, $state);
        $columns = $tableInstance->columnsForExport($definition, $state);

        if ($columns === []) {
            throw ValidationException::withMessages([
                'export' => 'This export has no available columns.',
            ]);
        }

        return $this->resolveExporter($definition)->download(
            $request,
            $tableInstance,
            $definition,
            $query,
            $columns,
        );
    }

    /**
     * @param  array<string, mixed>  $validated
     * @param  array<string, mixed>  $state
     * @return Builder<Model>
     */
    private function resolveQuery(
        Table $table,
        Export $export,
        array $validated,
        array $state,
    ): Builder {
        if ($export->scope() === ExportScope::All) {
            return $table->query();
        }

        if ($export->scope() === ExportScope::Filtered) {
            return $table->queryForState($state);
        }

        $selection = $validated['selection'] ?? null;

        if (! is_array($selection)) {
            throw ValidationException::withMessages([
                'selection' => 'Select at least one row before exporting.',
            ]);
        }

        return $table->selection($selection)->query();
    }

    private function resolveExporter(Export $export): Exporter
    {
        $adapter = config("inertia-table.exporters.{$export->typeName()}");

        if (! is_string($adapter) || $adapter === '') {
            throw ValidationException::withMessages([
                'export' => "No exporter adapter is configured for [{$export->typeName()}].",
            ]);
        }

        $instance = app($adapter);

        if (! $instance instanceof Exporter) {
            throw new LogicException("The [{$adapter}] exporter must implement ".Exporter::class.'.');
        }

        return $instance;
    }
}
