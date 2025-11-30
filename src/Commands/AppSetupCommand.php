<?php

namespace LaravelCliInstaller\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use LaravelCliInstaller\Services\PermissionsCheckerService;
use LaravelCliInstaller\Services\RequirementsCheckerService;

use function Laravel\Prompts\multisearch;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\password;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class AppSetupCommand extends Command
{
    protected $signature = 'erag:app-setup';

    protected $description = 'Run ERAG installer: system checks, env, key, admin account';

    public function __construct(
        protected RequirementsCheckerService $requirementsChecker,
        protected PermissionsCheckerService $permissionsChecker
    ) {
        parent::__construct();
    }

    /**
     * Main installer flow — uses runStep() to get retry/exit behaviour per step.
     */
    public function handle(): int
    {
        $this->info('🚀 Starting ERAG Installer...');
        $this->newLine();

        // STEP 1: System checks (php version, extensions, permissions)
        $this->runStep(fn () => $this->systemCheck(), 'System Check');

        // STEP 2: Replace .env with example and write env values
        $this->runStep(fn () => $this->envSetup(), 'Environment Setup');

        // STEP 3: Generate APP KEY
        $this->runStep(fn () => $this->generateAppKey(), 'Application Key Generation');

        // STEP 4: Create admin account
        $this->runStep(fn () => $this->createAdminAccount(), 'Admin Account Creation');

        $this->newLine();
        $this->info('🎉 Installation completed successfully!');

        return self::SUCCESS;
    }

    protected function runStep(callable $step, string $stepName)
    {
        try {
            return $step();
        } catch (\Throwable $e) {

            $this->newLine();
            $this->error("❌ {$stepName} Failed!");
            $this->error('Reason: '.$e->getMessage());
            $this->newLine();

            $this->comment('🔧 Fix the issue, then run:');
            $this->info('php artisan erag:app-setup');

            $this->newLine();
            $this->error('⛔ Installer stopped.');
            exit(1);
        }
    }

    /**
     * System check step logic (throws if critical failures).
     *
     * @throws \Exception
     */
    protected function systemCheck(): void
    {
        $this->info('🔍 Checking system requirements...');
        $this->newLine();

        /*
        |--------------------------------------------------------------------------
        | 1) CHECK PHP VERSION
        |--------------------------------------------------------------------------
        */
        $phpSupportInfo = $this->requirementsChecker->checkPHPVersion(
            config('install.min_php_version')
        );

        $this->info('📌 PHP Version Check');
        $this->table(
            ['Full Version', 'Current', 'Minimum Required', 'Supported'],
            [[
                $phpSupportInfo['full'],
                $phpSupportInfo['current'],
                $phpSupportInfo['minimum'],
                $phpSupportInfo['supported'] ? '✔ Yes' : '❌ No',
            ]]
        );

        if (! $phpSupportInfo['supported']) {
            throw new \Exception("PHP {$phpSupportInfo['minimum']} or greater required.");
        }

        $this->newLine();

        /*
        |--------------------------------------------------------------------------
        | 2) CHECK PHP EXTENSIONS
        |--------------------------------------------------------------------------
        */
        $requirements = $this->requirementsChecker->check(
            config('install.requirements')
        );

        $this->info('📌 PHP Extensions Check');

        $header = array_map('strtoupper', array_keys($requirements['requirements']['php'] ?? []));
        $statusRow = array_map(fn ($v) => $v ? '✔' : '❌', array_values($requirements['requirements']['php'] ?? []));

        $this->table($header, [$statusRow]);

        // Detect missing extensions
        $missingExtensions = [];

        foreach ($requirements['requirements']['php'] ?? [] as $ext => $enabled) {
            if (! $enabled) {
                $missingExtensions[] = strtoupper($ext);
            }
        }

        if (! empty($missingExtensions)) {

            $this->newLine();
            $this->error('❌ Missing PHP Extensions:');

            foreach ($missingExtensions as $ext) {
                $this->error(" - {$ext}");
            }

            throw new \Exception(
                'Missing PHP extensions: '.implode(', ', $missingExtensions)
            );
        }

        $this->newLine();

        /*
        |--------------------------------------------------------------------------
        | 3) CHECK DIRECTORY PERMISSIONS
        |--------------------------------------------------------------------------
        */
        $this->info('📌 Directory Permissions Check');

        $permissions = $this->permissionsChecker->check(
            config('install.permissions')
        );

        $permissionRows = [];
        foreach ($permissions['permissions'] as $perm) {
            $permissionRows[] = [
                $perm['folder'],
                $perm['permission'],
                $perm['isSet'] ? '✔ OK' : '❌ Failed',
            ];
        }

        $this->table(['Folder', 'Required Permission', 'Status'], $permissionRows);

        if (! empty($permissions['errors'])) {

            $this->newLine();
            $this->error('❌ Permission Errors Found:');

            foreach ($permissions['permissions'] as $perm) {
                if (! $perm['isSet']) {
                    $this->error(" - {$perm['folder']} must be {$perm['permission']}");
                }
            }

            throw new \Exception('Directory permission checks failed.');
        }

        /*
        |--------------------------------------------------------------------------
        | ALL CHECKS PASSED
        |--------------------------------------------------------------------------
        */
        $this->newLine();
        $this->info('✅ System checks passed.');
    }

    /**
     * Env setup step: create/replace .env, ask values and update.
     *
     * @return array env data collected
     *
     * @throws \Exception
     */
    protected function envSetup(): array
    {
        $this->info('📄 Preparing .env file from .env.example');

        // Replace .env with .env.example (fresh)
        $this->requirementsChecker->replaceEnvWithExample();
        $this->info('♻️ .env replaced with .env.example');
        $this->newLine();

        // Collect env values interactively
        $envData = $this->askEnvValues();

        // Update .env file
        try {
            $this->requirementsChecker->updateEnv($envData);
        } catch (\Throwable $e) {
            throw new \Exception('Failed to update .env: '.$e->getMessage());
        }

        // Test DB connection now (we already prompt for DB in askEnvValues and have DB test loop there)
        $this->newLine();
        $this->info('✅ .env updated.');

        return $envData;
    }

    /**
     * Generate application key.
     */
    protected function generateAppKey(): void
    {
        $this->info('🔐 Generating application key...');
        $this->call('key:generate', ['--force' => true]);
        $this->newLine();
        $this->info('✅ Application key generated.');
    }

    /**
     * Admin creation step. Throws on failure (runStep will catch and offer retry).
     */
    protected function createAdminAccount(): void
    {
        $this->newLine();
        $this->info('👤 Creating account...');

        $accountData = $this->askAccountForm();

        $this->newLine();
        $this->info('👤 Saving user...');

        $userModel = config('auth.providers.users.model');

        // Prepare data for mass assignment: ensure password hashed, convert arrays to json if needed
        $payload = $accountData;
        if (isset($payload['password'])) {
            $payload['password'] = bcrypt($payload['password']);
        }
        if (isset($payload['modules']) && is_array($payload['modules'])) {
            // if users table expects json string
            $payload['modules'] = json_encode(array_values($payload['modules']));
        }
        if (isset($payload['tags']) && is_array($payload['tags'])) {
            $payload['tags'] = json_encode(array_values($payload['tags']));
        }

        // Try create; throw on any error so runStep will ask retry
        try {
            $user = $userModel::query()->create($payload);

            // If spatie present and role provided, assign it
            if (isset($accountData['role']) && method_exists($user, 'assignRole')) {
                $user->assignRole($accountData['role']);
            }

            $this->newLine();
            $this->info('✅ Account created successfully!');
            $this->info("➡ Email: {$user->email}");
        } catch (\Throwable $e) {
            // give helpful tips for common errors
            $msg = $e->getMessage();
            if (Str::contains($msg, 'Fillable') || Str::contains($msg, 'MassAssignmentException')) {
                throw new \Exception('Mass assignment error — add required keys to $fillable on your User model: '.implode(', ', array_keys($accountData)));
            }
            if (Str::contains($msg, 'Duplicate') || Str::contains($msg, 'Integrity constraint')) {
                throw new \Exception('Database constraint error (maybe duplicate email). '.$msg);
            }
            throw $e; // rethrow to trigger retry flow
        }
    }

    /**
     * Prompt for environment values (app + db). Includes DB connection test loop.
     */
    protected function askEnvValues(): array
    {
        // App Name
        $appName = text(label: 'App Name', default: '', required: true);

        // App Environment
        $appEnv = select(label: 'App Environment', options: ['local', 'development', 'qa', 'production', 'other'], default: 'local');

        // App Debug
        $appDebug = select(label: 'App Debug', options: ['true', 'false'], default: 'true');

        // App URL
        $appUrl = text(label: 'App URL', default: 'https://', required: true);

        // Database Connection
        $dbConnection = select(label: 'Database Connection', options: ['mysql', 'sqlite', 'pgsql', 'sqlsrv'], default: 'mysql');

        // DB Host
        $dbHost = text(label: 'Database Host', default: '127.0.0.1', required: true);

        // DB Port
        $dbPort = text(label: 'DB Port', default: $dbConnection === 'mysql' ? '3306' : '5432', required: true);

        // DB Name
        $dbName = text(label: 'Database Name', default: '', required: true);

        // DB Username
        $dbUser = text(label: 'Database User Name', default: 'root', required: true);

        // DB Password
        $dbPassword = password(label: 'Database Password (Leave empty if no password)');

        // Database Test Loop (requirementsChecker handles runtime config override and connection test)
        while (! $this->requirementsChecker->checkDatabaseConnection(
            $dbConnection,
            $dbHost,
            $dbPort,
            $dbName,
            $dbUser,
            $dbPassword
        )) {
            $this->error('❌ Database connection failed! Please check your credentials.');
            $this->newLine();
            $this->info('🔁 Please re-enter your database details.');

            // Ask again
            $dbHost = text(label: 'Database Host', default: $dbHost, required: true);
            $dbPort = text(label: 'DB Port', default: $dbPort, required: true);
            $dbName = text(label: 'Database Name', default: $dbName, required: true);
            $dbUser = text(label: 'Database User Name', default: $dbUser, required: true);
            $dbPassword = password(label: 'Database Password (Leave empty if no password)');
        }

        $this->info('✅ Database connection successful!');

        return [
            'APP_NAME' => $appName,
            'APP_ENV' => $appEnv,
            'APP_DEBUG' => $appDebug,
            'APP_URL' => $appUrl,
            'DB_CONNECTION' => $dbConnection,
            'DB_HOST' => $dbHost,
            'DB_PORT' => $dbPort,
            'DB_DATABASE' => $dbName,
            'DB_USERNAME' => $dbUser,
            'DB_PASSWORD' => $dbPassword,
        ];
    }

    /**
     * Dynamic account form builder with validation + confirm password support.
     */
    protected function askAccountForm(): array
    {
        $fields = config('install.account') ?: [];
        $data = [];

        foreach ($fields as $field) {
            if (! is_array($field)) {
                // skip invalid field definitions
                $this->error('⚠️ Invalid account field definition in config/install.php');

                continue;
            }

            $type = $field['type'];
            $key = $field['key'];
            $label = $field['label'] ?? ucfirst(str_replace('_', ' ', $key));
            $required = $field['required'] ?? false;
            $rules = $field['rules'] ?? ($required ? 'required' : 'nullable');

            $value = null;

            // keep asking this field until validation passes (or confirm matches)
            while (true) {
                // TEXT / EMAIL / TEXTAREA
                if (in_array($type, ['text', 'email', 'textarea'])) {
                    $value = text(label: $label, required: $required);
                }

                // PASSWORD
                elseif ($type === 'password') {
                    $value = password(label: $label, required: $required);
                }

                // CONFIRM (special behaviour)
                elseif ($type === 'confirm') {
                    $matchKey = $field['match'] ?? null;

                    if (! $matchKey || ! isset($data[$matchKey])) {
                        $this->error("❌ Misconfigured confirm field or matching field '{$matchKey}' missing. Ensure password field comes before confirmation in config.");
                        // re-ask the password field if missing
                        $data[$matchKey] = password(label: 'Password (re-enter)', required: true);
                    }

                    // loop until match
                    while (true) {
                        $confirmVal = password(label: $label, required: $required);
                        if ($confirmVal !== $data[$matchKey]) {
                            $this->error('❌ Passwords do not match. Try again.');
                            $this->newLine();

                            continue;
                        }
                        $value = $confirmVal;
                        break;
                    }
                }

                // SELECT
                elseif ($type === 'select') {
                    $value = select(label: $label, options: $field['options'] ?? [], required: $required);
                }

                // MULTISELECT
                elseif ($type === 'multiselect') {
                    $value = multiselect(label: $label, options: $field['options'] ?? [], scroll: 10);
                }

                // MULTISEARCH
                elseif ($type === 'multisearch') {
                    $options = $field['options'] ?? [];
                    $value = multisearch(
                        label: $label,
                        options: function (string $search) use ($options): array {
                            if ($search === '') {
                                return $options;
                            }

                            return array_values(array_filter($options, fn ($item) => stripos($item, $search) !== false));
                        },
                        placeholder: 'Search & select...',
                        scroll: 10
                    );
                }

                // Validate this single field using Validator
                $validator = Validator::make([$key => $value], [$key => $rules]);

                if ($validator->fails()) {
                    $this->error(' ❌ '.$validator->errors()->first($key));

                    // retry asking this same field
                    continue;
                }

                // success for this field
                break;
            }

            $data[$key] = $value;
        }

        return $data;
    }
}
