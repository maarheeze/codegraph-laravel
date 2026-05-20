<?php

declare(strict_types=1);

namespace Maarheeze\CodeGraph\Laravel\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Foundation\Application;
use Maarheeze\CodeGraph\Paths;
use Maarheeze\CodeGraph\Services\WatchService;

use function sprintf;

final class WatchCommand extends Command
{
    protected $signature = 'codegraph:watch';
    protected $description = 'Watch for file changes and automatically re-index';

    public function handle(Application $app): int
    {
        $this->info('🔍 CodeGraph watch started. Press Ctrl+C to stop.');
        $this->newLine();

        $basePath = $app->basePath();
        $service = new WatchService(
            $basePath,
            Paths::databasePath($basePath),
        );

        $service->run(
            function (int $count): void {
                $this->info(sprintf('📝 %d file(s) changed. Re-indexing...', $count));
            },
            function ($stats): void {
                $this->line(sprintf(
                    '✅ Indexed %d files, %d symbols, %d edges',
                    $stats->getFilesChanged(),
                    $stats->getSymbolsEmitted(),
                    $stats->getEdgesEmitted(),
                ));
            },
        );
    }
}
