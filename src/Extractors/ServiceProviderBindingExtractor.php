<?php

declare(strict_types=1);

namespace Maarheeze\CodeGraph\Laravel\Extractors;

use Maarheeze\CodeGraph\Extraction\BaseAstVisitor;
use Maarheeze\CodeGraph\Values\Edge;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;

use function count;
use function in_array;
use function preg_match;
use function sprintf;

final class ServiceProviderBindingExtractor extends BaseAstVisitor
{
    public function enterNode(Node $node): ?Node
    {
        if ($node instanceof MethodCall) {
            $this->extractBinding($node);
        }

        return null;
    }

    private function extractBinding(MethodCall $node): void
    {
        if (!($node->name instanceof Identifier)) {
            return;
        }

        $methodName = $node->name->name;
        if (!in_array($methodName, ['bind', 'singleton', 'scoped', 'instance', 'factory'], true)) {
            return;
        }

        if (count($node->args) < 2) {
            return;
        }

        if (!($node->args[0] instanceof Arg) || !($node->args[1] instanceof Arg)) {
            return;
        }

        $abstract = $this->extractFqn($node->args[0]->value);
        $concrete = $this->extractFqn($node->args[1]->value);

        if ($abstract && $concrete) {
            $this->edgesList[] = new Edge(
                'service_binding',
                $this->getProviderFqn(),
                $concrete,
                $this->relativeFilePath,
                $node->getStartLine(),
                sprintf('binding_type:%s,abstract:%s', $methodName, $abstract),
            );
        }
    }

    private function extractFqn(Node $node): ?string
    {
        if ($node instanceof String_) {
            return $node->value;
        }

        if ($node instanceof ClassConstFetch) {
            if ($node->class instanceof Name) {
                return $node->class->toCodeString();
            }
        }

        if ($node instanceof Closure) {
            return 'Closure';
        }

        return null;
    }

    private function getProviderFqn(): string
    {
        preg_match('#namespace\s+([^;]+)#', $this->sourceContents, $matches);
        $namespace = $matches[1] ?? '';

        preg_match('#class\s+(\w+)#', $this->sourceContents, $matches);
        $className = $matches[1] ?? 'Unknown';

        return $namespace ? sprintf('%s\\%s', $namespace, $className) : $className;
    }
}
