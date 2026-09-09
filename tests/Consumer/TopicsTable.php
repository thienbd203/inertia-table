<?php

namespace Musing\InertiaTable\Tests\Consumer;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Musing\InertiaTable\Columns\DateTimeColumn;
use Musing\InertiaTable\Columns\TextColumn;
use Musing\InertiaTable\Filters\DateFilter;
use Musing\InertiaTable\Filters\NumericFilter;
use Musing\InertiaTable\Filters\SetFilter;
use Musing\InertiaTable\Table;

class Topic extends Model
{
    protected $table = 'topics';
}

final class TopicsTable extends Table
{
    protected ?string $name = 'topics';

    protected ?string $defaultSort = 'name';

    public function query(): Builder
    {
        return Topic::query();
    }

    public function columns(): array
    {
        return [
            TextColumn::make('name', 'Name')->searchable()->sortable(),
            TextColumn::make('status', 'Status'),
            DateTimeColumn::make('created_at', 'Created')->sortable(),
        ];
    }

    public function filters(): array
    {
        return [
            SetFilter::make('status', 'Status')->options([
                'published' => 'Published',
                'draft' => 'Draft',
            ])->lazy(),
            NumericFilter::make('id', 'ID'),
            DateFilter::make('created_at', 'Created'),
        ];
    }
}
