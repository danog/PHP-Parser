<?php declare(strict_types=1);

namespace PhpParser\Node\Expr;

use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr;

/**
 * @psalm-import-type AttributeArray from \PhpParser\NodeAttributes
 */
class Array_ extends Expr {
    // For use in "kind" attribute
    public const KIND_LONG = 1;  // array() syntax
    public const KIND_SHORT = 2; // [] syntax

    /** @var list<ArrayItem|null> Items (null for skipped elements of a destructuring array) */
    public array $items;

    /**
     * Constructs an array node.
     *
     * @param list<ArrayItem|null> $items Items of the array
     * @param \PhpParser\NodeAttributes|AttributeArray $attributes Additional attributes
     */
    public function __construct(array $items = [], \PhpParser\NodeAttributes|array $attributes = []) {
        $this->attributes = $attributes instanceof \PhpParser\NodeAttributes ? $attributes : \PhpParser\NodeAttributes::fromArray($attributes);
        $this->items = $items;
    }

    /** @return list<string> */
    public function getSubNodeNames(): array {
        return ['items'];
    }

    /**
     * Every sub node by name, in getSubNodeNames() order (one call instead of one getSubNode() per name).
     *
     * @return array<string, Node|list<Node|null>|string|int|float|bool|null>
     */
    public function getSubNodes(): array {
        return ['items' => $this->items];
    }

    #[\Override]
    public function traverseSubNodes(\PhpParser\NodeTraverser $traverser): void {
        $this->items = $traverser->traverseArray($this->items);
        if ($traverser->stopTraversal) {
            return;
        }
    }


    /** @return \PhpParser\Node|list<\PhpParser\Node|null>|string|int|float|bool|null */
    public function getSubNode(string $name): mixed {
        return match ($name) {
            'items' => $this->items,
            default => throw new \LogicException('Unknown sub node ' . $name . ' on ' . static::class),
        };
    }

    /** @param \PhpParser\Node|list<\PhpParser\Node|null>|string|int|float|bool|null $value */
    public function setSubNode(string $name, mixed $value): void {
        switch ($name) {
            case 'items':
                $this->items = $value;
                return;
        }
        throw new \LogicException('Unknown sub node ' . $name . ' on ' . static::class);
    }

    public function getType(): string {
        return 'Expr_Array';
    }
}
