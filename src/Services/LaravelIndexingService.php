<?php

declare(strict_types=1);

namespace Maarheeze\CodeGraph\Laravel\Services;

use Maarheeze\CodeGraph\Laravel\LaravelPlugin;
use Maarheeze\CodeGraph\Plugin\PluginRegistry;
use Maarheeze\CodeGraph\Services\IndexingService;

final readonly class LaravelIndexingService
{
    private IndexingService $coreService;

    public function __construct(
        ?PluginRegistry $pluginRegistry = null,
    ) {
        $registry = $pluginRegistry ?? new PluginRegistry();
        $registry->register(new LaravelPlugin());

        $this->coreService = new IndexingService($registry);
    }

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
        return $this->coreService->run(
            $projectRoot,
            $databasePath,
            $scanPaths,
            $excludes,
        );
    }
}
