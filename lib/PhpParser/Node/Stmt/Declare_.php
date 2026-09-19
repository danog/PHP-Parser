<?php declare(strict_types=1);

namespace PhpParser\Node\Stmt;

use PhpParser\Node;
use PhpParser\Node\DeclareItem;

/**
 * @psalm-import-type AttributeArray from \PhpParser\NodeAttributes
 */
class Declare_ extends Node\Stmt {
    /** @var list<DeclareItem> List of declares */
    public array $declares;
    /** @var list<Node\Stmt>|null Statements */
    public ?array $stmts;

    /**
     * Constructs a declare node.
     *
     * @param list<DeclareItem> $declares List of declares
     * @param list<Node\Stmt>|null $stmts Statements
     * @param \PhpParser\NodeAttributes|AttributeArray $attributes Additional attributes
     */
    public function __construct(array $declares, ?array $stmts = null, \PhpParser\NodeAttributes|array $attributes = []) {
        $this->attributes = \PhpParser\NodeAttributes::from($attributes);
        $this->declares = $declares;
        $this->stmts = $stmts;
    }

    /** @return list<string> */
    public function getSubNodeNames(): array {
        return ['declares', 'stmts'];
    }

    /** @return \PhpParser\Node|list<\PhpParser\Node|null>|string|int|float|bool|null */
    public function getSubNode(string $name): mixed {
        return match ($name) {
            'declares' => $this->declares,
            'stmts' => $this->stmts,
            default => throw new \LogicException('Unknown sub node ' . $name . ' on ' . static::class),
        };
    }

    /** @param \PhpParser\Node|list<\PhpParser\Node|null>|string|int|float|bool|null $value */
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
