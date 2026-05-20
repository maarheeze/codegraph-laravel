<?php

declare(strict_types=1);

namespace Maarheeze\CodeGraph\Laravel\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Foundation\Application;
use Maarheeze\CodeGraph\Laravel\Services\LaravelStatusService;
use Maarheeze\CodeGraph\Paths;

use function count;
use function file_exists;
use function sprintf;

final class StatusCommand extends Command
{
    protected $signature = 'codegraph:status';
    protected $description = 'Show CodeGraph indexing status and extracted Laravel patterns';

    public function handle(Application $app): int
    {
        $databasePath = Paths::databasePath($app->basePath());

        if (!file_exists($databasePath)) {
            $this->error('Database not found. Run: php artisan codegraph:index');
            return self::FAILURE;
        }

        $statusService = new LaravelStatusService($databasePath);

        $this->info('CodeGraph Status');
        $this->line('');

        $this->section('Overall Stats');
        $stats = $statusService->getOverallStats();
        $this->info(sprintf('  Symbols: %d', $stats['symbols']));
        $this->info(sprintf('  Edges: %d', $stats['edges']));
        $this->info(sprintf('  Chunks: %d', $stats['chunks']));
        $this->info(sprintf('  Files: %d', $stats['files']));

        $this->section('All Edge Kinds');
        foreach ($statusService->getAllEdgeKinds() as $kind) {
            $edges = $statusService->getEdgesByKind($kind);
            $this->line(sprintf('  %s: %d', $kind, count($edges)));
        }

        $this->section('Sample Routes');
        foreach ($statusService->getSampleEdges('route') as $edge) {
            $this->line(sprintf(
                '  %s → %s',
                $edge['src_fqn'],
                $edge['dst_fqn'],
            ));
        }

        $this->section('Sample Service Bindings');
        foreach ($statusService->getSampleEdges('service_binding') as $edge) {
            $this->line(sprintf(
                '  %s → %s',
                $edge['src_fqn'],
                $edge['dst_fqn'],
            ));
        }

        return self::SUCCESS;
    }

    private function section(string $title): void
    {
        $this->line('');
        $this->comment($title);
    }
}
