<?php

namespace LaravelCliInstaller\Commands;

use Illuminate\Console\Command;

use function Laravel\Prompts\select;

class AppInstallCommand extends Command
{
    protected $signature = 'erag:app-install';

    protected $description = 'Publish config and initialize erag Laravel App Installer';

    public function handle()
    {
        $this->info('🚀 Installing ERAG Laravel CLI Installer...');

        $this->call('vendor:publish', [
            '--tag' => 'erag:publish-cli--installer-config',
            '--force' => true,
        ]);

        $this->info('✅ Config file published: config/install.php');

        $this->line('');
        $this->info('📌 NEXT STEP: Update your installation settings in: config/install.php');
        $this->line('');

        $this->info('➡ After editing the config, run:');
        $this->comment('php artisan erag:app-setup');

        $choice = select(
            label: 'Have you set up install.php? Do you want to run app-setup now?',
            options: ['Yes', 'No']
        );

        if ($choice === 'Yes') {
            $this->info('⚙ Running: php artisan erag:app-setup');
            $this->call('erag:app-setup');
        } else {
            $this->info('❗ Okay, setup aborted. Run it later using: php artisan erag:app-setup');
        }

    }
}
