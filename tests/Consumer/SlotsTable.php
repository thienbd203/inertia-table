<?php

namespace Musing\InertiaTable\Tests\Consumer;

use Illuminate\Database\Eloquent\Builder;
use Musing\InertiaTable\Actions\Action;
use Musing\InertiaTable\Columns\ActionColumn;
use Musing\InertiaTable\Columns\TextColumn;
use Musing\InertiaTable\Table;

final class SlotsTable extends Table
{
    protected ?string $name = 'topics';

    public function query(): Builder
    {
        return Topic::query();
    }

    public function columns(): array
    {
        return [
            TextColumn::make('name')->searchable()->sortable(),
            ActionColumn::new(),
        ];
    }

    public function actions(): array
    {
        return [Action::make('preview', 'Preview')->row()];
    }
}
