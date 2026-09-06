<?php

namespace Musing\InertiaTable\Tests\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Musing\InertiaTable\Actions\Action;
use Musing\InertiaTable\Columns\BadgeColumn;
use Musing\InertiaTable\Columns\NumberColumn;
use Musing\InertiaTable\Columns\TextColumn;
use Musing\InertiaTable\Exports\Export;
use Musing\InertiaTable\Filters\SelectFilter;
use Musing\InertiaTable\PaginationType;
use Musing\InertiaTable\Table;
use Musing\InertiaTable\Variant;
use Musing\InertiaTable\Views;

class ContractTopicRecord extends Model
{
    protected $table = 'contract_topics';

    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return ['amount' => 'integer'];
    }
}

class ContractTopicsTable extends Table
{
    protected ?string $name = 'contract_topics';

    protected ?string $defaultSort = 'name';

    protected ?int $perPage = 1;

    /** @var array<int, int> */
    protected ?array $perPageOptions = [1, 2];

    public function query(): Builder
    {
        return ContractTopicRecord::query();
    }

    public function columns(): array
    {
        return [
            TextColumn::make('name', 'Name')->searchable()->sortable(),
            NumberColumn::make('amount', 'Amount')->sortable()->summary('sum'),
            BadgeColumn::make('status', 'Status')->variant(Variant::Success),
        ];
    }

    public function filters(): array
    {
        return [
            SelectFilter::make('status', 'Status')->options([
                'open' => 'Open',
                'closed' => 'Closed',
            ]),
        ];
    }

    public function actions(): array
    {
        return [
            Action::make('archive', 'Archive')
                ->bulk()
                ->endpoint('post', '/contract-topics/archive'),
        ];
    }

    public function exports(): array
    {
        return [Export::make('csv', 'Contract CSV', 'contract-topics.csv')->filtered()->withSummaries()];
    }

    public function views(): ?Views
    {
        return Views::make()->scopeUser(false);
    }
}

class ContractTopicsWithoutViewsTable extends ContractTopicsTable
{
    public function views(): ?Views
    {
        return null;
    }
}

class ContractTopicsSimpleTable extends ContractTopicsWithoutViewsTable
{
    protected ?PaginationType $paginationType = PaginationType::Simple;
}

class ContractTopicsCursorTable extends ContractTopicsWithoutViewsTable
{
    protected ?PaginationType $paginationType = PaginationType::Cursor;
}

class ContractTopicsUnpaginatedTable extends ContractTopicsWithoutViewsTable
{
    protected bool $pagination = false;
}
