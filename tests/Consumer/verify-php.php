<?php

require dirname(__DIR__, 2).'/vendor/autoload.php';
require __DIR__.'/TopicsTable.php';

use App\Models\ConsumerTopic;
use App\Tables\Consumer\TopicsTable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Musing\InertiaTable\InertiaTableServiceProvider;
use Orchestra\Testbench\Foundation\Application;

// No explicit providers: Testbench discovers this checkout's composer metadata.
$app = Application::create();
if (! $app->getProvider(InertiaTableServiceProvider::class)) {
    throw new RuntimeException('The package provider was not discovered.');
}
if ($app['config']->get('inertia-table.per_page') !== 25) {
    throw new RuntimeException('The package configuration was not loaded.');
}

$app->getNamespace();
$originalPath = $app->path();
$temporaryPath = sys_get_temp_dir().'/inertia-table-consumer-php-'.Str::uuid();
$files = new Filesystem;
$files->ensureDirectoryExists($temporaryPath.'/app/Models');
$app->useAppPath($temporaryPath.'/app');

try {
    $files->put($app->path('Models/ConsumerTopic.php'), '<?php namespace App\\Models; class ConsumerTopic extends \\Musing\\InertiaTable\\Tests\\Consumer\\Topic {}');
    require $app->path('Models/ConsumerTopic.php');
    $kernel = $app->make(Kernel::class);
    $status = $kernel->call('make:inertia-table', [
        'name' => 'Consumer/TopicsTable',
        '--model' => 'ConsumerTopic',
        '--no-interaction' => true,
    ]);
    if ($status !== 0) {
        throw new RuntimeException($kernel->output());
    }
    $path = $app->path('Tables/Consumer/TopicsTable.php');
    require $path;
    $table = new TopicsTable;
    $app['config']->set('database.default', 'consumer');
    $app['config']->set('database.connections.consumer', [
        'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
    ]);
    Schema::create('topics', function (Blueprint $blueprint) {
        $blueprint->id();
    });
    ConsumerTopic::query()->insert(['id' => 7]);
    if ($table->query()->getModel()->getTable() !== 'topics') {
        throw new RuntimeException('The generated table targets the wrong model.');
    }
    if ($table->columns()[0]->toArray()['attribute'] !== 'id') {
        throw new RuntimeException('The generated table columns are invalid.');
    }
    if ($table->toArray()['results']['data'][0]['id'] !== 7) {
        throw new RuntimeException('The generated table did not resolve its row.');
    }
    echo "PHP checkout discovery, config and generated table verified.\n";
} finally {
    $app->useAppPath($originalPath);
    $files->deleteDirectory($temporaryPath);
}
