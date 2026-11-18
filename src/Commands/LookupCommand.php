<?php

namespace Spark\Ember\Commands;

use Composer\InstalledVersions;
use InvalidArgumentException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use Spark\Console\Prompt;
use Spark\Ember\Inspectors\AppInspector;
use Spark\Foundation\Application;
use function count;
use function in_array;
use function is_array;
use function is_string;
use function strlen;

class LookupCommand
{
    /** @param array<string, mixed> $args */
    public function handle(array $args): int
    {
        $query = $args['_args'][0] ?? $args['query'] ?? null;
        if (!is_string($query) || trim($query) === '') {
            Prompt::message('Please provide a search term: php spark ember:lookup <symbol>', 'warning');
            return 2;
        }

        $query = preg_replace('/\(\s*\)$/', '', trim($query));
        $limit = filter_var($args['limit'] ?? 25, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 200],
        ]);

        if ($query === '' || $limit === false || !is_string($args['file'] ?? '')) {
            Prompt::message('Use a symbol, --file=substring, and --limit=1..200.', 'danger');
            return 2;
        }

        try {
            $sourcePath = $this->sourcePath($args);
        } catch (InvalidArgumentException $exception) {
            Prompt::message($exception->getMessage(), 'danger');
            return 2;
        }

        $results = $this->search($sourcePath, $query, $args['file'] ?? '', $limit);
        if ($results === []) {
            Prompt::message("No declarations found matching '{$query}'.", 'info');
            return 1;
        }

        Prompt::section("Lookup results for '{$query}'");
        Prompt::table(['Type', 'Signature', 'File:Line'], $results);

        return 0;
    }

    private function sourcePath(array $args): string
    {
        if (isset($args['source'], $args['vendor'])) {
            throw new InvalidArgumentException('Use either --source or --vendor, not both.');
        }

        if (isset($args['source'])) {
            $source = $args['source'];
        } else {
            $package = $args['vendor'] ?? 'tinymvc/tinycore';
            if (!is_string($package) || !preg_match('/^[a-z0-9][a-z0-9_.-]*\/[a-z0-9][a-z0-9_.-]*$/D', $package)) {
                throw new InvalidArgumentException('Use --vendor=vendor/package with an installed Composer package.');
            }

            if (class_exists(InstalledVersions::class) && InstalledVersions::isInstalled($package)) {
                $installPath = InstalledVersions::getInstallPath($package);
                $source = $installPath === null ? null : $installPath . '/src';
            } elseif ($package === 'tinymvc/tinycore') {
                $source = dirname((new ReflectionClass(Application::class))->getFileName(), 2);
            } else {
                $source = AppInspector::basePath() . '/vendor/' . $package . '/src';
            }
        }

        if (!is_string($source) || !is_dir($source) || !is_readable($source)) {
            throw new InvalidArgumentException('Package source directory not found. Use --source=/path/to/src for other layouts.');
        }

        return realpath($source);
    }

    /** @return list<array{string, string, string}> */
    private function search(string $dir, string $query, string $fileFilter, int $limit): array
    {
        $files = [];
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
            if (
                $file->isFile() && $file->getExtension() === 'php'
                && str_contains(substr($file->getPathname(), strlen($dir) + 1), $fileFilter)
            ) {
                $files[] = $file->getPathname();
            }
        }
        sort($files);
        $matches = [];

        foreach ($files as $path) {
            $content = file_get_contents($path);
            if ($content === false || stripos($content, $query) === false) {
                continue;
            }

            $tokens = token_get_all($content);
            $count = count($tokens);
            foreach ($tokens as $index => $token) {
                if (!is_array($token) || !in_array($token[0], [T_FUNCTION, T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM], true)) {
                    continue;
                }

                $nameIndex = $index + 1;
                while ($nameIndex < $count) {
                    $next = $tokens[$nameIndex];
                    if (is_array($next) && in_array($next[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT, T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG], true)) {
                        $nameIndex++;
                        continue;
                    }
                    break;
                }

                $name = $tokens[$nameIndex] ?? null;
                if (!is_array($name) || $name[0] !== T_STRING || stripos($name[1], $query) === false) {
                    continue;
                }

                $start = $index;
                while (
                    $start > 0 && is_array($tokens[$start - 1])
                    && in_array($tokens[$start - 1][0], [T_WHITESPACE, T_PUBLIC, T_PROTECTED, T_PRIVATE, T_STATIC, T_FINAL, T_ABSTRACT, T_READONLY], true)
                ) {
                    $start--;
                }

                $signature = '';
                $depth = 0;
                for ($cursor = $start; $cursor < $count; $cursor++) {
                    $part = $tokens[$cursor];
                    if (!is_array($part)) {
                        if ($depth === 0 && ($part === '{' || $part === ';')) {
                            break;
                        }
                        if ($part === '(' || $part === '[') {
                            $depth++;
                        } elseif ($part === ')' || $part === ']') {
                            $depth--;
                        }
                    }
                    if (is_array($part) && in_array($part[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                        continue;
                    }
                    $signature .= is_array($part) ? $part[1] : $part;
                }

                $matches[] = [ucfirst($token[1]), trim($signature), "$path:$token[2]"];
                if (count($matches) >= $limit) {
                    return $matches;
                }
            }
        }

        return $matches;
    }
}
