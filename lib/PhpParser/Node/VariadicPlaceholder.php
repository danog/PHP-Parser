<?php declare(strict_types=1);

namespace PhpParser\Node;

use PhpParser\NodeAbstract;

/**
 * Represents the "..." in "foo(...)" of the first-class callable syntax.
 * @psalm-import-type AttributeArray from \PhpParser\NodeAttributes
 */
class VariadicPlaceholder extends NodeAbstract {
    /**
     * Create a variadic argument placeholder (first-class callable syntax).
     *
     * @param \PhpParser\NodeAttributes|AttributeArray $attributes Additional attributes
     */
    public function __construct(\PhpParser\NodeAttributes|array $attributes = []) {
        $this->attributes = \PhpParser\NodeAttributes::from($attributes);
    }

    public function getType(): string {
        return 'VariadicPlaceholder';
    }

    /** @return list<string> */
    public function getSubNodeNames(): array {
        return [];
    }

    /** @return \PhpParser\Node|list<\PhpParser\Node|null>|string|int|float|bool|null */
    public function getSubNode(string $name): mixed {
        return match ($name) {
            default => throw new \LogicException('Unknown sub node ' . $name . ' on ' . static::class),
        };
    }

    /** @param \PhpParser\Node|list<\PhpParser\Node|null>|string|int|float|bool|null $value */
    public function setSubNode(string $name, mixed $value): void {
        throw new \LogicException('Unknown sub node ' . $name . ' on ' . static::class);
    }
}
