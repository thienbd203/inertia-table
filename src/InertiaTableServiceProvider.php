<?php

namespace Musing\InertiaTable;

use Musing\InertiaTable\Commands\MakeTableCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class InertiaTableServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('inertia-table')
            ->hasConfigFile()
            ->hasTranslations()
            ->hasMigration('create_table_views_table')
            ->hasCommand(MakeTableCommand::class)
            ->hasRoute('web');
    }
}
