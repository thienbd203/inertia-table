<?php

require_once __DIR__.'/Support/ContractTable.php';

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Musing\InertiaTable\Tests\Support\ContractTopicRecord;
use Musing\InertiaTable\Tests\Support\ContractTopicsCursorTable;
use Musing\InertiaTable\Tests\Support\ContractTopicsTable;

const URL_CONTRACT_CASES_PATH = __DIR__.'/Fixtures/url-contract-cases.json';

beforeEach(function () {
    Schema::create('contract_topics', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('status');
        $table->unsignedInteger('amount');
        $table->boolean('is_featured');
        $table->date('published_at');
    });
    $migration = require dirname(__DIR__).'/database/migrations/create_table_views_table.php.stub';
    $migration->up();

    ContractTopicRecord::query()->insert([
        [
            'name' => 'Alpha',
            'status' => 'open',
            'amount' => 10,
            'is_featured' => false,
            'published_at' => '2025-01-10',
        ],
        [
            'name' => 'Beta',
            'status' => 'closed',
            'amount' => 20,
            'is_featured' => true,
            'published_at' => '2025-02-20',
        ],
    ]);

    $table = new ContractTopicsTable;
    $views = $table->views();
    expect($views)->not->toBeNull();
    $view = $views->newQuery()->create($views->valuesFor(
        $table,
        Request::create('/contract-topics', 'GET'),
        'Saved layout',
        [
            'sort' => 'name',
            'filters' => [],
            'columns' => ['name' => true, 'amount' => true, 'status' => true],
            'pinnedColumns' => ['left' => ['amount'], 'right' => []],
            'columnOrder' => ['amount', 'name', 'status'],
            'columnWidths' => ['name' => 300, 'amount' => 200],
            'perPage' => 1,
        ],
    ));
    expect($view->getKey())->toBe(1);
});

function urlContractArtifactPath(): ?string
{
    $path = getenv('INERTIA_TABLE_URL_CONTRACT_INPUT');

    return is_string($path) && $path !== '' ? $path : null;
}

function urlContractCases(): array
{
    $contents = file_get_contents(URL_CONTRACT_CASES_PATH);

    if ($contents === false) {
        throw new LogicException('The URL contract case fixture is missing.');
    }

    $fixture = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);

    if (($fixture['schemaVersion'] ?? null) !== 1 || ! is_array($fixture['cases'] ?? null)) {
        throw new LogicException('The URL contract case fixture has an unsupported schema.');
    }

    return $fixture['cases'];
}

it('normalizes URLs emitted by the frontend contract test', function () {
    $artifactPath = urlContractArtifactPath();

    if ($artifactPath === null) {
        $this->markTestSkipped('Set INERTIA_TABLE_URL_CONTRACT_INPUT to run the frontend-to-PHP URL bridge.');
    }

    if (! is_file($artifactPath)) {
        throw new LogicException("The URL contract artifact [{$artifactPath}] is missing.");
    }

    $contents = file_get_contents($artifactPath);

    if ($contents === false) {
        throw new LogicException("The URL contract artifact [{$artifactPath}] cannot be read.");
    }

    $artifact = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);

    if (($artifact['schemaVersion'] ?? null) !== 1 || ! is_array($artifact['cases'] ?? null)) {
        throw new LogicException('The URL contract artifact has an unsupported schema.');
    }

    $urlsById = collect($artifact['cases'])->mapWithKeys(function (mixed $case): array {
        if (! is_array($case) || ! is_string($case['id'] ?? null) || ! is_string($case['url'] ?? null)) {
            throw new LogicException('The URL contract artifact contains an invalid case.');
        }

        return [$case['id'] => $case['url']];
    })->all();
    $cases = urlContractCases();

    expect(array_keys($urlsById))->toEqual(array_column($cases, 'id'));

    foreach ($cases as $case) {
        $id = $case['id'] ?? null;
        $resource = $case['resource'] ?? null;
        $expectedState = $case['expectedState'] ?? null;

        if (! is_string($id) || ! is_string($resource) || ! is_array($expectedState)) {
            throw new LogicException('The URL contract case is invalid.');
        }

        $request = Request::create($urlsById[$id], 'GET');
        $table = match ($resource) {
            'full' => new ContractTopicsTable,
            'cursor' => new ContractTopicsCursorTable,
            default => throw new LogicException("Unsupported URL contract resource [{$resource}]."),
        };
        $resolved = $table->resolve($request)->toArray();

        expect($resolved['state'])->toEqual($expectedState, $id);

        if ($id === 'full-layout-and-filters') {
            expect($request->query('host'))->toBe('kept')
                ->and(data_get($request->query(), 'table.authors.page'))->toBe('3');
        }
    }
});
