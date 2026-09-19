<?php declare(strict_types=1);

namespace PhpParser\Node\Expr;

use PhpParser\Node\Expr;

/**
 * @psalm-import-type AttributeArray from \PhpParser\NodeAttributes
 */
class Variable extends Expr {
    /** @var string|Expr Name */
    public $name;

    /**
     * Constructs a variable node.
     *
     * @param string|Expr $name Name
     * @param \PhpParser\NodeAttributes|AttributeArray $attributes Additional attributes
     */
    public function __construct($name, \PhpParser\NodeAttributes|array $attributes = []) {
        $this->attributes = \PhpParser\NodeAttributes::from($attributes);
        $this->name = $name;
    }

    /** @return list<string> */
    public function getSubNodeNames(): array {
        return ['name'];
    }

    /** @return \PhpParser\Node|list<\PhpParser\Node|null>|string|int|float|bool|null */
    public function getSubNode(string $name): mixed {
        return match ($name) {
            'name' => $this->name,
            default => throw new \LogicException('Unknown sub node ' . $name . ' on ' . static::class),
        };
    }

    /** @param \PhpParser\Node|list<\PhpParser\Node|null>|string|int|float|bool|null $value */
    public function setSubNode(string $name, mixed $value): void {
        switch ($name) {
            case 'name':
                $this->name = $value;
                return;
        }
        throw new \LogicException('Unknown sub node ' . $name . ' on ' . static::class);
    }

    public function getType(): string {
        return 'Expr_Variable';
    }
}
