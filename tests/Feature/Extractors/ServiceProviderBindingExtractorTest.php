<?php

declare(strict_types=1);

namespace Tests\Feature\Extractors;

use Maarheeze\CodeGraph\Laravel\Extractors\ServiceProviderBindingExtractor;
use Maarheeze\CodeGraph\Values\Edge;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;

final class ServiceProviderBindingExtractorTest extends TestCase
{
    public function testExtractsBind(): void
    {
        $code = <<<'PHP'
        <?php
        namespace App\Providers;
        class AppServiceProvider
        {
            public function register()
            {
                $this->app->bind('MyInterface', 'MyImplementation');
            }
        }
        PHP;

        $edges = $this->extract($code);

        $this->assertCount(1, $edges);
        $this->assertEquals('service_binding', $edges[0]->kind);
        $this->assertEquals('App\Providers\AppServiceProvider', $edges[0]->sourceFullyQualifiedName);
        $this->assertEquals('MyImplementation', $edges[0]->destinationFullyQualifiedName);
    }

    public function testExtractsSingleton(): void
    {
        $code = <<<'PHP'
        <?php
        namespace App\Providers;
        class AppServiceProvider
        {
            public function register()
            {
                $this->app->singleton('MyService', MyServiceImpl::class);
            }
        }
        PHP;

        $edges = $this->extract($code);

        $this->assertCount(1, $edges);
        $this->assertEquals('service_binding', $edges[0]->kind);
        $this->assertEquals('MyServiceImpl', $edges[0]->destinationFullyQualifiedName);
    }

    public function testExtractsMultipleBindings(): void
    {
        $code = <<<'PHP'
        <?php
        namespace App\Providers;
        class AppServiceProvider
        {
            public function register()
            {
                $this->app->bind('A', 'B');
                $this->app->singleton('C', 'D');
                $this->app->scoped('E', 'F');
            }
        }
        PHP;

        $edges = $this->extract($code);

        $this->assertCount(3, $edges);
        $this->assertEquals('B', $edges[0]->destinationFullyQualifiedName);
        $this->assertEquals('D', $edges[1]->destinationFullyQualifiedName);
        $this->assertEquals('F', $edges[2]->destinationFullyQualifiedName);
    }

    public function testIgnoresInvalidBindings(): void
    {
        $code = <<<'PHP'
        <?php
        namespace App\Providers;
        class AppServiceProvider
        {
            public function register()
            {
                $this->app->bind('OnlyOne');
                $this->app->unknown('A', 'B');
            }
        }
        PHP;

        $edges = $this->extract($code);

        $this->assertCount(0, $edges);
    }

    /**
     * @return array<int, Edge>
     */
    private function extract(string $code): array
    {
        $parserFactory = new ParserFactory();
        $parser = $parserFactory->createForHostVersion();
        $ast = $parser->parse($code);

        $this->assertNotNull($ast);

        $extractor = new ServiceProviderBindingExtractor('app/Providers/AppServiceProvider.php', $code);
        $traverser = new NodeTraverser();
        $traverser->addVisitor($extractor);
        $traverser->traverse($ast);

        return $extractor->edges();
    }
}
