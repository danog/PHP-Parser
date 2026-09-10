<?php declare(strict_types=1);

namespace PhpParser\Node\Stmt;

use PhpParser\Node;
use PhpParser\Node\DeclareItem;

class Declare_ extends Node\Stmt {
    /** @var DeclareItem[] List of declares */
    public array $declares;
    /** @var Node\Stmt[]|null Statements */
    public ?array $stmts;

    /**
     * Constructs a declare node.
     *
     * @param DeclareItem[] $declares List of declares
     * @param Node\Stmt[]|null $stmts Statements
     * @param \PhpParser\NodeAttributes|\PhpParser\NodeAttributes::AttributeArray $attributes Additional attributes
     */
    public function __construct(array $declares, ?array $stmts = null, \PhpParser\NodeAttributes|array $attributes = []) {
        $this->attributes = \PhpParser\NodeAttributes::from($attributes);
        $this->declares = $declares;
        $this->stmts = $stmts;
    }

    public function getSubNodeNames(): array {
        return ['declares', 'stmts'];
    }

    public function getSubNode(string $name): mixed {
        return match ($name) {
            'declares' => $this->declares,
            'stmts' => $this->stmts,
            default => throw new \LogicException('Unknown sub node ' . $name . ' on ' . static::class),
        };
    }

    public function setSubNode(string $name, mixed $value): void {
        switch ($name) {
            case 'declares':
                $this->declares = $value;
                return;
            case 'stmts':
                $this->stmts = $value;
                return;
        }
        throw new \LogicException('Unknown sub node ' . $name . ' on ' . static::class);
    }

    public function getType(): string {
        return 'Stmt_Declare';
    }
}
