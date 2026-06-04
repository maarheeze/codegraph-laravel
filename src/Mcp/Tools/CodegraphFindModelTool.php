<?php

declare(strict_types=1);

namespace Maarheeze\CodeGraph\Laravel\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Maarheeze\CodeGraph\CodeGraph;
use Throwable;
use Webmozart\Assert\Assert;

use function is_string;
use function json_encode;
use function preg_match;
use function sprintf;
use function str_starts_with;
use function substr;

use const JSON_PRETTY_PRINT;
use const JSON_UNESCAPED_SLASHES;

#[Description('Find Eloquent models and their relations')]
final class CodegraphFindModelTool extends Tool
{
    public function handle(Request $request): Response
    {
        try {
            $name = $request->get('name');
            if (!is_string($name)) {
                $name = '';
            }

            $codeGraph = CodeGraph::forProject();
            $storage = $codeGraph->getStorage();
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
                                'name' => $this->extractRelationName($edge->metadata),
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

            $json = json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            Assert::string($json);
            return Response::text($json);
        } catch (Throwable $e) {
            return Response::error(sprintf('Error finding models: %s', $e->getMessage()));
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()
                ->description('Model class name or partial FQN')
                ->required(),
        ];
    }

    private function extractRelationName(?string $metadata): string
    {
        if ($metadata === null) {
            return 'unknown';
        }

        if (preg_match('/relation_name:(\w+)/', $metadata, $matches)) {
            return $matches[1];
        }

        return 'unknown';
    }
}
