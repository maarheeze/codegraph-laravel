<?php

declare(strict_types=1);

namespace Maarheeze\CodeGraph\Laravel\Mcp;

use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use Maarheeze\CodeGraph\Laravel\Mcp\Tools\CodegraphFindModelTool;
use Maarheeze\CodeGraph\Laravel\Mcp\Tools\CodegraphFindRouteTool;
use Maarheeze\CodeGraph\Laravel\Mcp\Tools\CodegraphFindServiceTool;

#[Name('CodeGraph Laravel Tools')]
#[Version('1.0.0')]
#[Instructions(
    'Use these tools to query your Laravel codebase structure. Use codegraph_find_model '
    . 'to find Eloquent models and their relations, codegraph_find_route to find routes '
    . 'by URL pattern or name, and codegraph_find_service to locate service container '
    . 'bindings. These tools help answer questions like "where is X used?", '
    . '"what routes exist?", and "what is bound in the container?".',
)]
final class CodegraphServer extends Server
{
    protected array $tools = [
        CodegraphFindRouteTool::class,
        CodegraphFindModelTool::class,
        CodegraphFindServiceTool::class,
    ];
}
