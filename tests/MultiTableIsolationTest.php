<?php

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Musing\InertiaTable\Columns\TextColumn;
use Musing\InertiaTable\Table;

class IsolationPrimaryRecord extends Model
{
    protected $table = 'isolation_primary_records';

    protected $guarded = [];

    public $timestamps = false;
}

class IsolationSecondaryRecord extends Model
{
    protected $table = 'isolation_secondary_records';

    protected $guarded = [];

    public $timestamps = false;
}

class IsolationPrimaryTable extends Table
{
    protected ?string $name = 'primary';

    protected ?string $defaultSort = 'name';

    public function query(): Builder
    {
        return IsolationPrimaryRecord::query();
    }

    public function columns(): array
    {
        return [TextColumn::make('name', 'Name')->searchable()->sortable()];
    }
}

class IsolationSecondaryTable extends Table
{
    protected ?string $name = 'secondary';

    protected ?string $defaultSort = 'name';

    public function query(): Builder
    {
        return IsolationSecondaryRecord::query();
    }

    public function columns(): array
    {
        return [TextColumn::make('name', 'Name')->searchable()->sortable()];
    }
}

beforeEach(function () {
    Schema::create('isolation_primary_records', function (Blueprint $table) {
        $table->id();
        $table->string('name');
    });
    Schema::create('isolation_secondary_records', function (Blueprint $table) {
        $table->id();
        $table->string('name');
    });

    IsolationPrimaryRecord::query()->insert([
        ['name' => 'Primary Alpha'],
        ['name' => 'Primary Beta'],
    ]);
    IsolationSecondaryRecord::query()->insert([
        ['name' => 'Secondary Alpha'],
        ['name' => 'Secondary Beta'],
    ]);
});

it('resolves two named tables on one request without leaking state between them', function () {
    $request = Request::create('/dashboard', 'GET', [
        'foo' => 'bar',
        'table' => [
            'primary' => ['search' => 'Alpha', 'sort' => '-name'],
            'secondary' => ['search' => 'Beta'],
        ],
    ]);

    $primary = (new IsolationPrimaryTable)->resolve($request)->toArray();
    $secondary = (new IsolationSecondaryTable)->resolve($request)->toArray();

    expect(array_column($primary['results']['data'], 'name'))->toBe(['Primary Alpha'])
        ->and($primary['state']['sort'])->toBe('-name')
        ->and(array_column($secondary['results']['data'], 'name'))->toBe(['Secondary Beta'])
        ->and($secondary['state']['sort'])->toBe('name');
});

it('does not mutate the original request query string while resolving', function () {
    $request = Request::create('/dashboard', 'GET', [
        'foo' => 'bar',
        'table' => [
            'primary' => ['search' => 'Alpha'],
        ],
    ]);
    $originalQuery = $request->query();

    (new IsolationPrimaryTable)->resolve($request);

    expect($request->query())->toBe($originalQuery)
        ->and($request->query('foo'))->toBe('bar')
        ->and($request->query('filter'))->toBeNull()
        ->and($request->query('sort'))->toBeNull();
});
