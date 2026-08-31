<?php

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Musing\InertiaTable\Columns\ActionColumn;
use Musing\InertiaTable\Columns\BooleanColumn;
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
            NumberColumn::make('id', 'ID')->sortable(),
            TextColumn::make('name', 'Name')
                ->searchable()
                ->exportAs(fn (string $value, ExportTopicRecord $topic) => "{$value} #{$topic->getKey()}"),
            BooleanColumn::make('active', 'Active'),
            DateColumn::make('published_at', 'Published')->format('Y-m-d'),
            TextColumn::make('note', 'Note'),
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

it('streams a large CSV query without materializing a result collection', function () {
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
