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
        $this->assertEquals('route:GET:/users', $edges[0]->sourceFullyQualifiedName);
        $this->assertEquals('UserController@index', $edges[0]->destinationFullyQualifiedName);
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
        $this->assertEquals('route:GET:/users', $edges[0]->sourceFullyQualifiedName);
        $this->assertEquals('route:POST:/users', $edges[1]->sourceFullyQualifiedName);
        $this->assertEquals('route:DELETE:/users/{id}', $edges[2]->sourceFullyQualifiedName);
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
