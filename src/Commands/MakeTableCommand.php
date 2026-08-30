<?php

namespace Musing\InertiaTable\Commands;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Str;

final class MakeTableCommand extends GeneratorCommand
{
    protected $signature = 'make:inertia-table
        {name : The name of the table class}
        {--model= : The Eloquent model used by the table}
        {--force : Create the class even if the table already exists}';

    protected $description = 'Create a new Inertia table class';

    protected $type = 'Inertia table';

    protected function getStub(): string
    {
        return dirname(__DIR__, 2).'/stubs/table.stub';
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace.'\Tables';
    }

    protected function buildClass($name): string
    {
        $stub = parent::buildClass($name);
        $model = $this->modelClass($name);

        return str_replace(
            ['{{ modelNamespace }}', '{{ model }}'],
            [$model, class_basename($model)],
            $stub,
        );
    }

    private function modelClass(string $tableClass): string
    {
        $requested = $this->option('model');
        $model = is_string($requested) && trim($requested) !== ''
            ? trim($requested)
            : Str::singular(Str::replaceEnd(
                'Table',
                '',
                class_basename($tableClass),
            ));

        return $this->qualifyModel($model);
    }
}
