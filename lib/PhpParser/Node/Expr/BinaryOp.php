<?php declare(strict_types=1);

namespace PhpParser\Node\Expr;

use PhpParser\Node\Expr;

abstract class BinaryOp extends Expr {
    /** @var Expr The left hand side expression */
    public Expr $left;
    /** @var Expr The right hand side expression */
    public Expr $right;

    /**
     * Constructs a binary operator node.
     *
     * @param Expr $left The left hand side expression
     * @param Expr $right The right hand side expression
     * @param array<string, mixed> $attributes Additional attributes
     */
    public function __construct(Expr $left, Expr $right, array $attributes = []) {
        $this->attributes = $attributes;
        $this->left = $left;
        $this->right = $right;
    }

    public function getSubNodeNames(): array {
        return ['left', 'right'];
    }

    public function getSubNode(string $name): mixed {
        return match ($name) {
            'left' => $this->left,
            'right' => $this->right,
            default => throw new \LogicException('Unknown sub node ' . $name . ' on ' . static::class),
        };
    }

    public function setSubNode(string $name, mixed $value): void {
        switch ($name) {
            case 'left':
                $this->left = $value;
                return;
            case 'right':
                $this->right = $value;
                return;
        }
        throw new \LogicException('Unknown sub node ' . $name . ' on ' . static::class);
    }

    /**
     * Get the operator sigil for this binary operation.
     *
     * In the case there are multiple possible sigils for an operator, this method does not
     * necessarily return the one used in the parsed code.
     */
    abstract public function getOperatorSigil(): string;
}
