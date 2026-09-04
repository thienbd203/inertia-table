<?php

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Musing\InertiaTable\Columns\ActionColumn;
use Musing\InertiaTable\Columns\BooleanColumn;
use Musing\InertiaTable\Columns\Column;
use Musing\InertiaTable\Columns\DateColumn;
use Musing\InertiaTable\Columns\NumberColumn;
use Musing\InertiaTable\Columns\TextColumn;
use Musing\InertiaTable\Exports\Export;
use Musing\InertiaTable\Filters\BooleanFilter;
use Musing\InertiaTable\Support\TableReference;
use Musing\InertiaTable\Table;

class ExportTopicRecord extends Model
{
    protected $table = 'export_topics';

    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'published_at' => 'datetime',
        ];
    }
}

class ExportTopicsTable extends Table
{
    protected ?string $name = 'export_topics';

    protected ?int $perPage = 2;

    /** @var array<int, int> */
    protected ?array $perPageOptions = [2, 25];

    public function query(): Builder
    {
        return ExportTopicRecord::query();
    }

    public function columns(): array
    {
        return [
            NumberColumn::make('id', 'ID')->sortable()->reorderable(),
            TextColumn::make('name', 'Name')
                ->searchable()
                ->reorderable()
                ->exportAs(fn (string $value, ExportTopicRecord $topic) => "{$value} #{$topic->getKey()}"),
            BooleanColumn::make('active', 'Active')->reorderable(),
            DateColumn::make('published_at', 'Published')->format('Y-m-d')->reorderable(),
            TextColumn::make('note', 'Note')->reorderable(),
            TextColumn::make('secret', 'Secret')->dontExport(),
            ActionColumn::new(),
        ];
    }

    public function filters(): array
    {
        return [BooleanFilter::make('active')];
    }

    public function exports(): array
    {
        return [
            Export::make('all', 'All CSV', '../topics.csv')->allRows(),
            Export::make('filtered', 'Filtered CSV')->filtered()->visibleColumnsOnly(),
            Export::make('selected', 'Selected CSV')->selected(),
            Export::make('xlsx', 'Excel', type: 'xlsx'),
            Export::make('unauthorized')->authorize(false),
        ];
    }
}

class SummaryExportTopicsTable extends ExportTopicsTable
{
    public function columns(): array
    {
        return [
            NumberColumn::make('id', 'ID')->summary('count'),
            TextColumn::make('name', 'Name'),
            BooleanColumn::make('active', 'Active')->summary('count_distinct'),
            DateColumn::make('published_at', 'Published')->format('Y-m-d'),
            TextColumn::make('note', 'Note'),
        ];
    }

    public function exports(): array
    {
        return [
            Export::make('summary', 'Summary CSV')->filtered()->withSummaries(),
            Export::make('summary-xlsx', 'Summary Excel', type: 'xlsx')->withSummaries(),
        ];
    }
}

class ExportUuidRecord extends Model
{
    protected $table = 'export_uuid_topics';

    protected $primaryKey = 'uuid';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = [];

    public $timestamps = false;
}

class ExportUuidTopicsTable extends Table
{
    protected ?string $name = 'export_uuid_topics';

    public function query(): Builder
    {
        return ExportUuidRecord::query();
    }

    public function columns(): array
    {
        return [
            TextColumn::make('uuid', 'UUID')->sortable(),
            TextColumn::make('name', 'Name')->searchable(),
        ];
    }

    public function exports(): array
    {
        return [Export::make('selected')->selected()];
    }
}

class ExportAuthorRecord extends Model
{
    protected $table = 'export_authors';

    protected $guarded = [];

    public $timestamps = false;
}

class ExportRelationalTopicRecord extends Model
{
    protected $table = 'export_relational_topics';

    protected $guarded = [];

    public $timestamps = false;

    public function author(): BelongsTo
    {
        return $this->belongsTo(ExportAuthorRecord::class, 'author_id');
    }
}

class ExportRelationalTopicsTable extends Table
{
    protected ?string $name = 'export_relational_topics';

    public function query(): Builder
    {
        return ExportRelationalTopicRecord::query()->with('author:id,name');
    }

    public function columns(): array
    {
        return [
            NumberColumn::make('id', 'ID'),
            NumberColumn::make('rank', 'Rank')->sortable(),
            TextColumn::make('author.name', 'Author'),
        ];
    }

    public function exports(): array
    {
        return [
            Export::make('chunked', 'Chunked CSV')
                ->filtered()
                ->chunkSize(5),
            Export::make('optimized', 'Optimized CSV')
                ->allRows()
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->where('rank', 1)
                    ->offset(1)
                    ->limit(3)),
        ];
    }
}

beforeEach(function () {
    Schema::create('export_topics', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->boolean('active');
        $table->dateTime('published_at')->nullable();
        $table->text('note')->nullable();
        $table->string('secret');
    });
    Schema::create('export_uuid_topics', function (Blueprint $table) {
        $table->string('uuid')->primary();
        $table->string('name');
    });
    Schema::create('export_authors', function (Blueprint $table) {
        $table->id();
        $table->string('name');
    });
    Schema::create('export_relational_topics', function (Blueprint $table) {
        $table->id();
        $table->foreignId('author_id');
        $table->unsignedInteger('rank');
    });

    ExportTopicRecord::query()->insert([
        [
            'name' => 'Alpha, "quoted"',
            'active' => false,
            'published_at' => '2026-08-01 12:30:00',
            'note' => null,
            'secret' => 'one',
        ],
        [
            'name' => '=2+2',
            'active' => true,
            'published_at' => null,
            'note' => '@command',
            'secret' => 'two',
        ],
        [
            'name' => 'Gamma',
            'active' => true,
            'published_at' => '2026-08-03 08:00:00',
            'note' => 'plain',
            'secret' => 'three',
        ],
    ]);
    ExportUuidRecord::query()->insert([
        ['uuid' => 'topic-a', 'name' => 'Alpha'],
        ['uuid' => 'topic-b', 'name' => 'Beta'],
        ['uuid' => 'topic-c', 'name' => 'Gamma'],
    ]);
    ExportAuthorRecord::query()->insert([
        ['name' => 'Ada'],
        ['name' => 'Grace'],
    ]);
    ExportRelationalTopicRecord::query()->insert(array_map(
        fn (int $id) => [
            'author_id' => $id % 2 === 0 ? 2 : 1,
            'rank' => $id % 2 === 0 ? 1 : 2,
        ],
        range(1, 12),
    ));
});

/** @return array<int, array<int, string|null>> */
function csvRows(string $content): array
{
    $content = str_starts_with($content, "\xEF\xBB\xBF")
        ? substr($content, 3)
        : $content;
    $stream = fopen('php://temp', 'r+');
    fwrite($stream, $content);
    rewind($stream);
    $rows = [];

    while (($row = fgetcsv($stream, escape: '')) !== false) {
        $rows[] = $row;
    }

    fclose($stream);

    return $rows;
}

/** @return array<string, mixed> */
function exportResource(Table $table): array
{
    return $table->resolve()->toArray();
}

function exportEndpoint(Table $table, string $key): string
{
    return collect(exportResource($table)['exports'])->firstWhere('key', $key)['endpoint'];
}

it('serializes authorized exports and enables selection for selected exports', function () {
    $resource = exportResource(new ExportTopicsTable);

    expect(array_column($resource['exports'], 'key'))->toBe(['all', 'filtered', 'selected', 'xlsx'])
        ->and($resource['capabilities']['hasExports'])->toBeTrue()
        ->and($resource['capabilities']['selectable'])->toBeTrue()
        ->and($resource['results']['selectableTotal'])->toBe(3)
        ->and($resource['exports'][0])
        ->filename->toBe('topics.csv')
        ->endpoint->toContain('signature=');
});

it('exposes adapter formatting metadata without serializing server callbacks', function () {
    $column = TextColumn::make('amount')
        ->exportFormat('#,##0.00')
        ->exportMeta(['style' => ['font' => ['bold' => true]]]);

    expect($column->resolvedExportFormat())->toBe('#,##0.00')
        ->and($column->exportMetadata())->toBe([
            'style' => ['font' => ['bold' => true]],
        ])
        ->and($column->toArray())->not->toHaveKeys(['exportFormat', 'exportMeta']);
});

it('resolves a configurable global chunk size with per-export overrides', function () {
    config()->set('inertia-table.exports.chunk_size', 250);

    expect(Export::make()->resolvedChunkSize())->toBe(250)
        ->and(Export::make()->chunkSize(25)->resolvedChunkSize())->toBe(25)
        ->and(fn () => Export::make()->chunkSize(0))
        ->toThrow(LogicException::class, 'Export chunk size must be at least 1.');
});

it('eager loads relationships in configurable chunks and stabilizes tied sorts', function () {
    $table = new ExportRelationalTopicsTable;
    $endpoint = exportEndpoint($table, 'chunked');
    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });

    $response = $this->post($endpoint, [
        'state' => ['sort' => 'rank'],
    ])->assertOk();
    $rows = array_slice(csvRows($response->streamedContent()), 1);

    expect(array_column($rows, 0))->toBe([
        '2', '4', '6', '8', '10', '12',
        '1', '3', '5', '7', '9', '11',
    ])->and(array_values(array_unique(array_column($rows, 2))))->toBe(['Grace', 'Ada'])
        ->and($queries)->toBeLessThanOrEqual(6);
});

it('allows each export to customize its resolved query', function () {
    $response = $this->post(
        exportEndpoint(new ExportRelationalTopicsTable, 'optimized'),
        ['state' => []],
    )->assertOk();
    $rows = array_slice(csvRows($response->streamedContent()), 1);

    expect(array_column($rows, 0))->toBe(['4', '6', '8']);
});

it('streams UTF-8 CSV with mapped values, escaping, dates, booleans, nulls, and formula protection', function () {
    $response = $this->post(exportEndpoint(new ExportTopicsTable, 'all'), ['state' => []])
        ->assertOk()
        ->assertDownload('topics.csv');
    $content = $response->streamedContent();
    $rows = csvRows($content);

    expect($content)->toStartWith("\xEF\xBB\xBF")
        ->and($rows[0])->toBe(['ID', 'Name', 'Active', 'Published', 'Note'])
        ->and($rows[1])->toBe(['1', 'Alpha, "quoted" #1', 'false', '2026-08-01', ''])
        ->and($rows[2])->toBe(['2', "'=2+2 #2", 'true', '', "'@command"])
        ->and($rows[3])->toBe(['3', 'Gamma #3', 'true', '2026-08-03', 'plain']);
});

it('appends opt-in summaries as a final native CSV row', function () {
    $table = new SummaryExportTopicsTable;
    $resource = exportResource($table);
    $response = $this->post(exportEndpoint($table, 'summary'), [
        'state' => [
            'filters' => [
                'active' => ['enabled' => true, 'clause' => 'is_true', 'value' => null],
            ],
        ],
    ])->assertOk();
    $rows = csvRows($response->streamedContent());

    expect($resource['exports'][0]['includesSummaries'])->toBeTrue()
        ->and($rows)->toHaveCount(4)
        ->and($rows[array_key_last($rows)])->toBe(['2', '', '1', '', '']);
});

it('accepts a null selection for exports that do not require one', function () {
    $this->postJson(exportEndpoint(new ExportTopicsTable, 'all'), [
        'state' => [],
        'selection' => null,
    ])->assertOk();
});

it('normalizes filtered exports and honors visible columns only when requested', function () {
    $response = $this->post(exportEndpoint(new ExportTopicsTable, 'filtered'), [
        'state' => [
            'sort' => '-id',
            'filters' => [
                'active' => ['enabled' => true, 'clause' => 'is_true', 'value' => null],
                'unknown' => ['enabled' => true, 'clause' => 'equals', 'value' => 'unsafe'],
            ],
            'columns' => ['name' => false, 'note' => false, 'unknown' => true],
        ],
    ])->assertOk();
    $rows = csvRows($response->streamedContent());

    expect($rows[0])->toBe(['ID', 'Active', 'Published'])
        ->and(array_column(array_slice($rows, 1), 0))->toBe(['3', '2']);
});

it('follows visible user column layout only when explicitly requested', function () {
    $table = new ExportTopicsTable;
    $state = [
        'columns' => ['note' => false],
        'columnOrder' => ['active', 'name', 'id', 'published_at', 'note', 'secret', '__actions'],
    ];
    $declared = $table->columnsForExport(
        Export::make('declared')->visibleColumnsOnly(),
        $state,
    );
    $layout = $table->columnsForExport(
        Export::make('layout')->visibleColumnLayout(),
        $state,
    );

    expect(array_column(array_map(fn (Column $column) => $column->toArray(), $declared), 'attribute'))
        ->toBe(['id', 'name', 'active', 'published_at'])
        ->and(array_column(array_map(fn (Column $column) => $column->toArray(), $layout), 'attribute'))
        ->toBe(['active', 'name', 'id', 'published_at']);
});

it('exports explicit and all-matching integer selections without clearing their scope', function () {
    $endpoint = exportEndpoint(new ExportTopicsTable, 'selected');
    $explicit = $this->post($endpoint, [
        'state' => ['sort' => '-id'],
        'selection' => [
            'all' => false,
            'keys' => [1, 3],
            'except' => [],
            'table' => 'export_topics',
            'state' => ['sort' => '-id', 'search' => '', 'filters' => []],
        ],
    ])->assertOk();
    $matching = $this->post($endpoint, [
        'selection' => [
            'all' => true,
            'keys' => [],
            'except' => [2],
            'table' => 'export_topics',
            'state' => [
                'sort' => 'id',
                'search' => '',
                'filters' => [
                    'active' => ['enabled' => true, 'clause' => 'is_true', 'value' => null],
                ],
            ],
        ],
    ])->assertOk();

    expect(array_column(array_slice(csvRows($explicit->streamedContent()), 1), 0))->toBe(['3', '1'])
        ->and(array_column(array_slice(csvRows($matching->streamedContent()), 1), 0))->toBe(['3']);
});

it('supports all-matching UUID selections with exclusions', function () {
    $response = $this->post(exportEndpoint(new ExportUuidTopicsTable, 'selected'), [
        'selection' => [
            'all' => true,
            'keys' => [],
            'except' => ['topic-b'],
            'table' => 'export_uuid_topics',
            'state' => ['sort' => 'uuid', 'search' => 'a', 'filters' => []],
        ],
    ])->assertOk();

    expect(array_column(array_slice(csvRows($response->streamedContent()), 1), 0))
        ->toBe(['topic-a', 'topic-c']);
});

it('rejects missing selections, unauthorized exports, and tampered references', function () {
    $table = new ExportTopicsTable;
    $this->postJson(exportEndpoint($table, 'selected'), ['state' => []])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('selection');

    $allEndpoint = exportEndpoint($table, 'all');
    $tampered = str_replace('/all?', '/unauthorized?', $allEndpoint);
    $this->post($tampered, ['state' => []])->assertForbidden();

    $unauthorized = URL::signedRoute('inertia-table.execute-export', [
        'table' => TableReference::encode(ExportTopicsTable::class),
        'export' => 'unauthorized',
    ], absolute: false);
    $this->post($unauthorized, ['state' => []])->assertForbidden();
});

it('returns a clear validation error when the optional Laravel Excel adapter is unavailable', function () {
    $this->postJson(exportEndpoint(new ExportTopicsTable, 'xlsx'), ['state' => []])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('export')
        ->assertJsonPath('errors.export.0', 'Install maatwebsite/excel before using XLSX or PDF table exports.');
});

it('rejects summary rows for exporters that do not support them', function () {
    $this->postJson(exportEndpoint(new SummaryExportTopicsTable, 'summary-xlsx'), ['state' => []])
        ->assertUnprocessable()
        ->assertJsonPath('errors.export.0', 'Summary rows are currently supported only by the native CSV exporter.');
});

it('streams a large CSV query without materializing the entire result collection', function () {
    $rows = [];

    for ($index = 0; $index < 1200; $index++) {
        $rows[] = [
            'name' => "Topic {$index}",
            'active' => false,
            'published_at' => null,
            'note' => null,
            'secret' => 'bulk',
        ];
    }

    foreach (array_chunk($rows, 200) as $chunk) {
        ExportTopicRecord::query()->insert($chunk);
    }

    $response = $this->post(exportEndpoint(new ExportTopicsTable, 'all'), ['state' => []])->assertOk();

    expect(csvRows($response->streamedContent()))->toHaveCount(1204);
});
