<?php

use Illuminate\Database\Eloquent\Builder;
use Musing\InertiaTable\Actions\Action;
use Musing\InertiaTable\Exports\Export;
use Musing\InertiaTable\Table;

class DiagnosticTable extends Table
{
    public string $method;

    public array $entries;

    public function query(): Builder
    {
        throw new RuntimeException('Invalid declarations must fail before querying.');
    }

    public function columns(): array
    {
        return $this->method === 'columns' ? $this->entries : [];
    }

    public function filters(): array
    {
        return $this->method === 'filters' ? $this->entries : [];
    }

    public function actions(): array
    {
        return $this->method === 'actions' ? $this->entries : [];
    }

    public function exports(): array
    {
        return $this->method === 'exports' ? $this->entries : [];
    }
}

it('identifies the declaration and entry without dumping its value', function (string $method) {
    $table = new DiagnosticTable;
    $table->method = $method;
    $table->entries = ['broken' => 'private-value'];
    try {
        $table->resolve();
        $this->fail('Expected declaration validation to fail.');
    } catch (LogicException $error) {
        expect($error->getMessage())->toContain('DiagnosticTable::'.$method.'()[broken]', 'returned string', 'instance.')
            ->not->toContain('private-value');
    }
})->with(['columns', 'filters', 'actions', 'exports']);

it('identifies duplicate action and export keys', function (string $method) {
    $table = new DiagnosticTable;
    $table->method = $method;
    $table->entries = $method === 'actions'
        ? [Action::make('same'), Action::make('same')]
        : [Export::make('same'), Export::make('same')];
    expect(fn () => $table->resolve())->toThrow(LogicException::class, 'duplicate [same] found. DiagnosticTable::'.$method.'()[1]');
})->with(['actions', 'exports']);
