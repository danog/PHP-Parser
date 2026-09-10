<?php declare(strict_types=1);

namespace PhpParser\Node\Expr;

use PhpParser\Node;

class Throw_ extends Node\Expr {
    /** @var Node\Expr Expression */
    public Node\Expr $expr;

    /**
     * Constructs a throw expression node.
     *
     * @param Node\Expr $expr Expression
     * @param \PhpParser\NodeAttributes|\PhpParser\NodeAttributes::AttributeArray $attributes Additional attributes
     */
    public function __construct(Node\Expr $expr, \PhpParser\NodeAttributes|array $attributes = []) {
        $this->attributes = \PhpParser\NodeAttributes::from($attributes);
        $this->expr = $expr;
    }

    public function getSubNodeNames(): array {
        return ['expr'];
    }

    public function getSubNode(string $name): mixed {
        return match ($name) {
            'expr' => $this->expr,
            default => throw new \LogicException('Unknown sub node ' . $name . ' on ' . static::class),
        };
    }

    public function setSubNode(string $name, mixed $value): void {
        switch ($name) {
            case 'expr':
                $this->expr = $value;
                return;
        }
        throw new \LogicException('Unknown sub node ' . $name . ' on ' . static::class);
    }

    public function getType(): string {
        return 'Expr_Throw';
    }
}
