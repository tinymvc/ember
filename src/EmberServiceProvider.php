<?php

namespace Spark\Ember;

use Spark\Console\Commands;
use Spark\Ember\Commands\InstallCommand;
use Spark\Ember\Commands\LookupCommand;
use Spark\Foundation\Providers\ServiceProvider;

class EmberServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bind Ember commands or services if needed
    }

    /**
     * Bootstrap Ember services and commands.
     */
    public function boot(): void
    {
        if (!$this->app->bound(Commands::class)) {
            return;
        }

        /** @var Commands $commands */
        $commands = $this->app->get(Commands::class);

        $commands->addCommand('ember:install', [InstallCommand::class, 'handle'])
            ->description('Install Ember AI guidelines and configuration')
            ->help('Generates tailored AGENTS.md guidelines and sets up local skill entrypoints.');

        $commands->addCommand('ember:lookup', [LookupCommand::class, 'handle'])
            ->description('Search TinyCore API methods, classes, and signatures')
            ->help('Usage: php spark ember:lookup <query> [--file=subdir] [--limit=N]');

        // Register package skill assets as publishable if supported by TinyCore
        $skillsPath = dirname(__DIR__) . '/resources/skills';
        if (is_dir($skillsPath) && method_exists($this, 'publishes') && function_exists('root_dir')) {
            $this->publishes([
                $skillsPath => root_dir('.agents/skills/tinymvc-ember'),
            ], 'ember-skills');
        }
    }
}
