<?php

namespace Spark\Ember\Inspectors;

use Spark\Foundation\Application;
use function defined;
use function is_array;

class AppInspector
{
    /**
     * Inspect the application environment and configuration.
     *
     * @return array<string, mixed>
     */
    public static function inspect(): array
    {
        $basePath = self::basePath();

        $composerData = self::readJson("$basePath/composer.json");
        $packageData = self::readJson("$basePath/package.json");

        $tinycoreVersion = self::detectTinycoreVersion($basePath, $composerData);
        $phpVersion = PHP_VERSION;

        $dbInfo = self::detectDatabase($basePath);
        $cacheDriver = self::detectCacheDriver($basePath);
        $queueDriver = self::detectQueueDriver($basePath);
        $sessionDriver = self::detectSessionDriver($basePath);
        $frontendStack = self::detectFrontendStack($basePath, $packageData);

        return [
            'base_path' => $basePath,
            'tinycore_version' => $tinycoreVersion,
            'php_version' => $phpVersion,
            'db_driver' => $dbInfo['driver'],
            'db_details' => $dbInfo['details'],
            'cache_driver' => $cacheDriver,
            'queue_driver' => $queueDriver,
            'session_driver' => $sessionDriver,
            'frontend_stack' => $frontendStack,
        ];
    }

    /**
     * Determine the base path of the project.
     */
    public static function basePath(): string
    {
        if (function_exists('root_dir')) {
            $root = root_dir();
            if ($root && is_dir($root)) {
                return rtrim($root, '/\\');
            }
        }

        if (class_exists(Application::class) && Application::$app !== null) {
            if (method_exists(Application::$app, 'getPath')) {
                $path = Application::$app->getPath();
                if ($path && is_dir($path)) {
                    return rtrim($path, '/\\');
                }
            }
        }

        return getcwd() ?: '.';
    }

    /**
     * Detect installed TinyCore version.
     */
    private static function detectTinycoreVersion(string $basePath, array $composerData): string
    {
        if (class_exists(Application::class) && defined(Application::class . '::VERSION')) {
            return Application::VERSION;
        }

        $installedJsonPath = "$basePath/vendor/composer/installed.json";
        if (is_file($installedJsonPath)) {
            $installed = self::readJson($installedJsonPath);
            $packages = $installed['packages'] ?? $installed;
            foreach ($packages as $pkg) {
                if (($pkg['name'] ?? '') === 'tinymvc/tinycore') {
                    return $pkg['version'] ?? '4.x';
                }
            }
        }

        return $composerData['require']['tinymvc/tinycore'] ?? '4.x';
    }

    /**
     * Detect database configuration.
     *
     * @return array{driver: string, details: string}
     */
    private static function detectDatabase(string $basePath): array
    {
        $default = 'sqlite';
        $details = 'database/sqlite.db';

        $env = self::parseEnv("$basePath/.env");
        if (!empty($env['DB_CONNECTION'])) {
            $default = $env['DB_CONNECTION'];
        }
        if ($default === 'sqlite') {
            $details = $env['DB_DATABASE'] ?? 'database/sqlite.db';
        } else {
            $host = $env['DB_HOST'] ?? '127.0.0.1';
            $db = $env['DB_DATABASE'] ?? 'spark';
            $details = "$host/$db";
        }

        return [
            'driver' => $default,
            'details' => $details,
        ];
    }

    /**
     * Detect cache driver.
     */
    private static function detectCacheDriver(string $basePath): string
    {
        return self::envValue($basePath, 'CACHE_STORE', 'file');
    }

    /**
     * Detect queue driver.
     */
    private static function detectQueueDriver(string $basePath): string
    {
        return self::envValue($basePath, 'QUEUE_CONNECTION', 'sync');
    }

    /**
     * Detect session driver.
     */
    private static function detectSessionDriver(string $basePath): string
    {
        return self::envValue($basePath, 'SESSION_DRIVER', 'file');
    }

    /**
     * Detect frontend stack.
     */
    private static function detectFrontendStack(string $basePath, array $packageData): string
    {
        $stack = ['Blade'];

        $deps = [...$packageData['dependencies'] ?? [], ...$packageData['devDependencies'] ?? []];

        if (is_file("$basePath/vite.config.js") || isset($deps['vite'])) {
            $stack[] = 'Vite';
        }

        if (isset($deps['tailwindcss']) || is_file("$basePath/tailwind.config.js")) {
            $stack[] = 'Tailwind CSS';
        }

        if (isset($deps['@inertiajs/react']) || isset($deps['@inertiajs/vue3'])) {
            $stack[] = 'Inertia.js';
        } elseif (isset($deps['alpinejs'])) {
            $stack[] = 'Alpine.js';
        }

        return implode(' + ', $stack);
    }

    /**
     * Read an env key directly if available.
     */
    private static function envValue(string $basePath, string $key, string $default): string
    {
        if (function_exists('env')) {
            $val = env($key);
            if ($val !== null && $val !== '') {
                return (string) $val;
            }
        }

        $env = self::parseEnv("$basePath/.env");
        return !empty($env[$key]) ? (string) $env[$key] : $default;
    }

    /**
     * Parse an environment file safely line by line.
     *
     * @return array<string, string>
     */
    private static function parseEnv(string $envFile): array
    {
        if (!is_file($envFile)) {
            return [];
        }

        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return [];
        }

        $data = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (str_contains($line, '=')) {
                [$key, $value] = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value, " \t\n\r\0\x0B\"'");
                $data[$key] = $value;
            }
        }

        return $data;
    }

    /**
     * Safely read and decode a JSON file.
     */
    private static function readJson(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }

        $content = file_get_contents($path);
        if ($content === false) {
            return [];
        }

        $data = json_decode($content, true);
        return is_array($data) ? $data : [];
    }
}
