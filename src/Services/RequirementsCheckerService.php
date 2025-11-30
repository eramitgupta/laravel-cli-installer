<?php

namespace LaravelCliInstaller\Services;

use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class RequirementsCheckerService
{
    private string $_minPhpVersion = '8.0.0';

    /**
     * Check for the server requirements.
     */
    public function check(array $requirements): array
    {
        $results = [];

        foreach ($requirements as $type => $requirement) {
            switch ($type) {
                // check php requirements
                case 'php':
                    foreach ($requirements[$type] as $requirement) {
                        $results['requirements'][$type][$requirement] = true;

                        if (! extension_loaded($requirement)) {
                            $results['requirements'][$type][$requirement] = false;

                            $results['errors'] = true;
                        }
                    }
                    break;
                    // check apache requirements
                case 'apache':
                    foreach ($requirements[$type] as $requirement) {
                        // if function doesn't exist we can't check apache modules
                        if (function_exists('apache_get_modules')) {
                            $results['requirements'][$type][$requirement] = true;

                            if (! in_array($requirement, apache_get_modules())) {
                                $results['requirements'][$type][$requirement] = false;

                                $results['errors'] = true;
                            }
                        }
                    }
                    break;
            }
        }

        return $results;
    }

    /**
     * Check PHP version requirement.
     */
    public function checkPHPVersion(?string $minPhpVersion = null): array
    {
        $minVersionPhp = $minPhpVersion;
        $currentPhpVersion = $this->getPhpVersionInfo();
        $supported = false;

        if ($minPhpVersion == null) {
            $minVersionPhp = $this->getMinPhpVersion();
        }

        if (version_compare($currentPhpVersion['version'], $minVersionPhp) >= 0) {
            $supported = true;
        }

        return [
            'full' => $currentPhpVersion['full'],
            'current' => $currentPhpVersion['version'],
            'minimum' => $minVersionPhp,
            'supported' => $supported,
        ];
    }

    /**
     * Get current Php version information.
     */
    private static function getPhpVersionInfo(): array
    {
        $currentVersionFull = PHP_VERSION;
        preg_match("#^\d+(\.\d+)*#", $currentVersionFull, $filtered);
        $currentVersion = $filtered[0];

        return [
            'full' => $currentVersionFull,
            'version' => $currentVersion,
        ];
    }

    /**
     * Get minimum PHP version ID.
     *
     * @return string _minPhpVersion
     */
    protected function getMinPhpVersion(): string
    {
        return $this->_minPhpVersion;
    }

    public function replaceEnvWithExample()
    {
        $examplePath = base_path('.env.example');
        $envPath = base_path('.env');

        // Fresh Copy
        if (File::exists($envPath)) {
            File::delete($envPath);
        }

        sleep(1);

        copy($examplePath, $envPath);
    }

    public function updateEnv(array $userData)
    {
        $envPath = base_path('.env');

        $lines = file($envPath, FILE_IGNORE_NEW_LINES);
        $newLines = [];
        $added = [];

        foreach ($lines as $line) {

            $trim = trim($line);

            if ($trim === '' || str_starts_with($trim, '#')) {

                // commented key
                if (str_starts_with($trim, '#') && str_contains($trim, '=')) {

                    $commentKey = trim(substr($trim, 1));
                    [$key, $val] = explode('=', $commentKey, 2);

                    $newLines[] = $line;

                    if (array_key_exists($key, $userData) && ! isset($added[$key])) {
                        $newLines[] = $key.'="'.$userData[$key].'"';
                        $added[$key] = true;
                    }

                    continue;
                }

                $newLines[] = $line;

                continue;
            }

            if (str_contains($line, '=')) {

                [$key, $value] = explode('=', $line, 2);

                if (array_key_exists($key, $userData)) {

                    if ($key === 'APP_KEY') {
                        $newLines[] = $key.'=';
                    } else {
                        $newLines[] = $key.'="'.$userData[$key].'"';
                    }

                    $added[$key] = true;

                    continue;
                }
            }

            $newLines[] = $line;
        }

        file_put_contents($envPath, implode("\n", $newLines));

        sleep(1);
    }

    public function checkDatabaseConnection($dbConnection, $dbHost, $dbPort, $dbName, $dbUser, $dbPassword): bool
    {
        $settings = config("database.connections.$dbConnection");

        config([
            'database.default' => $dbConnection,
            "database.connections.$dbConnection" => array_merge($settings, [
                'driver' => $dbConnection,
                'host' => $dbHost,
                'port' => $dbPort,
                'database' => $dbName,
                'username' => $dbUser,
                'password' => $dbPassword,
            ]),
        ]);

        DB::purge($dbConnection);

        try {
            DB::connection($dbConnection)->getPdo();

            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}
