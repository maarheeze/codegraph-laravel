<?php

declare(strict_types=1);

namespace Maarheeze\CodeGraph\Laravel;

use Illuminate\Support\ServiceProvider;
use Maarheeze\CodeGraph\Laravel\Commands\IndexCommand;
use Maarheeze\CodeGraph\Laravel\Commands\InitCommand;
use Maarheeze\CodeGraph\Laravel\Commands\StatusCommand;
use Maarheeze\CodeGraph\Laravel\Commands\WatchCommand;

use function dirname;

final class LaravelServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                IndexCommand::class,
                InitCommand::class,
                StatusCommand::class,
                WatchCommand::class,
            ]);

            $this->publishes([
                dirname(__DIR__) . '/routes/ai.php' => $this->app->basePath('routes/ai.php'),
            ], 'ai-routes');
        }
    }
}
