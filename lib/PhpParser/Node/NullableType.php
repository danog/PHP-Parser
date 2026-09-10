<?php declare(strict_types=1);

namespace PhpParser\Node;

use PhpParser\Node;

class NullableType extends ComplexType {
    /** @var Identifier|Name Type */
    public Node $type;

    /**
     * Constructs a nullable type (wrapping another type).
     *
     * @param Identifier|Name $type Type
     * @param array<string, mixed> $attributes Additional attributes
     */
    public function __construct(Node $type, array $attributes = []) {
        $this->attributes = $attributes;
        $this->type = $type;
    }

    public function getSubNodeNames(): array {
        return ['type'];
    }

    public function getSubNode(string $name): mixed {
        return match ($name) {
            'type' => $this->type,
            default => throw new \LogicException('Unknown sub node ' . $name . ' on ' . static::class),
        };
    }

    public function setSubNode(string $name, mixed $value): void {
        switch ($name) {
            case 'type':
                $this->type = $value;
                return;
        }
        throw new \LogicException('Unknown sub node ' . $name . ' on ' . static::class);
    }

    public function getType(): string {
        return 'NullableType';
    }
}
