<?php declare(strict_types=1);

namespace PhpParser\Node\Stmt;

use PhpParser\Node\Stmt;

class Block extends Stmt {
    /** @var Stmt[] Statements */
    public array $stmts;

    /**
     * A block of statements.
     *
     * @param Stmt[] $stmts Statements
     * @param array<string, mixed> $attributes Additional attributes
     */
    public function __construct(array $stmts, array $attributes = []) {
        $this->attributes = $attributes;
        $this->stmts = $stmts;
    }

    public function getType(): string {
        return 'Stmt_Block';
    }

    public function getSubNodeNames(): array {
        return ['stmts'];
    }

    public function getSubNode(string $name): mixed {
        return match ($name) {
            'stmts' => $this->stmts,
            default => throw new \LogicException('Unknown sub node ' . $name . ' on ' . static::class),
        };
    }

    public function setSubNode(string $name, mixed $value): void {
        switch ($name) {
            case 'stmts':
                $this->stmts = $value;
                return;
        }
        throw new \LogicException('Unknown sub node ' . $name . ' on ' . static::class);
    }
}
