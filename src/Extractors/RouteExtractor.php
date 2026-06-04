<?php

declare(strict_types=1);

namespace Maarheeze\CodeGraph\Laravel\Extractors;

use Maarheeze\CodeGraph\Extraction\BaseAstVisitor;
use Maarheeze\CodeGraph\Values\Edge;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;

use function count;
use function in_array;
use function sprintf;
use function strtolower;
use function strtoupper;

final class RouteExtractor extends BaseAstVisitor
{
    public function enterNode(Node $node): ?Node
    {
        if ($node instanceof StaticCall) {
            $this->extractRoute($node);
        }

        return null;
    }

    private function extractRoute(StaticCall $node): void
    {
        if (!($node->class instanceof Name) || !($node->name instanceof Identifier)) {
            return;
        }

        if ($node->class->toCodeString() !== 'Route') {
            return;
        }

        $httpMethod = $node->name->name;
        if (!in_array(strtolower($httpMethod), ['get', 'post', 'patch', 'put', 'delete', 'options', 'head'], true)) {
            return;
        }

        if (count($node->args) < 2) {
            return;
        }

        if (!($node->args[0] instanceof Arg) || !($node->args[1] instanceof Arg)) {
            return;
        }

        $path = $this->extractString($node->args[0]->value);
        $controller = $this->extractController($node->args[1]->value);

        if ($path && $controller) {
            $this->edgesList[] = new Edge(
                'route',
                '\\Route',
                $controller,
                $this->relativeFilePath,
                $node->getStartLine(),
                sprintf('method:%s,path:%s', strtoupper($httpMethod), $path),
            );
        }
    }

    private function extractString(Node $node): ?string
    {
        if ($node instanceof String_) {
            return $node->value;
        }

        return null;
    }

    private function extractController(Node $node): ?string
    {
        if ($node instanceof Array_) {
            if (count($node->items) > 0) {
                $classNode = $node->items[0]->value;
                if ($classNode instanceof ClassConstFetch && $classNode->class instanceof Name) {
                    $className = $classNode->class->toCodeString();
                    if (count($node->items) > 1) {
                        $methodNode = $node->items[1]->value;
                        if ($methodNode instanceof String_) {
                            return sprintf('%s@%s', $className, $methodNode->value);
                        }
                    }

                    return $className;
                }
            }
        }

        if ($node instanceof ClassConstFetch && $node->class instanceof Name) {
            return $node->class->toCodeString();
        }

        return null;
    }
}
