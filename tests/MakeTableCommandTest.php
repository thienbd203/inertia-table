<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

function withTemporaryAppPath(Closure $callback): void
{
    $application = app();
    $application->getNamespace();
    $originalPath = app_path();
    $temporaryPath = sys_get_temp_dir().'/inertia-table-generator-'.Str::uuid();
    $application->useAppPath($temporaryPath.'/app');
    File::ensureDirectoryExists(app_path('Models'));

    try {
        $callback($temporaryPath);
    } finally {
        $application->useAppPath($originalPath);
        File::deleteDirectory($temporaryPath);
    }
}

it('generates a table class and infers its Eloquent model', function () {
    withTemporaryAppPath(function () {
        $this->artisan('make:inertia-table', ['name' => 'Users'])
            ->expectsOutputToContain('Inertia table')
            ->assertSuccessful();

        $generated = File::get(app_path('Tables/Users.php'));

        expect($generated)
            ->toContain('namespace App\Tables;')
            ->toContain('use App\Models\User;')
            ->toContain('final class Users extends Table')
            ->toContain('return User::query();')
            ->toContain("TextColumn::make('id', 'ID')->sortable()->toggleable(false)");
    });
});

it('accepts nested table and model names and protects existing files', function () {
    withTemporaryAppPath(function () {
        $arguments = [
            'name' => 'Admin/TopicsTable',
            '--model' => 'Content/Topic',
        ];

        $this->artisan('make:inertia-table', $arguments)->assertSuccessful();

        $path = app_path('Tables/Admin/TopicsTable.php');
        $generated = File::get($path);

        expect($generated)
            ->toContain('namespace App\Tables\Admin;')
            ->toContain('use App\Models\Content\Topic;')
            ->toContain('final class TopicsTable extends Table');

        $this->artisan('make:inertia-table', $arguments)
            ->expectsOutputToContain('already exists');

        expect(File::get($path))->toBe($generated);

        File::put($path, '<?php // application edit');
        $this->artisan('make:inertia-table', [
            ...$arguments,
            '--force' => true,
        ])->assertSuccessful();

        expect(File::get($path))
            ->not->toBe('<?php // application edit')
            ->toContain('final class TopicsTable extends Table');
    });
});
