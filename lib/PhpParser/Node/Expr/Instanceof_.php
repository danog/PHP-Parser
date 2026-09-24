<?php declare(strict_types=1);

namespace PhpParser\Node\Expr;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Name;

/**
 * @psalm-import-type AttributeArray from \PhpParser\NodeAttributes
 */
class Instanceof_ extends Expr {
    /** @var Expr Expression */
    public Expr $expr;
    /** @var Name|Expr Class name */
    public Node $class;

    /**
     * Constructs an instanceof check node.
     *
     * @param Expr $expr Expression
     * @param Name|Expr $class Class name
     * @param \PhpParser\NodeAttributes|AttributeArray $attributes Additional attributes
     */
    public function __construct(Expr $expr, Node $class, \PhpParser\NodeAttributes|array $attributes = []) {
        $this->attributes = $attributes instanceof \PhpParser\NodeAttributes ? $attributes : \PhpParser\NodeAttributes::fromArray($attributes);
        $this->expr = $expr;
        $this->class = $class;
    }

    /** @return list<string> */
    public function getSubNodeNames(): array {
        return ['expr', 'class'];
    }

    /**
     * Every sub node by name, in getSubNodeNames() order (one call instead of one getSubNode() per name).
     *
     * @return array<string, Node|list<Node|null>|string|int|float|bool|null>
     */
    public function getSubNodes(): array {
        return ['expr' => $this->expr, 'class' => $this->class];
    }

    #[\Override]
    public function traverseSubNodes(\PhpParser\NodeTraverser $traverser): void {
        $this->expr = $traverser->traverseRequiredChildNode($this->expr);
        if ($traverser->stopTraversal) {
            return;
        }
        $this->class = $traverser->traverseRequiredChildNode($this->class);
        if ($traverser->stopTraversal) {
            return;
        }
    }


    /** @return \PhpParser\Node|list<\PhpParser\Node|null>|string|int|float|bool|null */
    public function getSubNode(string $name): mixed {
        return match ($name) {
            'expr' => $this->expr,
            'class' => $this->class,
            default => throw new \LogicException('Unknown sub node ' . $name . ' on ' . static::class),
        };
    }

    /** @param \PhpParser\Node|list<\PhpParser\Node|null>|string|int|float|bool|null $value */
    public function setSubNode(string $name, mixed $value): void {
        switch ($name) {
            case 'expr':
                $this->expr = $value;
                return;
            case 'class':
                $this->class = $value;
                return;
        }
        throw new \LogicException('Unknown sub node ' . $name . ' on ' . static::class);
    }

    public function getType(): string {
        return 'Expr_Instanceof';
    }
}
