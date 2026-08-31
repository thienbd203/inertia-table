<?php

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Musing\InertiaTable\Actions\Action;
use Musing\InertiaTable\Columns\NumberColumn;
use Musing\InertiaTable\Columns\TextColumn;
use Musing\InertiaTable\Exports\Export;
use Musing\InertiaTable\Exports\ExportManager;
use Musing\InertiaTable\Filters\NumericFilter;
use Musing\InertiaTable\Filters\TextFilter;
use Musing\InertiaTable\Table;
use Spatie\QueryBuilder\QueryBuilder;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RelationshipOrganization extends Model
{
    protected $table = 'relationship_organizations';

    protected $guarded = [];

    public $timestamps = false;
}

class RelationshipAuthor extends Model
{
    protected $table = 'relationship_authors';

    protected $guarded = [];

    public $timestamps = false;

    public function organization(): BelongsTo
    {
        return $this->belongsTo(RelationshipOrganization::class, 'organization_id');
    }

    public function profile(): HasOne
    {
        return $this->hasOne(RelationshipProfile::class, 'author_id');
    }
}

class RelationshipProfile extends Model
{
    protected $table = 'relationship_profiles';

    protected $guarded = [];

    public $timestamps = false;
}

class RelationshipTopic extends Model
{
    protected $table = 'relationship_topics';

    protected $guarded = [];

    public $timestamps = false;

    public function author(): BelongsTo
    {
        return $this->belongsTo(RelationshipAuthor::class, 'author_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(RelationshipComment::class, 'topic_id');
    }
}

class RelationshipComment extends Model
{
    protected $table = 'relationship_comments';

    protected $guarded = [];

    public $timestamps = false;
}

class RelationshipTopicsTable extends Table
{
    protected ?string $name = 'relationship_topics';

    protected ?string $defaultSort = 'id';

    public function query(): Builder
    {
        return RelationshipTopic::query();
    }

    public function columns(): array
    {
        return [
            NumberColumn::make('id', 'ID')->sortable(),
            TextColumn::make('title', 'Title')->searchable()->sortable(),
            TextColumn::make('author.name', 'Author')->searchable()->sortable(),
            TextColumn::make('author.organization.name', 'Organization')->searchable()->sortable(),
            TextColumn::make('author.profile.city', 'City')->searchable(),
            TextColumn::make('comments.body', 'Comments')->searchable(),
            NumberColumn::make('comments.score', 'Comment score')->sortable(),
        ];
    }

    public function filters(): array
    {
        return [
            TextFilter::make('author.name', 'Author'),
            TextFilter::make('author.organization.name', 'Organization'),
            TextFilter::make('author.profile.city', 'City')->nullable(),
            NumericFilter::make('comments.score', 'Comment score'),
        ];
    }

    public function actions(): array
    {
        return [Action::make('archive')->bulk()];
    }

    public function exports(): array
    {
        return [
            Export::make('all', 'All', 'all.csv'),
            Export::make('filtered', 'Filtered', 'filtered.csv')->filtered(),
        ];
    }
}

class JoinedRelationshipTopicsTable extends RelationshipTopicsTable
{
    public static int $customizations = 0;

    protected function withQueryBuilder(QueryBuilder $query): QueryBuilder
    {
        self::$customizations++;
        $query->leftJoin(
            'relationship_comments',
            'relationship_comments.topic_id',
            '=',
            'relationship_topics.id',
        );

        return $query;
    }
}

beforeEach(function () {
    JoinedRelationshipTopicsTable::$customizations = 0;

    Schema::create('relationship_organizations', function (Blueprint $table) {
        $table->id();
        $table->string('name');
    });
    Schema::create('relationship_authors', function (Blueprint $table) {
        $table->id();
        $table->foreignId('organization_id')->nullable();
        $table->string('name');
    });
    Schema::create('relationship_profiles', function (Blueprint $table) {
        $table->id();
        $table->foreignId('author_id');
        $table->string('city')->nullable();
    });
    Schema::create('relationship_topics', function (Blueprint $table) {
        $table->id();
        $table->foreignId('author_id')->nullable();
        $table->string('title');
        $table->string('status');
    });
    Schema::create('relationship_comments', function (Blueprint $table) {
        $table->id();
        $table->foreignId('topic_id');
        $table->string('body');
        $table->unsignedInteger('score');
    });

    $acme = RelationshipOrganization::query()->create(['name' => 'Acme']);
    $globex = RelationshipOrganization::query()->create(['name' => 'Globex']);
    $alice = RelationshipAuthor::query()->create([
        'organization_id' => $acme->getKey(),
        'name' => 'Alice',
    ]);
    $bob = RelationshipAuthor::query()->create([
        'organization_id' => $globex->getKey(),
        'name' => 'Bob',
    ]);
    RelationshipProfile::query()->create([
        'author_id' => $alice->getKey(),
        'city' => 'Paris',
    ]);
    RelationshipTopic::query()->insert([
        ['author_id' => $alice->getKey(), 'title' => 'Laravel Guide', 'status' => 'published'],
        ['author_id' => $alice->getKey(), 'title' => 'Queue Guide', 'status' => 'draft'],
        ['author_id' => $bob->getKey(), 'title' => 'Vue Guide', 'status' => 'review'],
        ['author_id' => null, 'title' => 'Orphan Guide', 'status' => 'draft'],
    ]);
    RelationshipComment::query()->insert([
        ['topic_id' => 1, 'body' => 'Framework notes', 'score' => 2],
        ['topic_id' => 2, 'body' => 'Retry failed jobs', 'score' => 8],
        ['topic_id' => 2, 'body' => 'Queue worker', 'score' => 10],
    ]);
});

/** @param array<string, mixed> $state */
function relationshipRequest(array $state = []): Request
{
    return Request::create('/topics', 'GET', [
        'table' => ['relationship_topics' => $state],
    ]);
}

function relationshipStreamedContent(StreamedResponse $response): string
{
    ob_start();
    $response->sendContent();

    return (string) ob_get_clean();
}

it('searches belongs-to, has-one, has-many, and nested relationship paths', function (string $search, array $ids) {
    $resource = (new RelationshipTopicsTable)->resolve(
        relationshipRequest(['search' => $search]),
    )->toArray();

    expect(array_column($resource['results']['data'], 'id'))->toBe($ids);
})->with([
    'belongs to' => ['Alice', [1, 2]],
    'nested belongs to' => ['Acme', [1, 2]],
    'nested has one' => ['Paris', [1, 2]],
    'has many' => ['worker', [2]],
    'null relationships' => ['missing relationship', []],
]);

it('filters only declared relationship paths and supports nested missing values', function () {
    $table = new RelationshipTopicsTable;
    $author = $table->resolve(relationshipRequest(['filters' => [
        'author.name' => ['enabled' => true, 'clause' => 'contains', 'value' => 'Bob'],
        'unsafe.path' => ['enabled' => true, 'clause' => 'contains', 'value' => 'anything'],
    ]]))->toArray();
    $organization = $table->resolve(relationshipRequest(['filters' => [
        'author.organization.name' => ['enabled' => true, 'clause' => 'equals', 'value' => 'Acme'],
    ]]))->toArray();
    $comment = $table->resolve(relationshipRequest(['filters' => [
        'comments.score' => ['enabled' => true, 'clause' => 'greater_than', 'value' => 8],
    ]]))->toArray();
    $missingCity = $table->resolve(relationshipRequest(['filters' => [
        'author.profile.city' => ['enabled' => true, 'clause' => 'is_not_set', 'value' => null],
    ]]))->toArray();

    expect(array_column($author['results']['data'], 'id'))->toBe([3])
        ->and($author['state']['filters'])->not->toHaveKey('unsafe.path')
        ->and(array_column($organization['results']['data'], 'id'))->toBe([1, 2])
        ->and(array_column($comment['results']['data'], 'id'))->toBe([2])
        ->and(array_column($missingCity['results']['data'], 'id'))->toBe([3, 4]);
});

it('sorts nullable to-one and duplicate-producing to-many relationships through power joins', function () {
    $authors = (new RelationshipTopicsTable)->resolve(
        relationshipRequest(['sort' => 'author.name']),
    )->toArray();
    $authorsDescending = (new RelationshipTopicsTable)->resolve(
        relationshipRequest(['sort' => '-author.name']),
    )->toArray();
    $comments = (new RelationshipTopicsTable)->resolve(
        relationshipRequest(['sort' => '-comments.score']),
    )->toArray();

    expect($authors['results']['total'])->toBe(4)
        ->and(array_column($authors['results']['data'], 'id'))->toBe([1, 2, 3, 4])
        ->and(array_column($authorsDescending['results']['data'], 'id'))->toBe([3, 1, 2, 4])
        ->and($comments['results']['total'])->toBe(4)
        ->and(array_column($comments['results']['data'], 'id'))
        ->toBe([2, 1, 3, 4]);
});

it('keeps joined query customization unique and applies it to results, selections, and exports', function () {
    $table = new JoinedRelationshipTopicsTable;
    $resource = $table->resolve(relationshipRequest())->toArray();
    $selection = $table->selection([
        'all' => true,
        'keys' => [],
        'except' => [],
        'table' => 'relationship_topics',
        'state' => ['search' => 'Queue worker', 'filters' => [], 'sort' => null],
    ]);
    $export = $table->export('all');
    expect($export)->toBeInstanceOf(Export::class);
    $csv = relationshipStreamedContent(app(ExportManager::class)->download(
        Request::create('/', 'POST'),
        $table,
        $export,
        [],
        null,
    ));

    expect($resource['results']['total'])->toBe(4)
        ->and(array_column($resource['results']['data'], 'id'))->toBe([1, 2, 3, 4])
        ->and($selection->query()->pluck('relationship_topics.id')->all())->toBe([2])
        ->and(substr_count($csv, 'Queue Guide'))->toBe(1)
        ->and(JoinedRelationshipTopicsTable::$customizations)->toBeGreaterThanOrEqual(3);
});

it('uses the same relationship query for filtered exports and all-matching selection', function () {
    $table = new RelationshipTopicsTable;
    $state = ['search' => 'Acme', 'filters' => [], 'sort' => 'id'];
    $selection = $table->selection([
        'all' => true,
        'keys' => [],
        'except' => [1],
        'table' => 'relationship_topics',
        'state' => $state,
    ]);
    $export = $table->export('filtered');
    expect($export)->toBeInstanceOf(Export::class);
    $csv = relationshipStreamedContent(app(ExportManager::class)->download(
        Request::create('/', 'POST'),
        $table,
        $export,
        $state,
        null,
    ));

    expect($selection->query()->pluck('relationship_topics.id')->all())->toBe([2])
        ->and($csv)->toContain('Laravel Guide')
        ->toContain('Queue Guide')
        ->not->toContain('Vue Guide');
});

it('supports explicit priority sorting while leaving unmapped values last', function () {
    $column = TextColumn::make('status')
        ->sortable()
        ->sortUsingPriority(['published', 'review', 'draft']);
    $ascending = RelationshipTopic::query();
    $column->applySort($ascending, 'asc');
    $descending = RelationshipTopic::query();
    $column->applySort($descending, 'desc');

    expect($ascending->pluck('status')->all())
        ->toBe(['published', 'review', 'draft', 'draft'])
        ->and($descending->pluck('status')->all())
        ->toBe(['draft', 'draft', 'review', 'published']);
});

it('fails with an actionable error when the configured relationship sorter is invalid', function () {
    config()->set('inertia-table.relationship_sorter', stdClass::class);
    $column = TextColumn::make('author.name')->sortable();

    expect(fn () => $column->applySort(RelationshipTopic::query(), 'asc'))
        ->toThrow(LogicException::class, 'must implement');
});
