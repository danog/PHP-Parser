<?php declare(strict_types=1);

namespace PhpParser\Node;

/**
 * @psalm-import-type AttributeArray from \PhpParser\NodeAttributes
 */
class IntersectionType extends ComplexType {
    /** @var list<Identifier|Name> Types */
    public array $types;

    /**
     * Constructs an intersection type.
     *
     * @param list<Identifier|Name> $types Types
     * @param \PhpParser\NodeAttributes|AttributeArray $attributes Additional attributes
     */
    public function __construct(array $types, \PhpParser\NodeAttributes|array $attributes = []) {
        $this->attributes = \PhpParser\NodeAttributes::from($attributes);
        $this->types = $types;
    }

    /** @return list<string> */
    public function getSubNodeNames(): array {
        return ['types'];
    }

    /** @return \PhpParser\Node|list<\PhpParser\Node|null>|string|int|float|bool|null */
    public function getSubNode(string $name): mixed {
        return match ($name) {
            'types' => $this->types,
            default => throw new \LogicException('Unknown sub node ' . $name . ' on ' . static::class),
        };
    }

    /** @param \PhpParser\Node|list<\PhpParser\Node|null>|string|int|float|bool|null $value */
    public function setSubNode(string $name, mixed $value): void {
        switch ($name) {
            case 'types':
                $this->types = $value;
                return;
        }
        throw new \LogicException('Unknown sub node ' . $name . ' on ' . static::class);
    }

    public function getType(): string {
        return 'IntersectionType';
    }
}
