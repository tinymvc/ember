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
            ->description('Search installed PHP package declarations and signatures')
            ->help('Usage: php spark ember:lookup <query> [--vendor=vendor/package | --source=/path/to/src] [--file=subdir] [--limit=1..200]');
    }
}
