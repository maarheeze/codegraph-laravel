<?php

declare(strict_types=1);

namespace Maarheeze\CodeGraph\Laravel\Services;

use Maarheeze\CodeGraph\CodeGraph;
use Maarheeze\CodeGraph\Laravel\LaravelPlugin;
use Maarheeze\CodeGraph\Paths;
use Throwable;

use function is_dir;
use function sprintf;
use function str_contains;

final readonly class LaravelIndexingService
{
    /**
     * @param array<int, string> $scanPaths
     * @param array<int, string> $excludes
     * @return array{lines: array<int, string>, error: ?string}
     */
    public function run(
        string $projectRoot,
        string $databasePath,
        array $scanPaths = ['app'],
        array $excludes = ['vendor', 'storage', 'node_modules'],
    ): array {
        $cgDir = Paths::directoryPath($projectRoot);

        if (!is_dir($cgDir)) {
            return [
                'lines' => [],
                'error' => 'CodeGraph not initialized. Run <fg=blue>php artisan codegraph:init</> first.',
            ];
        }

        try {
            $graph = new CodeGraph($projectRoot, $databasePath, $scanPaths, $excludes);
            $graph->registerPlugin(new LaravelPlugin());
            $stats = $graph->index();

            $lines = [
                sprintf('Indexed %d symbols', $stats->getSymbolsEmitted()),
                sprintf('Found %d edges', $stats->getEdgesEmitted()),
                sprintf('Processed %d chunks', $stats->getChunksEmitted()),
                sprintf('Scanned %d files', $stats->getFilesScanned()),
                sprintf('Duration: %.2fs', $stats->getDurationSeconds()),
            ];

            return ['lines' => $lines, 'error' => null];
        } catch (Throwable $e) {
            if (str_contains($e->getMessage(), 'unable to open database file')) {
                return [
                    'lines' => [],
                    'error' => 'CodeGraph database not found. Run <fg=blue>php artisan codegraph:init</> first.',
                ];
            }

            return ['lines' => [], 'error' => $e->getMessage()];
        }
    }
}
