<?php declare(strict_types=1);

namespace PhpParser\Node\Expr;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\ArgPlaceholder;
use PhpParser\Node\Expr;
use PhpParser\Node\VariadicPlaceholder;

/**
 * @psalm-import-type AttributeArray from \PhpParser\NodeAttributes
 */
class New_ extends CallLike {
    /** @var Node\Name|Expr|Node\Stmt\Class_ Class name */
    public Node $class;
    /** @var list<Arg|VariadicPlaceholder|ArgPlaceholder> Arguments */
    public array $args;

    /**
     * Constructs a function call node.
     *
     * @param Node\Name|Expr|Node\Stmt\Class_ $class Class name (or class node for anonymous classes)
     * @param list<Arg|VariadicPlaceholder|ArgPlaceholder> $args Arguments
     * @param \PhpParser\NodeAttributes|AttributeArray $attributes Additional attributes
     */
    public function __construct(Node $class, array $args = [], \PhpParser\NodeAttributes|array $attributes = []) {
        $this->attributes = $attributes instanceof \PhpParser\NodeAttributes ? $attributes : \PhpParser\NodeAttributes::fromArray($attributes);
        $this->class = $class;
        $this->args = $args;
    }

    /** @return list<string> */
    public function getSubNodeNames(): array {
        return ['class', 'args'];
    }

    /**
     * Every sub node by name, in getSubNodeNames() order (one call instead of one getSubNode() per name).
     *
     * @return array<string, Node|list<Node|null>|string|int|float|bool|null>
     */
    public function getSubNodes(): array {
        return ['class' => $this->class, 'args' => $this->args];
    }

    #[\Override]
    public function traverseSubNodes(\PhpParser\NodeTraverser $traverser): void {
        $this->class = $traverser->traverseRequiredChildNode($this->class);
        if ($traverser->stopTraversal) {
            return;
        }
        $this->args = $traverser->traverseArray($this->args);
        if ($traverser->stopTraversal) {
            return;
        }
    }


    /** @return \PhpParser\Node|list<\PhpParser\Node|null>|string|int|float|bool|null */
    public function getSubNode(string $name): mixed {
        return match ($name) {
            'class' => $this->class,
            'args' => $this->args,
            default => throw new \LogicException('Unknown sub node ' . $name . ' on ' . static::class),
        };
    }

    /** @param \PhpParser\Node|list<\PhpParser\Node|null>|string|int|float|bool|null $value */
    public function setSubNode(string $name, mixed $value): void {
        switch ($name) {
            case 'class':
                $this->class = $value;
                return;
            case 'args':
                $this->args = $value;
                return;
        }
        throw new \LogicException('Unknown sub node ' . $name . ' on ' . static::class);
    }

    public function getType(): string {
        return 'Expr_New';
    }

    /** @return list<\PhpParser\Node\Arg|\PhpParser\Node\VariadicPlaceholder> */
    public function getRawArgs(): array {
        return $this->args;
    }
}
