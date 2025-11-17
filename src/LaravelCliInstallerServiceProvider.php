<?php

namespace LaravelCliInstaller;

use Illuminate\Support\ServiceProvider;
use LaravelCliInstaller\Commands\AppInstallCommand;
use LaravelCliInstaller\Commands\AppSetupCommand;

class LaravelCliInstallerServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->commands([
            AppInstallCommand::class,
            AppSetupCommand::class,
        ]);

        $this->publishes([
            __DIR__.'/../config/install.php' => config_path('install.php'),
        ], 'erag:publish-cli--installer-config');
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void {}
}
