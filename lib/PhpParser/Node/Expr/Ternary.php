<?php declare(strict_types=1);

namespace PhpParser\Node\Expr;

use PhpParser\Node\Expr;

/**
 * @psalm-import-type AttributeArray from \PhpParser\NodeAttributes
 */
class Ternary extends Expr {
    /** @var Expr Condition */
    public Expr $cond;
    /** @var null|Expr Expression for true */
    public ?Expr $if;
    /** @var Expr Expression for false */
    public Expr $else;

    /**
     * Constructs a ternary operator node.
     *
     * @param Expr $cond Condition
     * @param null|Expr $if Expression for true
     * @param Expr $else Expression for false
     * @param \PhpParser\NodeAttributes|AttributeArray $attributes Additional attributes
     */
    public function __construct(Expr $cond, ?Expr $if, Expr $else, \PhpParser\NodeAttributes|array $attributes = []) {
        $this->attributes = $attributes instanceof \PhpParser\NodeAttributes ? $attributes : \PhpParser\NodeAttributes::fromArray($attributes);
        $this->cond = $cond;
        $this->if = $if;
        $this->else = $else;
    }

    /** @return list<string> */
    public function getSubNodeNames(): array {
        return ['cond', 'if', 'else'];
    }

    /**
     * Every sub node by name, in getSubNodeNames() order (one call instead of one getSubNode() per name).
     *
     * @return array<string, Node|list<Node|null>|string|int|float|bool|null>
     */
    public function getSubNodes(): array {
        return ['cond' => $this->cond, 'if' => $this->if, 'else' => $this->else];
    }

    #[\Override]
    public function traverseSubNodes(\PhpParser\NodeTraverser $traverser): void {
        $this->cond = $traverser->traverseRequiredChildNode($this->cond);
        if ($traverser->stopTraversal) {
            return;
        }
        if ($this->if !== null) {
            $this->if = $traverser->traverseChildNode($this->if);
            if ($traverser->stopTraversal) {
                return;
            }
        }
        $this->else = $traverser->traverseRequiredChildNode($this->else);
        if ($traverser->stopTraversal) {
            return;
        }
    }


    /** @return \PhpParser\Node|list<\PhpParser\Node|null>|string|int|float|bool|null */
    public function getSubNode(string $name): mixed {
        return match ($name) {
            'cond' => $this->cond,
            'if' => $this->if,
            'else' => $this->else,
            default => throw new \LogicException('Unknown sub node ' . $name . ' on ' . static::class),
        };
    }

    /** @param \PhpParser\Node|list<\PhpParser\Node|null>|string|int|float|bool|null $value */
    public function setSubNode(string $name, mixed $value): void {
        switch ($name) {
            case 'cond':
                $this->cond = $value;
                return;
            case 'if':
                $this->if = $value;
                return;
            case 'else':
                $this->else = $value;
                return;
        }
        throw new \LogicException('Unknown sub node ' . $name . ' on ' . static::class);
    }

    public function getType(): string {
        return 'Expr_Ternary';
    }
}
