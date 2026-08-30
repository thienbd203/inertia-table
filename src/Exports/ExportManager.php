<?php

namespace Musing\InertiaTable\Exports;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use LogicException;
use Musing\InertiaTable\Columns\Column;
use Musing\InertiaTable\Contracts\Exporter;
use Musing\InertiaTable\Table;
use Symfony\Component\HttpFoundation\Response;

final class ExportManager
{
    /**
     * @param  array<string, mixed>  $state
     * @param  array<string, mixed>|null  $selection
     */
    public function download(
        Request $request,
        Table $table,
        Export $export,
        array $state,
        ?array $selection,
    ): Response {
        [$query, $columns] = $this->resolve($table, $export, $state, $selection);

        return $this->exporter($export)->download(
            $request,
            $table,
            $export,
            $query,
            $columns,
        );
    }

    /**
     * @param  array<string, mixed>  $state
     * @param  array<string, mixed>|null  $selection
     */
    public function store(
        Request $request,
        Table $table,
        Export $export,
        array $state,
        ?array $selection,
        string $disk,
        string $path,
    ): void {
        [$query, $columns] = $this->resolve($table, $export, $state, $selection);
        $this->exporter($export)->store(
            $request,
            $table,
            $export,
            $query,
            $columns,
            $disk,
            $path,
        );
    }

    /**
     * @param  array<string, mixed>  $state
     * @param  array<string, mixed>|null  $selection
     * @return array{Builder<Model>, array<int, Column>}
     */
    private function resolve(
        Table $table,
        Export $export,
        array $state,
        ?array $selection,
    ): array {
        $query = match ($export->scope()) {
            ExportScope::All => $table->queryForAll(),
            ExportScope::Filtered => $table->queryForState($state),
            ExportScope::Selected => $this->selectedQuery($table, $selection),
        };
        $columns = $table->columnsForExport($export, $state);

        if ($columns === []) {
            throw ValidationException::withMessages([
                'export' => 'This export has no available columns.',
            ]);
        }

        return [$query, $columns];
    }

    /**
     * @param  array<string, mixed>|null  $selection
     * @return Builder<Model>
     */
    private function selectedQuery(Table $table, ?array $selection): Builder
    {
        if ($selection === null) {
            throw ValidationException::withMessages([
                'selection' => 'Select at least one row before exporting.',
            ]);
        }

        return $table->selection($selection)->query();
    }

    private function exporter(Export $export): Exporter
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
