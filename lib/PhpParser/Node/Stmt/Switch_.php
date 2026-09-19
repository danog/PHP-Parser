<?php declare(strict_types=1);

namespace PhpParser\Node\Stmt;

use PhpParser\Node;

/**
 * @psalm-import-type AttributeArray from \PhpParser\NodeAttributes
 */
class Switch_ extends Node\Stmt {
    /** @var Node\Expr Condition */
    public Node\Expr $cond;
    /** @var list<Case_> Case list */
    public array $cases;

    /**
     * Constructs a case node.
     *
     * @param Node\Expr $cond Condition
     * @param list<Case_> $cases Case list
     * @param \PhpParser\NodeAttributes|AttributeArray $attributes Additional attributes
     */
    public function __construct(Node\Expr $cond, array $cases, \PhpParser\NodeAttributes|array $attributes = []) {
        $this->attributes = \PhpParser\NodeAttributes::from($attributes);
        $this->cond = $cond;
        $this->cases = $cases;
    }

    /** @return list<string> */
    public function getSubNodeNames(): array {
        return ['cond', 'cases'];
    }

    /** @return \PhpParser\Node|list<\PhpParser\Node|null>|string|int|float|bool|null */
    public function getSubNode(string $name): mixed {
        return match ($name) {
            'cond' => $this->cond,
            'cases' => $this->cases,
            default => throw new \LogicException('Unknown sub node ' . $name . ' on ' . static::class),
        };
    }

    /** @param \PhpParser\Node|list<\PhpParser\Node|null>|string|int|float|bool|null $value */
    public function setSubNode(string $name, mixed $value): void {
        switch ($name) {
            case 'cond':
                $this->cond = $value;
                return;
            case 'cases':
                $this->cases = $value;
                return;
        }
        throw new \LogicException('Unknown sub node ' . $name . ' on ' . static::class);
    }

    public function getType(): string {
        return 'Stmt_Switch';
    }
}
