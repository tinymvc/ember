<?php

namespace Spark\Ember\Commands;

use RuntimeException;
use Spark\Console\Prompt;
use Spark\Ember\Inspectors\AppInspector;

class InstallCommand
{
    /**
     * Handle the ember:install command.
     *
     * @param array<string, mixed> $args
     * @return int
     */
    public function handle(array $args): int
    {
        Prompt::section('Installing TinyMVC Ember');

        $info = AppInspector::inspect();
        $basePath = $info['base_path'];

        // 1. Update AGENTS.md
        $guidelines = $this->renderStub('guidelines', [
            'tinycore_version' => $info['tinycore_version'],
            'php_version' => $info['php_version'],
            'db_driver' => $info['db_driver'],
            'cache_driver' => $info['cache_driver'],
            'queue_driver' => $info['queue_driver'],
            'session_driver' => $info['session_driver'],
            'frontend_stack' => $info['frontend_stack'],
        ]);

        $this->updateAgentsFile("$basePath/AGENTS.md", $guidelines);
        Prompt::message('Tailored AGENTS.md guidelines updated.', 'success');

        // 2. Publish agent skill entrypoint
        $skillsDir = "$basePath/.agents/skills/tinymvc-ember";
        if (!is_dir($skillsDir) && !mkdir($skillsDir, 0755, true) && !is_dir($skillsDir)) {
            throw new RuntimeException("Cannot create skill directory: {$skillsDir}");
        }
        $this->writeFile("$skillsDir/SKILL.md", $this->renderStub('skill', []));
        Prompt::message('AI skill entrypoints (.agents/skills/tinymvc-ember) registered.', 'success');

        Prompt::newline();
        Prompt::table(
            ['Environment Item', 'Detected Value'],
            [
                ['Framework', "TinyMVC Spark (TinyCore {$info['tinycore_version']})"],
                ['PHP Version', $info['php_version']],
                ['Database', $info['db_driver']],
                ['Cache Store', $info['cache_driver']],
                ['Queue Driver', $info['queue_driver']],
                ['Session Driver', $info['session_driver']],
                ['Frontend Stack', $info['frontend_stack']],
            ]
        );

        Prompt::message('TinyMVC Ember is ready! Coding agents can now leverage framework-specific guidance.', 'success');

        return 0;
    }

    /**
     * Render a stub template by replacing {{key}} tokens.
     *
     * @param array<string, string> $data
     */
    private function renderStub(string $name, array $data): string
    {
        $stubPath = dirname(__DIR__, 2) . "/resources/stubs/{$name}.stub";
        $content = file_get_contents($stubPath);
        if ($content === false) {
            throw new RuntimeException("Cannot read stub: {$stubPath}");
        }

        foreach ($data as $key => $value) {
            $content = str_replace("{{{$key}}}", (string) $value, $content);
        }

        return $content;
    }

    /**
     * Update AGENTS.md file with the tailored guidelines.
     */
    private function updateAgentsFile(string $path, string $guidelines): void
    {
        $guidelines = rtrim($guidelines);
        $existing = is_file($path) ? file_get_contents($path) : '';

        if ($existing === false) {
            throw new RuntimeException("Cannot read guidelines: {$path}");
        }

        $startTag = '<tinymvc-ember-guidelines>';
        $endTag = '</tinymvc-ember-guidelines>';

        $hasStart = str_contains($existing, $startTag);
        $hasEnd = str_contains($existing, $endTag);
        if (
            $hasStart !== $hasEnd || ($hasStart && (
                substr_count($existing, $startTag) !== 1
                || substr_count($existing, $endTag) !== 1
                || strpos($existing, $startTag) > strpos($existing, $endTag)
            ))
        ) {
            throw new RuntimeException('Repair the Ember guidelines markers in AGENTS.md before installing.');
        }

        if ($hasStart) {
            $pattern = '#' . preg_quote($startTag, '#') . '.*?' . preg_quote($endTag, '#') . '#s';
            $content = preg_replace_callback($pattern, fn() => $guidelines, $existing);
        } else {
            $content = $existing !== '' ? "$guidelines\n\n$existing" : "$guidelines\n";
        }

        $this->writeFile($path, $content);
    }

    private function writeFile(string $path, string $content): void
    {
        if (file_put_contents($path, $content, LOCK_EX) === false) {
            throw new RuntimeException("Cannot write file: {$path}");
        }
    }
}
