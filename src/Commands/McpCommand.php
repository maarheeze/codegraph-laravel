<?php

declare(strict_types=1);

namespace Maarheeze\CodeGraph\Laravel\Commands;

use Illuminate\Console\Command;
use Maarheeze\CodeGraph\CodeGraph;
use Maarheeze\CodeGraph\Laravel\LaravelPlugin;
use Maarheeze\CodeGraph\Mcp\McpServer;
use Throwable;
use Webmozart\Assert\Assert;

use function fwrite;
use function sprintf;

use const STDERR;

final class McpCommand extends Command
{
    protected $signature = 'codegraph:mcp {--root=. : Project root directory}';
    protected $description = 'Start the CodeGraph MCP server';

    public function handle(): int
    {
        try {
            $root = $this->option('root');
            Assert::string($root);

            $codeGraph = CodeGraph::forProject($root);
            $codeGraph->registerPlugin(new LaravelPlugin());

            $server = new McpServer($codeGraph->getStorage(), $codeGraph->getPluginRegistry());
            $server->start();
        } catch (Throwable $e) {
            fwrite(STDERR, sprintf(
                "MCP Server Error: %s\n\n"
                . "Make sure to run 'php artisan codegraph:init' and 'php artisan codegraph:index' first.\n"
                . "Full error: %s\n",
                $e->getMessage(),
                $e,
            ));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
