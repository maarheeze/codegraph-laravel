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

#[Description('Find Laravel routes by URL pattern or name')]
final class CodegraphFindRouteTool extends Tool
{
    public function handle(Request $request): Response
    {
        try {
            $pattern = $request->get('pattern');
            if (!is_string($pattern)) {
                $pattern = '';
            }

            $codeGraph = CodeGraph::forProject();
            $storage = $codeGraph->getStorage();
            $results = [];

            $allSymbols = $storage->findByName('Route');
            foreach ($allSymbols as $symbol) {
                $edges = $storage->findEdgesFrom($symbol->fullyQualifiedName);
                foreach ($edges as $edge) {
                    if ($edge->kind === 'route' && $this->matchesPattern($edge->sourceFullyQualifiedName, $pattern)) {
                        $metadata = $edge->metadata ?? '';
                        $path = '';

                        if (preg_match('/method:(\w+),path:([^,]+)/', $metadata, $matches)) {
                            $path = $matches[2];
                        }

                        $results[] = [
                            'name' => '',
                            'path' => $path,
                            'controller' => $edge->destinationFullyQualifiedName,
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
            return Response::error(sprintf('Error finding routes: %s', $e->getMessage()));
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'pattern' => $schema->string()
                ->description('Route URL pattern or name to search for (e.g., "users", "user.show")')
                ->required(),
        ];
    }

    private function matchesPattern(string $source, string $pattern): bool
    {
        if ($pattern === '') {
            return true;
        }

        return str_contains($source, $pattern);
    }
}
