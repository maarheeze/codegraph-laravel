<?php

declare(strict_types=1);

namespace Maarheeze\CodeGraph\Laravel\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Foundation\Application;
use Maarheeze\CodeGraph\Laravel\Services\LaravelIndexingService;
use Maarheeze\CodeGraph\Paths;

final class IndexCommand extends Command
{
    protected $signature = 'codegraph:index';
    protected $description = 'Index the Laravel application with CodeGraph';

    public function handle(Application $app): int
    {
        $basePath = $app->basePath();
        $service = new LaravelIndexingService();
        $result = $service->run(
            $basePath,
            Paths::databasePath($basePath),
            ['app', 'routes'],
        );

        if ($result['error'] !== null) {
            $this->error($result['error']);
            return self::FAILURE;
        }

        foreach ($result['lines'] as $line) {
            $this->line($line);
        }

        return self::SUCCESS;
    }
}
