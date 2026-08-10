<?php

namespace Toolbelt\InertiaTable;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Toolbelt\InertiaTable\Commands\InertiaTableCommand;

class InertiaTableServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('inertia-table')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_inertia_table_table')
            ->hasCommand(InertiaTableCommand::class);
    }
}
