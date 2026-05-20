<?php

declare(strict_types=1);

namespace Maarheeze\CodeGraph\Laravel;

use Illuminate\Support\ServiceProvider;
use Maarheeze\CodeGraph\Laravel\Commands\IndexCommand;
use Maarheeze\CodeGraph\Laravel\Commands\InitCommand;
use Maarheeze\CodeGraph\Laravel\Commands\StatusCommand;
use Maarheeze\CodeGraph\Laravel\Commands\WatchCommand;

final class LaravelServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(LaravelPlugin::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                IndexCommand::class,
                InitCommand::class,
                StatusCommand::class,
                WatchCommand::class,
            ]);
        }
    }
}
