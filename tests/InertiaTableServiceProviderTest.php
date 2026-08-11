<?php

it('merges the package default configuration without a published config file', function () {
    expect(config('inertia-table.per_page'))->toBe(25)
        ->and(config('inertia-table.per_page_options'))->toBe([10, 25, 50, 100])
        ->and(config('inertia-table.debounce'))->toBe(300);
});

it('publishes its config file under the inertia-table-config tag', function () {
    $this->artisan('vendor:publish', ['--tag' => 'inertia-table-config', '--force' => true]);

    expect(file_exists(config_path('inertia-table.php')))->toBeTrue();
});
