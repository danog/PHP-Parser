<?php declare(strict_types=1);

namespace PhpParser\Node\Stmt;

use PhpParser\Node;

/**
 * @psalm-import-type AttributeArray from \PhpParser\NodeAttributes
 */
class Echo_ extends Node\Stmt {
    /** @var list<Node\Expr> Expressions */
    public array $exprs;

    /**
     * Constructs an echo node.
     *
     * @param list<Node\Expr> $exprs Expressions
     * @param \PhpParser\NodeAttributes|AttributeArray $attributes Additional attributes
     */
    public function __construct(array $exprs, \PhpParser\NodeAttributes|array $attributes = []) {
        $this->attributes = $attributes instanceof \PhpParser\NodeAttributes ? $attributes : \PhpParser\NodeAttributes::fromArray($attributes);
        $this->exprs = $exprs;
    }

    /** @return list<string> */
    public function getSubNodeNames(): array {
        return ['exprs'];
    }

    /**
     * Every sub node by name, in getSubNodeNames() order (one call instead of one getSubNode() per name).
     *
     * @return array<string, Node|list<Node|null>|string|int|float|bool|null>
     */
    public function getSubNodes(): array {
        return ['exprs' => $this->exprs];
    }

    #[\Override]
    public function traverseSubNodes(\PhpParser\NodeTraverser $traverser): void {
        $this->exprs = $traverser->traverseArray($this->exprs);
        if ($traverser->stopTraversal) {
            return;
        }
    }


    /** @return \PhpParser\Node|list<\PhpParser\Node|null>|string|int|float|bool|null */
    public function getSubNode(string $name): mixed {
        return match ($name) {
            'exprs' => $this->exprs,
            default => throw new \LogicException('Unknown sub node ' . $name . ' on ' . static::class),
        };
    }

    /** @param \PhpParser\Node|list<\PhpParser\Node|null>|string|int|float|bool|null $value */
    public function setSubNode(string $name, mixed $value): void {
        switch ($name) {
            case 'exprs':
                $this->exprs = $value;
                return;
        }
        throw new \LogicException('Unknown sub node ' . $name . ' on ' . static::class);
    }

    public function getType(): string {
        return 'Stmt_Echo';
    }
}
