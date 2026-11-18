<?php

namespace Spark\Ember\Inspectors;

use Spark\Foundation\Application;

class AppInspector
{
    /** @return array<string, string> */
    public static function inspect(): array
    {
        $basePath = self::basePath();
        $app = Application::$app;

        $database = $app?->getConfig('database.default');
        $cache = $app?->getConfig('cache.default');
        $queue = $app?->getConfig('queue.default');
        $session = $app?->getConfig('session.default');

        return [
            'base_path' => $basePath,
            'tinycore_version' => Application::VERSION,
            'php_version' => PHP_VERSION,
            'db_driver' => (string) $app?->getConfig("database.connections.{$database}.driver", $database ?? 'unknown'),
            'cache_driver' => (string) $app?->getConfig("cache.connections.{$cache}.driver", $cache ?? 'unknown'),
            'queue_driver' => (string) $app?->getConfig("queue.connections.{$queue}.driver", $queue ?? 'unknown'),
            'session_driver' => (string) $app?->getConfig("session.connections.{$session}.driver", $session ?? 'unknown'),
            'frontend_stack' => self::detectFrontendStack($basePath),
        ];
    }

    public static function basePath(): string
    {
        return rtrim(Application::$app?->getPath() ?? (getcwd() ?: '.'), '/\\');
    }

    private static function detectFrontendStack(string $basePath): string
    {
        $stack = ['Blade'];
        $packagePath = "$basePath/package.json";
        $package = is_file($packagePath) ? json_decode(file_get_contents($packagePath), true) : [];
        $dependencies = [...$package['dependencies'] ?? [], ...$package['devDependencies'] ?? []];

        if (isset($dependencies['vite']) || is_file("$basePath/vite.config.js") || is_file("$basePath/vite.config.ts")) {
            $stack[] = 'Vite';
        }
        if (isset($dependencies['tailwindcss']) || is_file("$basePath/tailwind.config.js")) {
            $stack[] = 'Tailwind CSS';
        }
        if (isset($dependencies['@inertiajs/react']) || isset($dependencies['@inertiajs/vue3']) || isset($dependencies['@inertiajs/svelte'])) {
            $stack[] = 'Inertia.js';
        }
        if (isset($dependencies['alpinejs'])) {
            $stack[] = 'Alpine.js';
        }

        return implode(' + ', $stack);
    }
}
