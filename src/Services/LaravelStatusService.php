<?php

declare(strict_types=1);

namespace Maarheeze\CodeGraph\Laravel\Services;

use Maarheeze\CodeGraph\Services\StatusService;

final readonly class LaravelStatusService
{
    private StatusService $coreService;

    public function __construct(
        string $databasePath,
    ) {
        $this->coreService = new StatusService($databasePath);
    }

    /**
     * @return array{lines: array<int, string>, error: ?string}
     */
    public function run(): array
    {
        return $this->coreService->run();
    }

    /**
     * @return array{symbols: int, edges: int, chunks: int, files: int}
     */
    public function getOverallStats(): array
    {
        return $this->coreService->getOverallStats();
    }

    /**
     * @return array<int, string>
     */
    public function getAllEdgeKinds(): array
    {
        return $this->coreService->getAllEdgeKinds();
    }

    /**
     * @return array<int, array{kind: string, src_fqn: string, dst_fqn: string, file: string, line: int}>
     */
    public function getEdgesByKind(string $kind): array
    {
        return $this->coreService->getEdgesByKind($kind);
    }

    /**
     * @return array<int, array{kind: string, src_fqn: string, dst_fqn: string, file: string, line: int}>
     */
    public function getSampleEdges(string $kind): array
    {
        return $this->coreService->getSampleEdges($kind);
    }
}
