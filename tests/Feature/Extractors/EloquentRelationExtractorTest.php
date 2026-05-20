<?php

declare(strict_types=1);

namespace Tests\Feature\Extractors;

use Maarheeze\CodeGraph\Laravel\Extractors\EloquentRelationExtractor;
use Maarheeze\CodeGraph\Values\Edge;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;

final class EloquentRelationExtractorTest extends TestCase
{
    public function testExtractsHasMany(): void
    {
        $code = <<<'PHP'
        <?php
        namespace App\Models;
        class User
        {
            public function posts()
            {
                return $this->hasMany(Post::class);
            }
        }
        PHP;

        $edges = $this->extract($code);

        $this->assertCount(1, $edges);
        $this->assertEquals('relation_hasMany', $edges[0]->kind);
        $this->assertEquals('App\Models\User', $edges[0]->sourceFullyQualifiedName);
        $this->assertEquals('Post', $edges[0]->destinationFullyQualifiedName);
    }

    public function testExtractsBelongsTo(): void
    {
        $code = <<<'PHP'
        <?php
        namespace App\Models;
        class Comment
        {
            public function post()
            {
                return $this->belongsTo(Post::class);
            }
        }
        PHP;

        $edges = $this->extract($code);

        $this->assertCount(1, $edges);
        $this->assertEquals('relation_belongsTo', $edges[0]->kind);
        $this->assertEquals('App\Models\Comment', $edges[0]->sourceFullyQualifiedName);
        $this->assertEquals('Post', $edges[0]->destinationFullyQualifiedName);
    }

    public function testExtractsMultipleRelations(): void
    {
        $code = <<<'PHP'
        <?php
        namespace App\Models;
        class User
        {
            public function posts()
            {
                return $this->hasMany(Post::class);
            }

            public function profile()
            {
                return $this->hasOne(Profile::class);
            }

            public function role()
            {
                return $this->belongsTo(Role::class);
            }
        }
        PHP;

        $edges = $this->extract($code);

        $this->assertCount(3, $edges);
        $this->assertEquals('relation_hasMany', $edges[0]->kind);
        $this->assertEquals('relation_hasOne', $edges[1]->kind);
        $this->assertEquals('relation_belongsTo', $edges[2]->kind);
    }

    public function testExtractsRelationWithStringModel(): void
    {
        $code = <<<'PHP'
        <?php
        namespace App\Models;
        class User
        {
            public function posts()
            {
                return $this->hasMany('App\Models\Post');
            }
        }
        PHP;

        $edges = $this->extract($code);

        $this->assertCount(1, $edges);
        $this->assertEquals('App\Models\Post', $edges[0]->destinationFullyQualifiedName);
    }

    public function testIgnoresInvalidRelations(): void
    {
        $code = <<<'PHP'
        <?php
        namespace App\Models;
        class User
        {
            public function custom()
            {
                return $this->customRelation(Post::class);
            }

            public function noArgs()
            {
                return $this->hasMany();
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

        $extractor = new EloquentRelationExtractor('app/Models/User.php', $code);
        $traverser = new NodeTraverser();
        $traverser->addVisitor($extractor);
        $traverser->traverse($ast);

        return $extractor->edges();
    }
}
