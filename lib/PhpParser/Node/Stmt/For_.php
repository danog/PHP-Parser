<?php declare(strict_types=1);

namespace PhpParser\Node\Stmt;

use PhpParser\Node;

class For_ extends Node\Stmt {
    /** @var list<Node\Expr> Init expressions */
    public array $init;
    /** @var list<Node\Expr> Loop conditions */
    public array $cond;
    /** @var list<Node\Expr> Loop expressions */
    public array $loop;
    /** @var list<Node\Stmt> Statements */
    public array $stmts;

    /**
     * Constructs a for loop node.
     *
     * @param array{
     *     init?: list<Node\Expr>,
     *     cond?: list<Node\Expr>,
     *     loop?: list<Node\Expr>,
     *     stmts?: list<Node\Stmt>,
     * } $subNodes Array of the following optional subnodes:
     *             'init'  => array(): Init expressions
     *             'cond'  => array(): Loop conditions
     *             'loop'  => array(): Loop expressions
     *             'stmts' => array(): Statements
     * @param \PhpParser\NodeAttributes|\PhpParser\NodeAttributes::AttributeArray $attributes Additional attributes
     */
    public function __construct(array $subNodes = [], \PhpParser\NodeAttributes|array $attributes = []) {
        $this->attributes = \PhpParser\NodeAttributes::from($attributes);
        $this->init = $subNodes['init'] ?? [];
        $this->cond = $subNodes['cond'] ?? [];
        $this->loop = $subNodes['loop'] ?? [];
        $this->stmts = $subNodes['stmts'] ?? [];
    }

    /** @return list<string> */
    public function getSubNodeNames(): array {
        return ['init', 'cond', 'loop', 'stmts'];
    }

    /** @return \PhpParser\Node|list<\PhpParser\Node|null>|string|int|float|bool|null */
    public function getSubNode(string $name): mixed {
        return match ($name) {
            'init' => $this->init,
            'cond' => $this->cond,
            'loop' => $this->loop,
            'stmts' => $this->stmts,
            default => throw new \LogicException('Unknown sub node ' . $name . ' on ' . static::class),
        };
    }

    /** @param \PhpParser\Node|list<\PhpParser\Node|null>|string|int|float|bool|null $value */
    public function setSubNode(string $name, mixed $value): void {
        switch ($name) {
            case 'init':
                $this->init = $value;
                return;
            case 'cond':
                $this->cond = $value;
                return;
            case 'loop':
                $this->loop = $value;
                return;
            case 'stmts':
                $this->stmts = $value;
                return;
        }
        throw new \LogicException('Unknown sub node ' . $name . ' on ' . static::class);
    }

    public function getType(): string {
        return 'Stmt_For';
    }
}
