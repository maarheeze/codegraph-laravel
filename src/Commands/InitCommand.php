<?php

declare(strict_types=1);

namespace Maarheeze\CodeGraph\Laravel\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Foundation\Application;
use Maarheeze\CodeGraph\Laravel\Services\LaravelInitializationService;
use Webmozart\Assert\Assert;

final class InitCommand extends Command
{
    protected $signature = 'codegraph:init
        {--mcp : Register the MCP server instead of using the CLI}
        {--mcp-config=auto : MCP server configuration (auto, sail, docker, php)}';
    protected $description = 'Initialize CodeGraph database';

    public function handle(Application $app): int
    {
        $basePath = $app->basePath();
        $registerMcp = (bool) $this->option('mcp');
        $mcpConfig = $this->option('mcp-config');
        Assert::string($mcpConfig);

        $service = new LaravelInitializationService();
        $result = $service->run($basePath, $registerMcp, $mcpConfig);

        if ($result['error'] !== null) {
            $this->error($result['error']);
            return self::FAILURE;
        }

        foreach ($result['lines'] as $line) {
            $this->line($line);
        }

        if ($registerMcp) {
            $this->call('vendor:publish', [
                '--provider' => 'Maarheeze\\CodeGraph\\Laravel\\LaravelServiceProvider',
                '--tag' => 'ai-routes',
                '--force' => true,
            ]);
        }

        return self::SUCCESS;
    }
}
