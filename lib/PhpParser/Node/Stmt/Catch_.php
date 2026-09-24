<?php declare(strict_types=1);

namespace PhpParser\Node\Stmt;

use PhpParser\Node;
use PhpParser\Node\Expr;

/**
 * @psalm-import-type AttributeArray from \PhpParser\NodeAttributes
 */
class Catch_ extends Node\Stmt {
    /** @var list<Node\Name> Types of exceptions to catch */
    public array $types;
    /** @var Expr\Variable|null Variable for exception */
    public ?Expr\Variable $var;
    /** @var list<Node\Stmt> Statements */
    public array $stmts;

    /**
     * Constructs a catch node.
     *
     * @param list<Node\Name> $types Types of exceptions to catch
     * @param Expr\Variable|null $var Variable for exception
     * @param list<Node\Stmt> $stmts Statements
     * @param \PhpParser\NodeAttributes|AttributeArray $attributes Additional attributes
     */
    public function __construct(
        array $types, ?Expr\Variable $var = null, array $stmts = [], \PhpParser\NodeAttributes|array $attributes = []
    ) {
        $this->attributes = $attributes instanceof \PhpParser\NodeAttributes ? $attributes : \PhpParser\NodeAttributes::fromArray($attributes);
        $this->types = $types;
        $this->var = $var;
        $this->stmts = $stmts;
    }

    /** @return list<string> */
    public function getSubNodeNames(): array {
        return ['types', 'var', 'stmts'];
    }

    /**
     * Every sub node by name, in getSubNodeNames() order (one call instead of one getSubNode() per name).
     *
     * @return array<string, Node|list<Node|null>|string|int|float|bool|null>
     */
    public function getSubNodes(): array {
        return ['types' => $this->types, 'var' => $this->var, 'stmts' => $this->stmts];
    }

    #[\Override]
    public function traverseSubNodes(\PhpParser\NodeTraverser $traverser): void {
        $this->types = $traverser->traverseArray($this->types);
        if ($traverser->stopTraversal) {
            return;
        }
        if ($this->var !== null) {
            $this->var = $traverser->traverseChildNode($this->var);
            if ($traverser->stopTraversal) {
                return;
            }
        }
        $this->stmts = $traverser->traverseArray($this->stmts);
        if ($traverser->stopTraversal) {
            return;
        }
    }


    /** @return \PhpParser\Node|list<\PhpParser\Node|null>|string|int|float|bool|null */
    public function getSubNode(string $name): mixed {
        return match ($name) {
            'types' => $this->types,
            'var' => $this->var,
            'stmts' => $this->stmts,
            default => throw new \LogicException('Unknown sub node ' . $name . ' on ' . static::class),
        };
    }

    /** @param \PhpParser\Node|list<\PhpParser\Node|null>|string|int|float|bool|null $value */
    public function setSubNode(string $name, mixed $value): void {
        switch ($name) {
            case 'types':
                $this->types = $value;
                return;
            case 'var':
                $this->var = $value;
                return;
            case 'stmts':
                $this->stmts = $value;
                return;
        }
        throw new \LogicException('Unknown sub node ' . $name . ' on ' . static::class);
    }

    public function getType(): string {
        return 'Stmt_Catch';
    }
}
