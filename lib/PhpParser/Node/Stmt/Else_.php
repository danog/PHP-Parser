<?php declare(strict_types=1);

namespace PhpParser\Node\Stmt;

use PhpParser\Node;

/**
 * @psalm-import-type AttributeArray from \PhpParser\NodeAttributes
 */
class Else_ extends Node\Stmt {
    /** @var list<Node\Stmt> Statements */
    public array $stmts;

    /**
     * Constructs an else node.
     *
     * @param list<Node\Stmt> $stmts Statements
     * @param \PhpParser\NodeAttributes|AttributeArray $attributes Additional attributes
     */
    public function __construct(array $stmts = [], \PhpParser\NodeAttributes|array $attributes = []) {
        $this->attributes = $attributes instanceof \PhpParser\NodeAttributes ? $attributes : \PhpParser\NodeAttributes::fromArray($attributes);
        $this->stmts = $stmts;
    }

    /** @return list<string> */
    public function getSubNodeNames(): array {
        return ['stmts'];
    }

    /**
     * Every sub node by name, in getSubNodeNames() order (one call instead of one getSubNode() per name).
     *
     * @return array<string, Node|list<Node|null>|string|int|float|bool|null>
     */
    public function getSubNodes(): array {
        return ['stmts' => $this->stmts];
    }

    #[\Override]
    public function traverseSubNodes(\PhpParser\NodeTraverser $traverser): void {
        $this->stmts = $traverser->traverseArray($this->stmts);
        if ($traverser->stopTraversal) {
            return;
        }
    }


    /** @return \PhpParser\Node|list<\PhpParser\Node|null>|string|int|float|bool|null */
    public function getSubNode(string $name): mixed {
        return match ($name) {
            'stmts' => $this->stmts,
            default => throw new \LogicException('Unknown sub node ' . $name . ' on ' . static::class),
        };
    }

    /** @param \PhpParser\Node|list<\PhpParser\Node|null>|string|int|float|bool|null $value */
    public function setSubNode(string $name, mixed $value): void {
        switch ($name) {
            case 'stmts':
                $this->stmts = $value;
                return;
        }
        throw new \LogicException('Unknown sub node ' . $name . ' on ' . static::class);
    }

    public function getType(): string {
        return 'Stmt_Else';
    }
}
