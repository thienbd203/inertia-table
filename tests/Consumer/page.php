<?php

// A fresh SQLite memory database per request; never use host DB_* settings.
require dirname(__DIR__, 2).'/vendor/autoload.php';
require __DIR__.'/TopicsTable.php';

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\ServiceProvider;
use Musing\InertiaTable\InertiaTableServiceProvider;
use Musing\InertiaTable\Tests\Consumer\Topic;
use Musing\InertiaTable\Tests\Consumer\TopicsTable;
use Orchestra\Testbench\Foundation\Application;

$app = Application::create(options: [
    'providers' => [ServiceProvider::class, InertiaTableServiceProvider::class],
]);
$app['config']->set('database.default', 'consumer');
$app['config']->set('database.connections.consumer', [
    'driver' => 'sqlite',
    'database' => ':memory:',
    'prefix' => '',
]);
$app['config']->set('inertia.ssr.enabled', false);
Schema::create('topics', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('status');
    $table->timestamps();
});
Topic::query()->insert([
    ['id' => 1, 'name' => 'Alpha', 'status' => 'published', 'created_at' => '2026-01-01 12:00:00', 'updated_at' => '2026-01-01 12:00:00'],
    ['id' => 2, 'name' => 'Beta', 'status' => 'draft', 'created_at' => '2026-01-02 12:00:00', 'updated_at' => '2026-01-02 12:00:00'],
    ['id' => 3, 'name' => 'Gamma', 'status' => 'published', 'created_at' => '2026-01-03 12:00:00', 'updated_at' => '2026-01-03 12:00:00'],
]);

$input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
$request = Request::create($input['url'] ?? '/topics');
$request->headers->set('X-Inertia', 'true');
foreach (['X-Inertia-Partial-Component', 'X-Inertia-Partial-Data', 'X-Musing-Inertia-Table-Lazy-Filters'] as $header) {
    if (isset($input['headers'][strtolower($header)])) {
        $request->headers->set($header, $input['headers'][strtolower($header)]);
    }
}
$app->instance('request', $request);
$app['router']->get('/topics', fn () => Inertia::render('Topics/Index', [
    'topics' => TopicsTable::make(),
]));
$app['router']->get('/headless', fn () => Inertia::render('Topics/Headless', [
    'topics' => TopicsTable::make(),
]));

// Laravel emits the real page resource; the Node fixture renders this with Vue SSR.
require __DIR__.'/multiple-tables.php';

$response = $app['router']->dispatch($request);
if ($response->getStatusCode() !== 200) {
    throw new RuntimeException('Consumer route did not return HTTP 200.');
}
echo $response->getContent();
