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
use function str_contains;

use const JSON_PRETTY_PRINT;
use const JSON_UNESCAPED_SLASHES;

#[Description('Find Laravel service provider bindings')]
final class CodegraphFindServiceTool extends Tool
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

            $allSymbols = $storage->findByName('ServiceProvider');
            foreach ($allSymbols as $symbol) {
                $edges = $storage->findEdgesFrom($symbol->fullyQualifiedName);
                foreach ($edges as $edge) {
                    if (
                        $edge->kind === 'service_binding'
                        && $this->matchesService($edge->metadata, $edge->destinationFullyQualifiedName, $name)
                    ) {
                        $abstract = '';

                        if (
                            $edge->metadata !== null
                            && preg_match('/abstract:([^,]+)/', $edge->metadata, $matches)
                        ) {
                            $abstract = $matches[1];
                        }

                        $results[] = [
                            'binding' => $abstract,
                            'class' => $edge->destinationFullyQualifiedName,
                            'provider' => $symbol->fullyQualifiedName,
                            'file' => $edge->file,
                            'line' => $edge->line,
                        ];
                    }
                }
            }

            $json = json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            Assert::string($json);
            return Response::text($json);
        } catch (Throwable $e) {
            return Response::error(sprintf('Error finding services: %s', $e->getMessage()));
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()
                ->description('Service name, class name, or binding key')
                ->required(),
        ];
    }

    private function matchesService(?string $metadata, string $destination, string $name): bool
    {
        if ($name === '') {
            return true;
        }

        if ($metadata !== null && str_contains($metadata, $name)) {
            return true;
        }

        return str_contains($destination, $name);
    }
}
