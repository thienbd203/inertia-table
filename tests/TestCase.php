<?php

namespace Musing\InertiaTable\Tests;

use Illuminate\Support\Facades\Schema;
use Kirschbaum\PowerJoins\PowerJoinsServiceProvider;
use Musing\InertiaTable\InertiaTableServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::dropAllTables();
    }

    protected function getPackageProviders($app)
    {
        return [
            PowerJoinsServiceProvider::class,
            InertiaTableServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        $driver = getenv('DB_CONNECTION') ?: 'sqlite';
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        $app['config']->set('database.default', 'testing');
        $app['config']->set(
            'database.connections.testing',
            $this->databaseConfigurationFor($driver),
        );
    }

    /** @return array<string, mixed> */
    protected function databaseConfigurationFor(string $driver): array
    {
        if ($driver === 'mysql') {
            return [
                'driver' => 'mysql',
                'host' => getenv('DB_HOST') ?: '127.0.0.1',
                'port' => getenv('DB_PORT') ?: '3306',
                'database' => getenv('DB_DATABASE') ?: 'inertia_table',
                'username' => getenv('DB_USERNAME') ?: 'root',
                'password' => getenv('DB_PASSWORD') ?: '',
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
                'strict' => true,
            ];
        }

        if ($driver === 'pgsql') {
            return [
                'driver' => 'pgsql',
                'host' => getenv('DB_HOST') ?: '127.0.0.1',
                'port' => getenv('DB_PORT') ?: '5432',
                'database' => getenv('DB_DATABASE') ?: 'inertia_table',
                'username' => getenv('DB_USERNAME') ?: 'postgres',
                'password' => getenv('DB_PASSWORD') ?: 'postgres',
                'charset' => 'utf8',
                'prefix' => '',
                'schema' => 'public',
            ];
        }

        return [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ];
    }
}
