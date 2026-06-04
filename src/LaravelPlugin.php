<?php

declare(strict_types=1);

namespace Maarheeze\CodeGraph\Laravel;

use Maarheeze\CodeGraph\Contracts\Plugin;
use Maarheeze\CodeGraph\Contracts\Storage;
use Maarheeze\CodeGraph\Laravel\Extractors\EloquentRelationExtractor;
use Maarheeze\CodeGraph\Laravel\Extractors\RouteExtractor;
use Maarheeze\CodeGraph\Laravel\Extractors\ServiceProviderBindingExtractor;

final class LaravelPlugin implements Plugin
{
    public function getName(): string
    {
        return 'laravel';
    }

    /**
     * @return array<int, class-string>
     */
    public function getVisitors(): array
    {
        return [
            ServiceProviderBindingExtractor::class,
            RouteExtractor::class,
            EloquentRelationExtractor::class,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getMcpTools(): array
    {
        return [];
    }

    /**
     * @return array<string, callable>
     */
    public function getMcpToolHandlers(Storage $storage): array
    {
        return [];
    }

    public function onBeforeIndex(): void
    {
    }

    public function onAfterExtraction(): void
    {
    }

    public function onBeforeResolution(): void
    {
    }

    public function onAfterResolution(): void
    {
    }

    public function onAfterIndex(): void
    {
    }
}
