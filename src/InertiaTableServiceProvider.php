<?php

namespace Musing\InertiaTable;

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
            ->hasRoute('web');
    }
}
