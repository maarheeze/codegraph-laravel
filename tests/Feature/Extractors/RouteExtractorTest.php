<?php

declare(strict_types=1);

namespace Tests\Feature\Extractors;

use Maarheeze\CodeGraph\Laravel\Extractors\RouteExtractor;
use Maarheeze\CodeGraph\Values\Edge;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;

final class RouteExtractorTest extends TestCase
{
    public function testExtractsGetRoute(): void
    {
        $code = <<<'PHP'
        <?php
        Route::get('/users', [UserController::class, 'index']);
        PHP;

        $edges = $this->extract($code);

        $this->assertCount(1, $edges);
        $this->assertEquals('route', $edges[0]->kind);
        $this->assertEquals('\\Route', $edges[0]->sourceFullyQualifiedName);
        $this->assertEquals('UserController@index', $edges[0]->destinationFullyQualifiedName);
        $this->assertStringContainsString('method:GET', $edges[0]->metadata ?? '');
        $this->assertStringContainsString('path:/users', $edges[0]->metadata ?? '');
    }

    public function testExtractsMultipleRoutes(): void
    {
        $code = <<<'PHP'
        <?php
        Route::get('/users', [UserController::class, 'index']);
        Route::post('/users', [UserController::class, 'store']);
        Route::delete('/users/{id}', [UserController::class, 'destroy']);
        PHP;

        $edges = $this->extract($code);

        $this->assertCount(3, $edges);
        $this->assertEquals('\\Route', $edges[0]->sourceFullyQualifiedName);
        $this->assertStringContainsString('method:GET', $edges[0]->metadata ?? '');
        $this->assertEquals('\\Route', $edges[1]->sourceFullyQualifiedName);
        $this->assertStringContainsString('method:POST', $edges[1]->metadata ?? '');
        $this->assertEquals('\\Route', $edges[2]->sourceFullyQualifiedName);
        $this->assertStringContainsString('method:DELETE', $edges[2]->metadata ?? '');
    }

    public function testExtractsRouteWithClassOnly(): void
    {
        $code = <<<'PHP'
        <?php
        Route::get('/dashboard', UserController::class);
        PHP;

        $edges = $this->extract($code);

        $this->assertCount(1, $edges);
        $this->assertEquals('UserController', $edges[0]->destinationFullyQualifiedName);
    }

    public function testIgnoresInvalidRoutes(): void
    {
        $code = <<<'PHP'
        <?php
        Route::get('/users');
        Route::get();
        Route::custom('/path', [Controller::class, 'method']);
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

        $extractor = new RouteExtractor('routes/web.php', $code);
        $traverser = new NodeTraverser();
        $traverser->addVisitor($extractor);
        $traverser->traverse($ast);

        return $extractor->edges();
    }
}
