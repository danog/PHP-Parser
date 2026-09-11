<?php declare(strict_types=1);

namespace PhpParser\Node\Stmt;

use PhpParser\Node;

class Do_ extends Node\Stmt {
    /** @var list<Node\Stmt> Statements */
    public array $stmts;
    /** @var Node\Expr Condition */
    public Node\Expr $cond;

    /**
     * Constructs a do while node.
     *
     * @param Node\Expr $cond Condition
     * @param list<Node\Stmt> $stmts Statements
     * @param \PhpParser\NodeAttributes|\PhpParser\NodeAttributes::AttributeArray $attributes Additional attributes
     */
    public function __construct(Node\Expr $cond, array $stmts = [], \PhpParser\NodeAttributes|array $attributes = []) {
        $this->attributes = \PhpParser\NodeAttributes::from($attributes);
        $this->cond = $cond;
        $this->stmts = $stmts;
    }

    public function getSubNodeNames(): array {
        return ['stmts', 'cond'];
    }

    /** @return \PhpParser\Node|list<\PhpParser\Node|null>|string|int|float|bool|null */
    public function getSubNode(string $name): mixed {
        return match ($name) {
            'stmts' => $this->stmts,
            'cond' => $this->cond,
            default => throw new \LogicException('Unknown sub node ' . $name . ' on ' . static::class),
        };
    }

    /** @param \PhpParser\Node|list<\PhpParser\Node|null>|string|int|float|bool|null $value */
    public function setSubNode(string $name, mixed $value): void {
        switch ($name) {
            case 'stmts':
                $this->stmts = $value;
                return;
            case 'cond':
                $this->cond = $value;
                return;
        }
        throw new \LogicException('Unknown sub node ' . $name . ' on ' . static::class);
    }

    public function getType(): string {
        return 'Stmt_Do';
    }
}
