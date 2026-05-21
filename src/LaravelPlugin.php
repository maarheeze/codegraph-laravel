<?php

declare(strict_types=1);

namespace Maarheeze\CodeGraph\Laravel;

use Maarheeze\CodeGraph\Contracts\FileVisitor;
use Maarheeze\CodeGraph\Contracts\Plugin;
use Maarheeze\CodeGraph\Contracts\Storage;
use Maarheeze\CodeGraph\Laravel\Extractors\EloquentRelationExtractor;
use Maarheeze\CodeGraph\Laravel\Extractors\RouteExtractor;
use Maarheeze\CodeGraph\Laravel\Extractors\ServiceProviderBindingExtractor;
use Maarheeze\CodeGraph\Values\Edge;
use Maarheeze\CodeGraph\Values\Symbol;

use function is_string;
use function preg_match;
use function str_contains;
use function str_starts_with;
use function substr;

final class LaravelPlugin implements Plugin
{
    public function getName(): string
    {
        return 'laravel';
    }

    /**
     * @return array<int, class-string<FileVisitor>>
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
     * @param Storage $storage
     * @return array<string, callable(array<string, mixed>): array<string|int, mixed>>
     */
    public function getMcpToolHandlers(Storage $storage): array
    {
        return [
            'codegraph_find_route' => function (array $arguments) use ($storage): array {
                $pattern = $arguments['pattern'] ?? '';
                if (!is_string($pattern)) {
                    $pattern = '';
                }
                $results = [];

                $allSymbols = $storage->findByName('Route');
                foreach ($allSymbols as $symbol) {
                    $edges = $storage->findEdgesFrom($symbol->fullyQualifiedName);
                    foreach ($edges as $edge) {
                        if ($edge->kind === 'route' && $this->matchesPattern($edge, $pattern)) {
                            $results[] = $this->formatRoute($edge);
                        }
                    }
                }

                return $results;
            },
            'codegraph_find_model' => function (array $arguments) use ($storage): array {
                $name = $arguments['name'] ?? '';
                if (!is_string($name)) {
                    $name = '';
                }
                $results = [];

                $symbols = $storage->findByName($name);
                foreach ($symbols as $symbol) {
                    if ($symbol->kind === 'class') {
                        $relations = [];
                        $edges = $storage->findEdgesFrom($symbol->fullyQualifiedName);
                        foreach ($edges as $edge) {
                            if (str_starts_with($edge->kind, 'relation_')) {
                                $relationType = substr($edge->kind, 9);
                                $relations[] = [
                                    'name' => $this->extractRelationName($edge),
                                    'type' => $relationType,
                                    'related' => $edge->destinationFullyQualifiedName,
                                ];
                            }
                        }

                        $results[] = [
                            'fqn' => $symbol->fullyQualifiedName,
                            'file' => $symbol->file,
                            'relations' => $relations,
                        ];
                    }
                }

                return $results;
            },
            'codegraph_find_service' => function (array $arguments) use ($storage): array {
                $name = $arguments['name'] ?? '';
                if (!is_string($name)) {
                    $name = '';
                }
                $results = [];

                $allSymbols = $storage->findByName('ServiceProvider');
                foreach ($allSymbols as $symbol) {
                    $edges = $storage->findEdgesFrom($symbol->fullyQualifiedName);
                    foreach ($edges as $edge) {
                        if ($edge->kind === 'service_binding' && $this->matchesService($edge, $name)) {
                            $results[] = $this->formatService($edge, $symbol);
                        }
                    }
                }

                return $results;
            },
        ];
    }

    private function matchesPattern(Edge $edge, string $pattern): bool
    {
        if ($pattern === '') {
            return true;
        }

        return str_contains($edge->sourceFullyQualifiedName, $pattern) || str_contains($edge->file, $pattern);
    }

    private function matchesService(Edge $edge, string $name): bool
    {
        if ($name === '') {
            return true;
        }

        if ($edge->metadata !== null && str_contains($edge->metadata, $name)) {
            return true;
        }

        return str_contains($edge->destinationFullyQualifiedName, $name);
    }

    private function extractRelationName(Edge $edge): string
    {
        if ($edge->metadata !== null && preg_match('/relation_type:(\w+)/', $edge->metadata, $matches)) {
            return $matches[1];
        }

        return 'unknown';
    }

    /**
     * @return array<string, mixed>
     */
    private function formatRoute(Edge $edge): array
    {
        $metadata = $edge->metadata ?? '';
        $path = '';

        if (preg_match('/method:(\w+),path:([^,]+)/', $metadata, $matches)) {
            $path = $matches[2];
        }

        return [
            'name' => '',
            'path' => $path,
            'controller' => $edge->destinationFullyQualifiedName,
            'file' => $edge->file,
            'line' => $edge->line,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatService(Edge $edge, Symbol $provider): array
    {
        $abstract = '';

        if (
            $edge->metadata !== null
            && preg_match('/abstract:([^,]+)/', $edge->metadata, $matches)
        ) {
            $abstract = $matches[1];
        }

        return [
            'binding' => $abstract,
            'class' => $edge->destinationFullyQualifiedName,
            'provider' => $provider->fullyQualifiedName,
            'file' => $edge->file,
            'line' => $edge->line,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getMcpTools(): array
    {
        return [
            [
                'name' => 'codegraph_find_route',
                'description' => 'Find a Laravel route by URL pattern or name',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'pattern' => [
                            'type' => 'string',
                            'description' => 'Route URL pattern or name to search for (e.g., "users", "user.show")',
                        ],
                    ],
                    'required' => ['pattern'],
                ],
                'outputSchema' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'name' => ['type' => 'string', 'description' => 'Route name'],
                            'path' => ['type' => 'string', 'description' => 'Route path pattern'],
                            'controller' => ['type' => 'string', 'description' => 'Controller class and method'],
                            'file' => ['type' => 'string', 'description' => 'File path'],
                            'line' => ['type' => 'integer', 'description' => 'Line number'],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'codegraph_find_model',
                'description' => 'Find a Laravel Eloquent model and its relations',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => [
                            'type' => 'string',
                            'description' => 'Model class name or partial FQN',
                        ],
                    ],
                    'required' => ['name'],
                ],
                'outputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'fqn' => ['type' => 'string', 'description' => 'Fully qualified model class name'],
                        'file' => ['type' => 'string', 'description' => 'File path'],
                        'relations' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'name' => [
                                        'type' => 'string',
                                        'description' => 'Relation name',
                                    ],
                                    'type' => [
                                        'type' => 'string',
                                        'description' => 'Relation type (hasMany, belongsTo, etc.)',
                                    ],
                                    'related' => [
                                        'type' => 'string',
                                        'description' => 'Related model FQN',
                                    ],
                                ],
                            ],
                            'description' => 'Eloquent relations defined on this model',
                        ],
                    ],
                ],
            ],
            [
                'name' => 'codegraph_find_service',
                'description' => 'Find a Laravel service provider binding or service class',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => [
                            'type' => 'string',
                            'description' => 'Service name, class name, or binding key',
                        ],
                    ],
                    'required' => ['name'],
                ],
                'outputSchema' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'binding' => ['type' => 'string', 'description' => 'Service container binding key'],
                            'class' => ['type' => 'string', 'description' => 'Concrete class FQN'],
                            'provider' => ['type' => 'string', 'description' => 'Service provider class'],
                            'file' => ['type' => 'string', 'description' => 'File path'],
                            'line' => ['type' => 'integer', 'description' => 'Line number'],
                        ],
                    ],
                ],
            ],
        ];
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
