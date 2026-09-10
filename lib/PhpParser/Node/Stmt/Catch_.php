<?php declare(strict_types=1);

namespace PhpParser\Node\Stmt;

use PhpParser\Node;
use PhpParser\Node\Expr;

class Catch_ extends Node\Stmt {
    /** @var Node\Name[] Types of exceptions to catch */
    public array $types;
    /** @var Expr\Variable|null Variable for exception */
    public ?Expr\Variable $var;
    /** @var Node\Stmt[] Statements */
    public array $stmts;

    /**
     * Constructs a catch node.
     *
     * @param Node\Name[] $types Types of exceptions to catch
     * @param Expr\Variable|null $var Variable for exception
     * @param Node\Stmt[] $stmts Statements
     * @param \PhpParser\NodeAttributes|\PhpParser\NodeAttributes::AttributeArray $attributes Additional attributes
     */
    public function __construct(
        array $types, ?Expr\Variable $var = null, array $stmts = [], \PhpParser\NodeAttributes|array $attributes = []
    ) {
        $this->attributes = \PhpParser\NodeAttributes::from($attributes);
        $this->types = $types;
        $this->var = $var;
        $this->stmts = $stmts;
    }

    public function getSubNodeNames(): array {
        return ['types', 'var', 'stmts'];
    }

    public function getSubNode(string $name): mixed {
        return match ($name) {
            'types' => $this->types,
            'var' => $this->var,
            'stmts' => $this->stmts,
            default => throw new \LogicException('Unknown sub node ' . $name . ' on ' . static::class),
        };
    }

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
