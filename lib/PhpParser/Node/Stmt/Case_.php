<?php declare(strict_types=1);

namespace PhpParser\Node\Stmt;

use PhpParser\Node;

/**
 * @psalm-import-type AttributeArray from \PhpParser\NodeAttributes
 */
class Case_ extends Node\Stmt {
    /** @var null|Node\Expr Condition (null for default) */
    public ?Node\Expr $cond;
    /** @var list<Node\Stmt> Statements */
    public array $stmts;

    /**
     * Constructs a case node.
     *
     * @param null|Node\Expr $cond Condition (null for default)
     * @param list<Node\Stmt> $stmts Statements
     * @param \PhpParser\NodeAttributes|AttributeArray $attributes Additional attributes
     */
    public function __construct(?Node\Expr $cond, array $stmts = [], \PhpParser\NodeAttributes|array $attributes = []) {
        $this->attributes = $attributes instanceof \PhpParser\NodeAttributes ? $attributes : \PhpParser\NodeAttributes::fromArray($attributes);
        $this->cond = $cond;
        $this->stmts = $stmts;
    }

    /** @return list<string> */
    public function getSubNodeNames(): array {
        return ['cond', 'stmts'];
    }

    /**
     * Every sub node by name, in getSubNodeNames() order (one call instead of one getSubNode() per name).
     *
     * @return array<string, Node|list<Node|null>|string|int|float|bool|null>
     */
    public function getSubNodes(): array {
        return ['cond' => $this->cond, 'stmts' => $this->stmts];
    }

    #[\Override]
    public function traverseSubNodes(\PhpParser\NodeTraverser $traverser): void {
        if ($this->cond !== null) {
            $this->cond = $traverser->traverseChildNode($this->cond);
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
            'cond' => $this->cond,
            'stmts' => $this->stmts,
            default => throw new \LogicException('Unknown sub node ' . $name . ' on ' . static::class),
        };
    }

    /** @param \PhpParser\Node|list<\PhpParser\Node|null>|string|int|float|bool|null $value */
    public function setSubNode(string $name, mixed $value): void {
        switch ($name) {
            case 'cond':
                $this->cond = $value;
                return;
            case 'stmts':
                $this->stmts = $value;
                return;
        }
        throw new \LogicException('Unknown sub node ' . $name . ' on ' . static::class);
    }

    public function getType(): string {
        return 'Stmt_Case';
    }
}
