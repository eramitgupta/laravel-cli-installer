<?php

namespace LaravelCliInstaller\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;
use LaravelCliInstaller\Services\PermissionsCheckerService;
use LaravelCliInstaller\Services\RequirementsCheckerService;

use function Laravel\Prompts\password;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\multisearch;

class AppSetupCommand extends Command
{
    protected $signature = 'erag:app-setup';

    protected $description = 'Check PHP version & server requirements';

    public function __construct(protected RequirementsCheckerService $requirementsChecker, protected PermissionsCheckerService $permissionsChecker)
    {
        parent::__construct();
    }

    public function handle()
    {
        $this->info('🔍 Checking system requirements...');
        $this->newLine();

        // Check PHP version
        $phpSupportInfo = $this->requirementsChecker->checkPHPversion(
            config('install.min_php_version')
        );

        // Check server extensions
        $requirements = $this->requirementsChecker->check(
            config('install.requirements')
        );

        // PHP VERSION TABLE
        $this->info('📌 PHP Version Check');
        $this->table(
            ['Full Version', 'Current', 'Minimum Required', 'Supported'],
            [
                [
                    $phpSupportInfo['full'],
                    $phpSupportInfo['current'],
                    $phpSupportInfo['minimum'],
                    $phpSupportInfo['supported'] ? '✔ Yes' : '❌ No',
                ],
            ]
        );

        $this->newLine();

        $this->info('📌 PHP Extensions Check');

        // Header Row
        $header = array_map('strtoupper', array_keys($requirements['requirements']['php']));

        // Status Row
        $statusRow = array_map(function ($v) {
            return $v ? '✔' : '❌';
        }, array_values($requirements['requirements']['php']));

        // Print Horizontal Table
        $this->table(
            $header,
            [$statusRow]
        );

        $this->newLine();

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

        $this->table(
            ['Folder', 'Required Permission', 'Status'],
            $permissionRows
        );

        $this->newLine();

        // If errors exist, show them
        if (! empty($permissions['errors'])) {
            $this->error('❌ Permission Errors Found:');

            foreach ($permissions['errors'] as $err) {
                $this->error('- '.$err);
            }
        }

        $this->newLine();

        File::delete(base_path('.env'));

        $examplePath = base_path('.env.example');
        $envPath = base_path('.env');

        // Check if .env exists
        if (! file_exists($envPath)) {
            copy($examplePath, $envPath);
            $this->info('📄 .env file created from .env.example');
        }

        $this->newLine();

        $this->requirementsChecker->replaceEnvWithExample();

        $this->info('♻️ .env replaced with .env.example');

        $this->newLine();

        $envData = $this->askEnvValues();

        $this->requirementsChecker->updateEnv($envData);

        $this->newLine();

        $this->info('🔐 Generating application key...');

        $this->call('key:generate');

//        $this->call('migrate:fresh --seed');

    }

    protected function askEnvValues(): array
    {
        // App Name
        $appName = text(
            label: 'App Name',
            default: '',
            required: true
        );

        // App Environment
        $appEnv = select(
            label: 'App Environment',
            options: ['local', 'development', 'qa', 'production', 'other'],
            default: 'local'
        );

        // App Debug
        $appDebug = select(
            label: 'App Debug',
            options: ['true', 'false'],
            default: 'true'
        );

        $appUrl = text(
            label: 'App URL',
            default: 'https://',
            required: true
        );

        // Database Connection
        $dbConnection = select(
            label: 'Database Connection',
            options: ['mysql', 'sqlite', 'pgsql', 'sqlsrv'],
            default: 'mysql'
        );

        // DB Host
        $dbHost = text(
            label: 'Database Host',
            default: '127.0.0.1',
            required: true
        );

        // DB Port
        $dbPort = text(
            label: 'DB Port',
            default: $dbConnection === 'mysql' ? '3306' : '5432',
            required: true
        );

        $dbName = text(
            label: 'Database Name',
            default: '',
            required: true
        );

        // DB Username
        $dbUser = text(
            label: 'Database User Name',
            default: 'root',
            required: true
        );

        // DB Password
        $dbPassword = password(
            label: 'Database Password (Leave empty if no password)'
        );


        // Database Test Loop
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
            $dbHost = text(
                label: 'Database Host',
                default: $dbHost,
                required: true
            );

            $dbPort = text(
                label: 'DB Port',
                default: $dbPort,
                required: true
            );

            $dbName = text(
                label: 'Database Name',
                default: $dbName,
                required: true
            );

            $dbUser = text(
                label: 'Database User Name',
                default: $dbUser,
                required: true
            );

            $dbPassword = password(
                label: 'Database Password (Leave empty if no password)'
            );
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


    protected function askAccountForm(): array
    {
        $fields = config('install.account');
        $data = [];

        foreach ($fields as $field) {

            $type = $field['type'];
            $key = $field['key'];
            $label = $field['label'];
            $required = $field['required'] ?? false;
            $rules = $field['rules'] ?? ($required ? 'required' : 'nullable');

            $value = null;

            while (true) {

                // TEXT / EMAIL / TEXTAREA
                if (in_array($type, ['text', 'email', 'textarea'])) {
                    $value = text(label: $label, required: $required);
                }

                // PASSWORD
                elseif ($type === 'password') {
                    $value = password(label: $label, required: $required);
                }

                // CONFIRM PASSWORD
                elseif ($type === 'confirm') {
                    $matchKey = $field['match'];

                    while (true) {
                        $confirmValue = password(label: $label, required: $required);

                        if (!isset($data[$matchKey])) {
                            $this->error('❌ Password must be entered before confirmation.');
                            continue;
                        }

                        if ($confirmValue !== $data[$matchKey]) {
                            $this->error('❌ The password confirmation must match the password.');
                            $this->newLine();
                            continue;
                        }

                        $value = $confirmValue;
                        break;
                    }
                }



                // SELECT
                elseif ($type === 'select') {
                    $value = select(
                        label: $label,
                        options: $field['options'],
                        required: $required
                    );
                }

                // MULTISELECT
                elseif ($type === 'multiselect') {
                    $value = multiselect(
                        label: $label,
                        options: $field['options'],
                        scroll: 10
                    );
                }

                // MULTISEARCH
                elseif ($type === 'multisearch') {

                    $options = $field['options'];

                    $value = multisearch(
                        label: $label,
                        options: function (string $search) use ($options): array {
                            if ($search === '') return $options;

                            return array_filter($options, function ($item) use ($search) {
                                return stripos($item, $search) !== false;
                            });
                        },
                        placeholder: 'Search...',
                        scroll: 10
                    );
                }

                // VALIDATE FIELD
                $validator = Validator::make(
                    [$key => $value],
                    [$key => $rules]
                );

                if ($validator->fails()) {
                    $this->error(' ❌ ' . $validator->errors()->first($key));
                    continue; // retry
                }

                break; // success, exit loop
            }

            $data[$key] = $value;
        }

        return $data;
    }


    protected function createAdminAccount()
    {

        $this->newLine();

        $this->info('👤 Creating admin account...');

        while (true) {

            $accountData = $this->askAccountForm();

            $this->newLine();
            $this->info('👤 Saving admin user...');

            $userModel = config('auth.providers.users.model');

            try {

                $userModel::query()->create($accountData);

                $this->newLine();
                $this->info("✅ Account created successfully!");
                break; // EXIT LOOP SUCCESS

            } catch (\Throwable $e) {

                $this->newLine();
                $this->error('❌ Failed to create account!');
                $this->error('Reason: ' . $e->getMessage());
                $this->newLine();

                $retry = select(
                    label: 'Do you want to retry account creation?',
                    options: ['Yes', 'No']
                );

                if ($retry === 'No') {
                    $this->info('⛔ Account creation skipped.');
                    break;
                }

                $this->newLine();
                $this->info('🔁 Retrying account creation...');
                $this->newLine();
            }
        }
    }
}
