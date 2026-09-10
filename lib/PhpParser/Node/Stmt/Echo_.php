<?php declare(strict_types=1);

namespace PhpParser\Node\Stmt;

use PhpParser\Node;

class Echo_ extends Node\Stmt {
    /** @var Node\Expr[] Expressions */
    public array $exprs;

    /**
     * Constructs an echo node.
     *
     * @param Node\Expr[] $exprs Expressions
     * @param array<string, mixed> $attributes Additional attributes
     */
    public function __construct(array $exprs, array $attributes = []) {
        $this->attributes = $attributes;
        $this->exprs = $exprs;
    }

    public function getSubNodeNames(): array {
        return ['exprs'];
    }

    public function getSubNode(string $name): mixed {
        return match ($name) {
            'exprs' => $this->exprs,
            default => throw new \LogicException('Unknown sub node ' . $name . ' on ' . static::class),
        };
    }

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
