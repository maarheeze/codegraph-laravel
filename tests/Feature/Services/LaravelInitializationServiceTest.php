<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use Maarheeze\CodeGraph\Laravel\Services\LaravelInitializationService;
use PHPUnit\Framework\TestCase;

use function file_get_contents;
use function is_dir;
use function mkdir;
use function rmdir;
use function scandir;
use function sprintf;
use function substr_count;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

final class LaravelInitializationServiceTest extends TestCase
{
    private string $projectRoot;

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->projectRoot);

        parent::tearDown();
    }

    public function testCliModeWritesCliGuidelinesAndSkipsMcp(): void
    {
        $projectRoot = $this->makeProjectRoot();

        $result = (new LaravelInitializationService())->run($projectRoot, false);

        $this->assertNull($result['error']);

        $claudeContent = $this->readFile(sprintf('%s/CLAUDE.md', $projectRoot));
        $this->assertStringContainsString('<!-- codegraph -->', $claudeContent);
        $this->assertStringContainsString('ALWAYS use the CodeGraph CLI', $claudeContent);
        $this->assertStringContainsString('php vendor/bin/codegraph search <name>', $claudeContent);

        $this->assertFileDoesNotExist(sprintf('%s/.mcp.json', $projectRoot));
        $this->assertFileDoesNotExist(sprintf('%s/.claude/settings.local.json', $projectRoot));
    }

    public function testMcpModeWritesMcpGuidelinesAndRegistersServer(): void
    {
        $projectRoot = $this->makeProjectRoot();

        $result = (new LaravelInitializationService())->run($projectRoot, true, 'php');

        $this->assertNull($result['error']);

        $claudeContent = $this->readFile(sprintf('%s/CLAUDE.md', $projectRoot));
        $this->assertStringContainsString('ALWAYS use the CodeGraph MCP tools', $claudeContent);
        $this->assertStringContainsString('codegraph_search', $claudeContent);

        $mcpContent = $this->readFile(sprintf('%s/.mcp.json', $projectRoot));
        $this->assertStringContainsString('codegraph', $mcpContent);

        $settingsContent = $this->readFile(sprintf('%s/.claude/settings.local.json', $projectRoot));
        $this->assertStringContainsString('codegraph', $settingsContent);
    }

    public function testGuidelinesAreNotDuplicatedOnSecondRun(): void
    {
        $projectRoot = $this->makeProjectRoot();

        $service = new LaravelInitializationService();
        $service->run($projectRoot, false);
        $service->run($projectRoot, false);

        $claudeContent = $this->readFile(sprintf('%s/CLAUDE.md', $projectRoot));
        $this->assertSame(1, substr_count($claudeContent, '<!-- codegraph -->'));
    }

    private function makeProjectRoot(): string
    {
        $projectRoot = sprintf('%s/%s', sys_get_temp_dir(), uniqid('codegraph-init-', true));
        mkdir($projectRoot, 0755, true);
        $this->projectRoot = $projectRoot;

        return $projectRoot;
    }

    private function readFile(string $path): string
    {
        $this->assertFileExists($path);
        $content = file_get_contents($path);
        $this->assertNotFalse($content);

        return $content;
    }

    private function deleteDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $entries = scandir($directory);
        if ($entries === false) {
            return;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = sprintf('%s/%s', $directory, $entry);
            if (is_dir($path)) {
                $this->deleteDirectory($path);
                continue;
            }

            unlink($path);
        }

        rmdir($directory);
    }
}
