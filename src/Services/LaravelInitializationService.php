<?php

declare(strict_types=1);

namespace Maarheeze\CodeGraph\Laravel\Services;

use Maarheeze\CodeGraph\CodeGraph;
use Maarheeze\CodeGraph\Paths;
use Webmozart\Assert\Assert;

use function array_key_exists;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function in_array;
use function is_array;
use function is_dir;
use function json_decode;
use function json_encode;
use function mkdir;
use function sprintf;
use function str_contains;

use const JSON_PRETTY_PRINT;
use const JSON_UNESCAPED_SLASHES;
use const PHP_EOL;

final readonly class LaravelInitializationService
{
    /**
     * @return array{lines: array<int, string>, error: ?string}
     */
    public function run(string $projectRoot, string $mcpConfig = 'auto'): array
    {
        $lines = [];

        $cgDir = Paths::directoryPath($projectRoot);

        if (!is_dir($cgDir)) {
            mkdir($cgDir, 0755, true);
        }

        CodeGraph::forProject($projectRoot);
        $lines[] = sprintf('<info>Initialized CodeGraph database at: %s</info>', $cgDir);

        $this->addClaudemdGuidelines($projectRoot);
        $lines[] = '<info>Added CodeGraph guidelines to CLAUDE.md</info>';

        $this->registerMcpServer($projectRoot, $mcpConfig);
        $lines[] = '<info>Registered CodeGraph MCP server in .mcp.json</info>';

        $this->registerClaudeCodeMcpServer($projectRoot);
        $lines[] = '<info>Registered CodeGraph MCP server in .claude/settings.local.json</info>';

        return [
            'lines' => $lines,
            'error' => null,
        ];
    }

    public function initialize(string $projectRoot): void
    {
        $cgDir = Paths::directoryPath($projectRoot);

        if (!is_dir($cgDir)) {
            mkdir($cgDir, 0755, true);
        }

        CodeGraph::forProject($projectRoot);
    }

    public function addClaudemdGuidelines(string $projectRoot): void
    {
        $claudeMdPath = sprintf('%s/CLAUDE.md', $projectRoot);
        $claudeContent = '';
        if (file_exists($claudeMdPath)) {
            $content = file_get_contents($claudeMdPath);
            $claudeContent = $content !== false ? $content : '';
        }

        if (str_contains($claudeContent, '<!-- codegraph -->')) {
            return;
        }

        $section = <<<'MD'
		<!-- codegraph -->
		## CodeGraph

		This project has CodeGraph installed with a pre-built index of all PHP symbols
		and their call graph relationships.

		**CRITICAL:** For any question about code structure, relationships, or impact —
		ALWAYS use the CodeGraph MCP tools. Do NOT use bash, grep, file search, or IDE
		symbol search. The graph is faster, cheaper, and more accurate than text search.

		### Decision Rules

		- "Where is X defined?" → `codegraph_search`
		- "Where is X used?" / "Who calls X?" → `codegraph_callers`
		- "What does X call?" / "What does X depend on?" → `codegraph_callees`
		- "What breaks if I change X?" → `codegraph_blast_radius`
		- "Where is string/pattern Y used?" → `codegraph_search_chunks`

		### Workflow

		1. `codegraph_search(name)` → get exact FQN (e.g. `\App\Models\User`)
		2. Use the appropriate relationship tool with that FQN
		3. Only read files for implementation details not available in the index
		<!-- /codegraph -->
MD;

        $newContent = $claudeContent
            ? sprintf("%s\n\n%s", $claudeContent, $section)
            : sprintf('%s%s', $section, PHP_EOL);
        file_put_contents($claudeMdPath, $newContent);
    }

    private function registerMcpServer(string $projectRoot, string $mcpConfig = 'auto'): void
    {
        $mcpJsonPath = sprintf('%s/.mcp.json', $projectRoot);

        $config = [];
        if (file_exists($mcpJsonPath)) {
            $content = file_get_contents($mcpJsonPath);
            if ($content !== false) {
                $decoded = json_decode($content, true);
                if (is_array($decoded)) {
                    $config = $decoded;
                }
            }
        }

        if (!array_key_exists('mcpServers', $config)) {
            $config['mcpServers'] = [];
        }

        Assert::isArray($config);
        $mcpServers = $config['mcpServers'];
        if (!is_array($mcpServers)) {
            return;
        }

        if (array_key_exists('codegraph', $mcpServers)) {
            return;
        }

        $mcpServers['codegraph'] = $this->detectMcpCommand($projectRoot, $mcpConfig);
        $config['mcpServers'] = $mcpServers;

        file_put_contents($mcpJsonPath, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
    }

    private function registerClaudeCodeMcpServer(string $projectRoot): void
    {
        $settingsPath = sprintf('%s/.claude/settings.local.json', $projectRoot);

        $config = [];
        if (file_exists($settingsPath)) {
            $content = file_get_contents($settingsPath);
            if ($content !== false) {
                $decoded = json_decode($content, true);
                if (is_array($decoded)) {
                    $config = $decoded;
                }
            }
        }

        $changed = false;

        if (!array_key_exists('enabledMcpjsonServers', $config)) {
            $config['enabledMcpjsonServers'] = [];
        }

        $enabledServers = $config['enabledMcpjsonServers'];
        if (!is_array($enabledServers)) {
            $enabledServers = [];
        }

        if (!in_array('codegraph', $enabledServers, true)) {
            $enabledServers[] = 'codegraph';
            $config['enabledMcpjsonServers'] = $enabledServers;
            $changed = true;
        }

        if (!array_key_exists('permissions', $config)) {
            $config['permissions'] = [];
        }

        $permissions = $config['permissions'];
        if (!is_array($permissions)) {
            $permissions = [];
        }

        if (!array_key_exists('allow', $permissions)) {
            $permissions['allow'] = [];
        }

        $allow = $permissions['allow'];
        if (!is_array($allow)) {
            $allow = [];
        }

        if (!in_array('codegraph_.*', $allow, true)) {
            $allow[] = 'codegraph_.*';
            $permissions['allow'] = $allow;
            $config['permissions'] = $permissions;
            $changed = true;
        }

        if ($changed) {
            $json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
            file_put_contents($settingsPath, $json);
        }
    }

    /**
     * @return array<string, string|array<int, string>>
     */
    private function detectMcpCommand(string $projectRoot, string $mcpConfig = 'auto'): array
    {
        if ($mcpConfig === 'sail') {
            return [
                'command' => 'vendor/bin/sail',
                'args' => ['php', 'artisan', 'codegraph:mcp', '--root=.'],
            ];
        }

        if ($mcpConfig === 'docker') {
            return [
                'command' => 'docker',
                'args' => [
                    'compose',
                    'exec',
                    '-T',
                    'laravel.test',
                    'php',
                    'artisan',
                    'codegraph:mcp',
                    '--root=.',
                ],
            ];
        }

        if ($mcpConfig === 'php') {
            return [
                'command' => 'php',
                'args' => ['artisan', 'codegraph:mcp', '--root=.'],
            ];
        }

        $sailPath = sprintf('%s/vendor/bin/sail', $projectRoot);
        $dockerComposePath = sprintf('%s/docker-compose.yml', $projectRoot);

        if (file_exists($sailPath)) {
            return [
                'command' => 'vendor/bin/sail',
                'args' => ['php', 'artisan', 'codegraph:mcp', '--root=.'],
            ];
        }

        if (file_exists($dockerComposePath)) {
            return [
                'command' => 'docker',
                'args' => [
                    'compose',
                    'exec',
                    '-T',
                    'laravel.test',
                    'php',
                    'artisan',
                    'codegraph:mcp',
                    '--root=.',
                ],
            ];
        }

        return [
            'command' => 'php',
            'args' => ['artisan', 'codegraph:mcp', '--root=.'],
        ];
    }
}
