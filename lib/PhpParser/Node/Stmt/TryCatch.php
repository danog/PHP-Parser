<?php declare(strict_types=1);

namespace PhpParser\Node\Stmt;

use PhpParser\Node;

class TryCatch extends Node\Stmt {
    /** @var Node\Stmt[] Statements */
    public array $stmts;
    /** @var Catch_[] Catches */
    public array $catches;
    /** @var null|Finally_ Optional finally node */
    public ?Finally_ $finally;

    /**
     * Constructs a try catch node.
     *
     * @param Node\Stmt[] $stmts Statements
     * @param Catch_[] $catches Catches
     * @param null|Finally_ $finally Optional finally node
     * @param array<string, mixed> $attributes Additional attributes
     */
    public function __construct(array $stmts, array $catches, ?Finally_ $finally = null, array $attributes = []) {
        $this->attributes = $attributes;
        $this->stmts = $stmts;
        $this->catches = $catches;
        $this->finally = $finally;
    }

    public function getSubNodeNames(): array {
        return ['stmts', 'catches', 'finally'];
    }

    public function getSubNode(string $name): mixed {
        return match ($name) {
            'stmts' => $this->stmts,
            'catches' => $this->catches,
            'finally' => $this->finally,
            default => throw new \LogicException('Unknown sub node ' . $name . ' on ' . static::class),
        };
    }

    public function setSubNode(string $name, mixed $value): void {
        switch ($name) {
            case 'stmts':
                $this->stmts = $value;
                return;
            case 'catches':
                $this->catches = $value;
                return;
            case 'finally':
                $this->finally = $value;
                return;
        }
        throw new \LogicException('Unknown sub node ' . $name . ' on ' . static::class);
    }

    public function getType(): string {
        return 'Stmt_TryCatch';
    }
}
