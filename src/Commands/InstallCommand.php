<?php

namespace Spark\Ember\Commands;

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
            'db_details' => $info['db_details'],
            'cache_driver' => $info['cache_driver'],
            'queue_driver' => $info['queue_driver'],
            'session_driver' => $info['session_driver'],
            'frontend_stack' => $info['frontend_stack'],
        ]);

        $this->updateAgentsFile("$basePath/AGENTS.md", $guidelines);
        Prompt::message('Tailored AGENTS.md guidelines updated.', 'success');

        // 2. Publish agent skill entrypoint
        $skillsDir = "$basePath/.agents/skills/tinymvc-ember";
        if (!is_dir($skillsDir)) {
            mkdir($skillsDir, 0755, true);
        }
        file_put_contents("$skillsDir/SKILL.md", $this->renderStub('skill', []));
        Prompt::message('AI skill entrypoints (.agents/skills/tinymvc-ember) registered.', 'success');

        Prompt::newline();
        Prompt::table(
            ['Environment Item', 'Detected Value'],
            [
                ['Framework', "TinyMVC Spark (TinyCore {$info['tinycore_version']})"],
                ['PHP Version', $info['php_version']],
                ['Database', "{$info['db_driver']} ({$info['db_details']})"],
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
        $content = file_exists($stubPath) ? file_get_contents($stubPath) : '';

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
        $existing = is_file($path) ? file_get_contents($path) : '';

        $startTag = '<tinymvc-ember-guidelines>';
        $endTag = '</tinymvc-ember-guidelines>';

        if (str_contains($existing, $startTag) && str_contains($existing, $endTag)) {
            $pattern = '#' . preg_quote($startTag, '#') . '.*?' . preg_quote($endTag, '#') . '#s';
            $content = preg_replace($pattern, $guidelines, $existing);
        } else {
            $content = $existing !== '' ? "$guidelines\n\n$existing" : "$guidelines\n";
        }

        file_put_contents($path, $content);
    }
}
