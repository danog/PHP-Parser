<?php declare(strict_types=1);

namespace PhpParser\Node;

use PhpParser\NodeAbstract;

/**
 * Represents the "..." in "foo(...)" of the first-class callable syntax.
 */
class VariadicPlaceholder extends NodeAbstract {
    /**
     * Create a variadic argument placeholder (first-class callable syntax).
     *
     * @param \PhpParser\NodeAttributes|\PhpParser\NodeAttributes::AttributeArray $attributes Additional attributes
     */
    public function __construct(\PhpParser\NodeAttributes|array $attributes = []) {
        $this->attributes = \PhpParser\NodeAttributes::from($attributes);
    }

    public function getType(): string {
        return 'VariadicPlaceholder';
    }

    public function getSubNodeNames(): array {
        return [];
    }

    public function getSubNode(string $name): mixed {
        return match ($name) {
            default => throw new \LogicException('Unknown sub node ' . $name . ' on ' . static::class),
        };
    }

    public function setSubNode(string $name, mixed $value): void {
        throw new \LogicException('Unknown sub node ' . $name . ' on ' . static::class);
    }
}
