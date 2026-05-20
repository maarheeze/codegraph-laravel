<?php

declare(strict_types=1);

namespace Maarheeze\CodeGraph\Laravel\Extractors;

use Maarheeze\CodeGraph\Extraction\BaseAstVisitor;
use Maarheeze\CodeGraph\Values\Edge;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;

use function count;
use function in_array;
use function preg_match;
use function sprintf;

final class EloquentRelationExtractor extends BaseAstVisitor
{
    public function enterNode(Node $node): ?Node
    {
        if ($node instanceof MethodCall) {
            $this->extractRelation($node);
        }

        return null;
    }

    private function extractRelation(MethodCall $node): void
    {
        if (!($node->name instanceof Identifier)) {
            return;
        }

        $relationMethod = $node->name->name;
        $validRelations = [
            'hasMany',
            'belongsTo',
            'hasOne',
            'hasManyThrough',
            'belongsToMany',
            'morphMany',
            'morphOne',
            'morphTo',
        ];

        if (!in_array($relationMethod, $validRelations, true)) {
            return;
        }

        if (count($node->args) === 0) {
            return;
        }

        if (!($node->args[0] instanceof Arg)) {
            return;
        }

        $relatedModel = $this->extractModelFqn($node->args[0]->value);

        if ($relatedModel) {
            $sourceModel = $this->getModelFqn();
            $this->edgesList[] = new Edge(
                sprintf('relation_%s', $relationMethod),
                $sourceModel,
                $relatedModel,
                $this->relativeFilePath,
                $node->getStartLine(),
                sprintf('relation_type:%s', $relationMethod),
            );
        }
    }

    private function extractModelFqn(Node $node): ?string
    {
        if ($node instanceof ClassConstFetch && $node->class instanceof Name) {
            return $node->class->toCodeString();
        }

        if ($node instanceof String_) {
            return $node->value;
        }

        return null;
    }

    private function getModelFqn(): string
    {
        preg_match('#namespace\s+([^;]+)#', $this->sourceContents, $matches);
        $namespace = $matches[1] ?? '';

        preg_match('#class\s+(\w+)#', $this->sourceContents, $matches);
        $className = $matches[1] ?? 'Unknown';

        return $namespace ? sprintf('%s\\%s', $namespace, $className) : $className;
    }
}
