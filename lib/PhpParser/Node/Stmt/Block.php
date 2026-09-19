<?php declare(strict_types=1);

namespace PhpParser\Node\Stmt;

use PhpParser\Node\Stmt;

/**
 * @psalm-import-type AttributeArray from \PhpParser\NodeAttributes
 */
class Block extends Stmt {
    /** @var list<Stmt> Statements */
    public array $stmts;

    /**
     * A block of statements.
     *
     * @param list<Stmt> $stmts Statements
     * @param \PhpParser\NodeAttributes|AttributeArray $attributes Additional attributes
     */
    public function __construct(array $stmts, \PhpParser\NodeAttributes|array $attributes = []) {
        $this->attributes = \PhpParser\NodeAttributes::from($attributes);
        $this->stmts = $stmts;
    }

    public function getType(): string {
        return 'Stmt_Block';
    }

    /** @return list<string> */
    public function getSubNodeNames(): array {
        return ['stmts'];
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
}
