<?php

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Musing\InertiaTable\Columns\NumberColumn;
use Musing\InertiaTable\Columns\TextColumn;
use Musing\InertiaTable\Filters\BooleanFilter;
use Musing\InertiaTable\Table;
use Musing\InertiaTable\TableView;
use Musing\InertiaTable\Views;

class ViewUser extends Authenticatable
{
    protected $table = 'view_users';

    protected $guarded = [];

    public $timestamps = false;
}

class ViewTopicRecord extends Model
{
    protected $table = 'view_topics';

    protected $guarded = [];

    public $timestamps = false;
}

class ViewTopicsTable extends Table
{
    protected ?string $name = 'view_topics';

    protected ?string $defaultSort = 'name';

    protected ?int $perPage = 2;

    /** @var array<int, int> */
    protected ?array $perPageOptions = [1, 2, 25];

    public function query(): Builder
    {
        return ViewTopicRecord::query();
    }

    public function columns(): array
    {
        return [
            NumberColumn::make('id')->toggleable(false),
            TextColumn::make('name')->searchable()->sortable()->stickable(),
            NumberColumn::make('score')->sortable()->stickable(),
        ];
    }

    public function filters(): array
    {
        return [BooleanFilter::make('is_featured')];
    }

    public function views(): ?Views
    {
        return Views::make();
    }
}

class GlobalViewTopicsTable extends ViewTopicsTable
{
    public function views(): ?Views
    {
        return Views::make()->scopeUser(false);
    }
}

class SearchViewTopicsTable extends ViewTopicsTable
{
    public function views(): ?Views
    {
        return Views::make()->includeSearch();
    }
}

class TenantViewTopicsTable extends ViewTopicsTable
{
    public static string $tenant = 'tenant-one';

    public function views(): ?Views
    {
        return Views::make()
            ->scopeUser(false)
            ->attributes(fn () => ['tenant_id' => self::$tenant]);
    }
}

class NamedViewTopicsTable extends ViewTopicsTable
{
    public static string $tableName = 'first';

    public function name(): string
    {
        return self::$tableName;
    }

    public function views(): ?Views
    {
        return Views::make()->scopeUser(false)->scopeTableName();
    }
}

class CustomTableView extends TableView {}

class CustomModelViewTopicsTable extends ViewTopicsTable
{
    public function views(): ?Views
    {
        return Views::make()->scopeUser(false)->modelClass(CustomTableView::class);
    }
}

class RestrictedViewTopicsTable extends ViewTopicsTable
{
    public function views(): ?Views
    {
        return Views::make()
            ->authorizeUpdate(false)
            ->authorizeDelete(true)
            ->authorizeShare(false)
            ->authorizeDefault(false);
    }
}

beforeEach(function () {
    Schema::create('view_users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
    });
    Schema::create('view_topics', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->unsignedInteger('score');
        $table->boolean('is_featured')->default(false);
    });
    $migration = require dirname(__DIR__).'/database/migrations/create_table_views_table.php.stub';
    $migration->up();

    ViewTopicRecord::query()->insert([
        ['name' => 'Alpha', 'score' => 10, 'is_featured' => false],
        ['name' => 'Beta', 'score' => 30, 'is_featured' => true],
        ['name' => 'Gamma', 'score' => 20, 'is_featured' => true],
    ]);
});

function viewRequest(string $table = 'view_topics', array $state = []): Request
{
    return Request::create('/topics', 'GET', [
        'table' => [$table => $state],
    ]);
}

function saveTableView(
    Table $table,
    Request $request,
    string $name,
    array $state,
    bool $default = false,
    bool $shared = false,
): TableView {
    $views = $table->views();
    expect($views)->toBeInstanceOf(Views::class);
    $view = $views->newQuery()->create(
        $views->valuesFor($table, $request, $name, $state),
    );
    $view->forceFill([
        'is_default' => $default,
        ...($shared ? ['is_shared' => true] : []),
    ])->save();

    return $view;
}

it('applies selected and default views below explicit URL state', function () {
    $user = ViewUser::query()->create(['name' => 'One']);
    $this->actingAs($user);
    $table = new ViewTopicsTable;
    $default = saveTableView($table, viewRequest(), 'Featured', [
        'search' => 'Beta',
        'sort' => '-score',
        'filters' => [
            'is_featured' => ['enabled' => true, 'clause' => 'is_true', 'value' => null],
        ],
        'columns' => ['score' => false],
        'perPage' => 1,
    ], default: true);
    $other = saveTableView($table, viewRequest(), 'Regular', [
        'sort' => 'score',
        'filters' => [
            'is_featured' => ['enabled' => true, 'clause' => 'is_false', 'value' => null],
        ],
        'perPage' => 25,
    ]);

    $fromDefault = $table->resolve(viewRequest())->toArray();
    $selected = $table->resolve(viewRequest(state: ['view' => $other->getKey()]))->toArray();
    $explicit = $table->resolve(viewRequest(state: [
        'view' => $default->getKey(),
        'sort' => 'name',
        'perPage' => 2,
        'filters' => [
            'is_featured' => ['enabled' => false, 'clause' => 'is_true', 'value' => null],
        ],
    ]))->toArray();

    expect($fromDefault['state'])
        ->view->toBe($default->getKey())
        ->search->toBe('')
        ->sort->toBe('-score')
        ->perPage->toBe(1)
        ->and($fromDefault['results']['total'])->toBe(2)
        ->and($selected['state'])
        ->view->toBe($other->getKey())
        ->sort->toBe('score')
        ->perPage->toBe(25)
        ->and($selected['results']['total'])->toBe(1)
        ->and($explicit['state'])
        ->view->toBe($default->getKey())
        ->sort->toBe('name')
        ->perPage->toBe(2)
        ->and($explicit['state']['filters']['is_featured']['enabled'])->toBeFalse()
        ->and($explicit['results']['total'])->toBe(3);
});

it('normalizes stale saved state through current declarations', function () {
    $user = ViewUser::query()->create(['name' => 'One']);
    $this->actingAs($user);
    $table = new ViewTopicsTable;
    $view = saveTableView($table, viewRequest(), 'Stale', []);
    $view->state = [
        'schemaVersion' => 0,
        'sort' => 'removed_column',
        'filters' => [
            'removed_filter' => ['enabled' => true, 'clause' => 'equals', 'value' => 'unsafe'],
        ],
        'columns' => ['removed_column' => false, 'score' => false],
        'pinnedColumns' => ['left' => ['removed_column', 'name'], 'right' => ['name', 'score']],
        'perPage' => 999,
    ];
    $view->save();

    $resource = $table->resolve(viewRequest(state: ['view' => $view->getKey()]))->toArray();
    $savedState = $resource['views']['items'][0]['state'];

    expect($resource['state'])
        ->sort->toBe('name')
        ->perPage->toBe(2)
        ->and($resource['state']['filters'])->not->toHaveKey('removed_filter')
        ->and($resource['state']['columns'])->not->toHaveKey('removed_column')
        ->and($savedState['schemaVersion'])->toBe(1)
        ->and($savedState['pinnedColumns'])->toBe([
            'left' => ['name'],
            'right' => ['score'],
        ]);
});

it('stores normalized views and rejects duplicate names in one scope', function () {
    $user = ViewUser::query()->create(['name' => 'One']);
    $this->actingAs($user);
    $resource = (new ViewTopicsTable)->resolve(viewRequest())->toArray();
    $endpoint = $resource['views']['storeEndpoint'];
    $state = [
        'search' => 'ephemeral',
        'sort' => '-score',
        'filters' => ['unknown' => ['enabled' => true, 'clause' => 'equals', 'value' => 'x']],
        'columns' => ['unknown' => false, 'score' => false],
        'perPage' => 1,
    ];

    $this->post($endpoint, ['name' => 'My view', 'state' => $state])->assertRedirect();
    $stored = TableView::query()->firstOrFail();

    expect($stored->user_id)->toBe((string) $user->getKey())
        ->and($stored->state)->not->toHaveKey('search')
        ->and($stored->state['filters'])->not->toHaveKey('unknown')
        ->and($stored->state['columns'])->not->toHaveKey('unknown')
        ->and($stored->state['columns']['score'])->toBeFalse();

    $this->postJson($endpoint, ['name' => 'My view', 'state' => $state])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('name');
});

it('detects stale concurrent updates with lock versions', function () {
    $user = ViewUser::query()->create(['name' => 'One']);
    $this->actingAs($user);
    $table = new ViewTopicsTable;
    $view = saveTableView($table, viewRequest(), 'Original', []);
    $item = $table->resolve(viewRequest(state: ['view' => $view->getKey()]))->toArray()['views']['items'][0];

    $this->patch($item['endpoints']['update'], [
        'name' => 'Renamed',
        'version' => 0,
    ])->assertRedirect();
    $this->patchJson($item['endpoints']['update'], [
        'name' => 'Stale rename',
        'version' => 0,
    ])->assertUnprocessable()->assertJsonValidationErrors('view');

    expect($view->fresh())
        ->name->toBe('Renamed')
        ->lock_version->toBe(1);
});

it('isolates private views per user and exposes shared views read-only', function () {
    $owner = ViewUser::query()->create(['name' => 'Owner']);
    $viewer = ViewUser::query()->create(['name' => 'Viewer']);
    $this->actingAs($owner);
    $table = new ViewTopicsTable;
    $private = saveTableView($table, viewRequest(), 'Private', []);
    $shared = saveTableView($table, viewRequest(), 'Shared', [], shared: true);
    $ownerItems = $table->resolve(viewRequest())->toArray()['views']['items'];
    $privateEndpoint = collect($ownerItems)->firstWhere('id', $private->getKey())['endpoints']['update'];
    $sharedEndpoint = collect($ownerItems)->firstWhere('id', $shared->getKey())['endpoints']['update'];

    $this->actingAs($viewer);
    $viewerResource = (new ViewTopicsTable)->resolve(viewRequest())->toArray();

    expect(array_column($viewerResource['views']['items'], 'name'))->toBe(['Shared'])
        ->and($viewerResource['views']['items'][0]['canUpdate'])->toBeFalse()
        ->and($viewerResource['views']['items'][0]['endpoints']['update'])->toBeNull();

    $this->patch($sharedEndpoint, ['name' => 'Hijacked', 'version' => 0])->assertForbidden();
    $this->patch($privateEndpoint, ['name' => 'Hijacked', 'version' => 0])->assertNotFound();
});

it('supports global views custom models and persisted search', function () {
    $global = new GlobalViewTopicsTable;
    $view = saveTableView($global, viewRequest(), 'Everyone', ['sort' => '-score']);
    $custom = new CustomModelViewTopicsTable;
    $customView = saveTableView($custom, viewRequest(), 'Custom', []);
    $user = ViewUser::query()->create(['name' => 'One']);
    $this->actingAs($user);
    $searchTable = new SearchViewTopicsTable;
    $searchView = saveTableView($searchTable, viewRequest(), 'Search', ['search' => 'Gamma']);
    $searchResource = $searchTable->resolve(viewRequest(state: ['view' => $searchView->getKey()]))->toArray();

    expect($view->user_id)->toBeNull()
        ->and($view->is_shared)->toBeTrue()
        ->and($global->resolve(viewRequest())->toArray()['views']['items'])->toHaveCount(1)
        ->and($customView)->toBeInstanceOf(CustomTableView::class)
        ->and($searchResource['state']['search'])->toBe('Gamma')
        ->and($searchResource['results']['total'])->toBe(1);
});

it('scopes views by tenant attributes and optional table names', function () {
    $tenantTable = new TenantViewTopicsTable;
    TenantViewTopicsTable::$tenant = 'tenant-one';
    $tenantOne = saveTableView($tenantTable, viewRequest(), 'Tenant one', []);
    TenantViewTopicsTable::$tenant = 'tenant-two';
    $tenantTwo = saveTableView($tenantTable, viewRequest(), 'Tenant two', []);

    expect($tenantOne->attributes)->toBe(['tenant_id' => 'tenant-one'])
        ->and($tenantTwo->attributes)->toBe(['tenant_id' => 'tenant-two'])
        ->and($tenantTable->resolve(viewRequest())->toArray()['views']['items'][0]['name'])
        ->toBe('Tenant two');

    $named = new NamedViewTopicsTable;
    NamedViewTopicsTable::$tableName = 'first';
    saveTableView($named, viewRequest('first'), 'First table', []);
    NamedViewTopicsTable::$tableName = 'second';
    saveTableView($named, viewRequest('second'), 'Second table', []);

    expect($named->resolve(viewRequest('second'))->toArray()['views']['items'])
        ->toHaveCount(1)
        ->and($named->resolve(viewRequest('second'))->toArray()['views']['items'][0]['name'])
        ->toBe('Second table');
});

it('authorizes view operations independently and changes defaults atomically', function () {
    $user = ViewUser::query()->create(['name' => 'One']);
    $this->actingAs($user);
    $restricted = new RestrictedViewTopicsTable;
    $restrictedView = saveTableView($restricted, viewRequest(), 'Restricted', []);
    $restrictedItem = $restricted->resolve(viewRequest())->toArray()['views']['items'][0];

    expect($restrictedItem)
        ->canUpdate->toBeFalse()
        ->canDelete->toBeTrue()
        ->canShare->toBeFalse()
        ->canDefault->toBeFalse()
        ->and($restrictedItem['endpoints']['update'])->toBeNull()
        ->and($restrictedItem['endpoints']['delete'])->not->toBeNull();

    $table = new ViewTopicsTable;
    $first = saveTableView($table, viewRequest(), 'First', [], default: true);
    $second = saveTableView($table, viewRequest(), 'Second', []);
    $secondItem = collect($table->resolve(viewRequest())->toArray()['views']['items'])
        ->firstWhere('id', $second->getKey());

    $this->post($secondItem['endpoints']['default'], ['version' => 0])->assertRedirect();

    expect($first->fresh()->is_default)->toBeFalse()
        ->and($second->fresh()->is_default)->toBeTrue()
        ->and($restrictedView->fresh())->not->toBeNull();
});
