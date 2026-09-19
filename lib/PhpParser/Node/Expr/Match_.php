<?php declare(strict_types=1);

namespace PhpParser\Node\Expr;

use PhpParser\Node;
use PhpParser\Node\MatchArm;

/**
 * @psalm-import-type AttributeArray from \PhpParser\NodeAttributes
 */
class Match_ extends Node\Expr {
    /** @var Node\Expr Condition */
    public Node\Expr $cond;
    /** @var list<MatchArm> */
    public array $arms;

    /**
     * @param Node\Expr $cond Condition
     * @param list<MatchArm> $arms
     * @param \PhpParser\NodeAttributes|AttributeArray $attributes Additional attributes
     */
    public function __construct(Node\Expr $cond, array $arms = [], \PhpParser\NodeAttributes|array $attributes = []) {
        $this->attributes = \PhpParser\NodeAttributes::from($attributes);
        $this->cond = $cond;
        $this->arms = $arms;
    }

    /** @return list<string> */
    public function getSubNodeNames(): array {
        return ['cond', 'arms'];
    }

    /** @return \PhpParser\Node|list<\PhpParser\Node|null>|string|int|float|bool|null */
    public function getSubNode(string $name): mixed {
        return match ($name) {
            'cond' => $this->cond,
            'arms' => $this->arms,
            default => throw new \LogicException('Unknown sub node ' . $name . ' on ' . static::class),
        };
    }

    /** @param \PhpParser\Node|list<\PhpParser\Node|null>|string|int|float|bool|null $value */
    public function setSubNode(string $name, mixed $value): void {
        switch ($name) {
            case 'cond':
                $this->cond = $value;
                return;
            case 'arms':
                $this->arms = $value;
                return;
        }
        throw new \LogicException('Unknown sub node ' . $name . ' on ' . static::class);
    }

    public function getType(): string {
        return 'Expr_Match';
    }
}
