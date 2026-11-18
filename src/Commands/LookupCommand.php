<?php

namespace Spark\Ember\Commands;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Spark\Console\Prompt;
use Spark\Ember\Inspectors\AppInspector;
use function count;

class LookupCommand
{
    /**
     * Handle the ember:lookup command.
     *
     * @param array<string, mixed> $args
     * @return int
     */
    public function handle(array $args): int
    {
        $query = $args['_args'][0] ?? $args['query'] ?? null;
        if (empty($query) || !is_string($query)) {
            Prompt::message('Please provide a search term: php spark ember:lookup <symbol>', 'warning');
            return 1;
        }

        $basePath = AppInspector::basePath();
        $sourcePath = $args['source'] ?? null;
        if (!$sourcePath) {
            $candidates = [
                "$basePath/vendor/tinymvc/tinycore/src",
                dirname($basePath) . '/tinycore/src',
                "$basePath/src",
            ];
            foreach ($candidates as $cand) {
                if (is_dir($cand)) {
                    $sourcePath = $cand;
                    break;
                }
            }
        }

        if (!$sourcePath || !is_dir($sourcePath)) {
            Prompt::message('TinyCore source directory not found.', 'danger');
            return 1;
        }

        $fileFilter = (string) ($args['file'] ?? '');
        $limit = isset($args['limit']) ? (int) $args['limit'] : 20;

        $results = $this->search($sourcePath, $query, $fileFilter, $limit);

        if (empty($results)) {
            Prompt::message("No declarations found matching '{$query}'.", 'info');
            return 0;
        }

        Prompt::section("Lookup results for '{$query}'");
        $rows = [];
        foreach ($results as $res) {
            $rows[] = [$res['type'], $res['signature'], $res['location']];
        }

        Prompt::table(['Type', 'Signature', 'File:Line'], $rows);

        return 0;
    }

    /**
     * Search declarations across PHP files.
     *
     * @return list<array{type: string, signature: string, location: string}>
     */
    private function search(string $dir, string $query, string $fileFilter, int $limit): array
    {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
        $matches = [];
        $pattern = '/' . preg_quote($query, '/') . '/i';

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $relativePath = str_replace($dir . DIRECTORY_SEPARATOR, '', $file->getPathname());
            if ($fileFilter !== '' && !str_contains($relativePath, $fileFilter)) {
                continue;
            }

            $content = file_get_contents($file->getPathname());
            if ($content === false || !preg_match($pattern, $content)) {
                continue;
            }

            $lines = explode("\n", $content);
            foreach ($lines as $lineNum => $line) {
                $trimmed = trim($line);

                if (preg_match('/^(?:final\s+|abstract\s+)?(?:public\s+|protected\s+)?function\s+(&?[a-zA-Z0-9_]+)\s*\((.*?)\)(?:\s*:\s*([^;{]+))?/', $trimmed, $m)) {
                    if (preg_match($pattern, $m[1])) {
                        $matches[] = [
                            'type' => 'Method',
                            'signature' => "function {$m[1]}(" . trim($m[2]) . ')' . (isset($m[3]) ? ': ' . trim($m[3]) : ''),
                            'location' => $relativePath . ':' . ($lineNum + 1),
                        ];
                    }
                } elseif (preg_match('/^(?:final\s+|abstract\s+)?(class|interface|trait|enum)\s+([a-zA-Z0-9_]+)/', $trimmed, $m)) {
                    if (preg_match($pattern, $m[2])) {
                        $matches[] = [
                            'type' => ucfirst($m[1]),
                            'signature' => "{$m[1]} {$m[2]}",
                            'location' => $relativePath . ':' . ($lineNum + 1),
                        ];
                    }
                }

                if (count($matches) >= $limit) {
                    return $matches;
                }
            }
        }

        return $matches;
    }
}
