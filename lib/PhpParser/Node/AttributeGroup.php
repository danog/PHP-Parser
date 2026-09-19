<?php declare(strict_types=1);

namespace PhpParser\Node;

use PhpParser\NodeAbstract;

/**
 * @psalm-import-type AttributeArray from \PhpParser\NodeAttributes
 */
class AttributeGroup extends NodeAbstract {
    /** @var list<Attribute> Attributes */
    public array $attrs;

    /**
     * @param list<Attribute> $attrs PHP attributes
     * @param \PhpParser\NodeAttributes|AttributeArray $attributes Additional node attributes
     */
    public function __construct(array $attrs, \PhpParser\NodeAttributes|array $attributes = []) {
        $this->attributes = \PhpParser\NodeAttributes::from($attributes);
        $this->attrs = $attrs;
    }

    /** @return list<string> */
    public function getSubNodeNames(): array {
        return ['attrs'];
    }

    /** @return \PhpParser\Node|list<\PhpParser\Node|null>|string|int|float|bool|null */
    public function getSubNode(string $name): mixed {
        return match ($name) {
            'attrs' => $this->attrs,
            default => throw new \LogicException('Unknown sub node ' . $name . ' on ' . static::class),
        };
    }

    /** @param \PhpParser\Node|list<\PhpParser\Node|null>|string|int|float|bool|null $value */
    public function setSubNode(string $name, mixed $value): void {
        switch ($name) {
            case 'attrs':
                $this->attrs = $value;
                return;
        }
        throw new \LogicException('Unknown sub node ' . $name . ' on ' . static::class);
    }

    public function getType(): string {
        return 'AttributeGroup';
    }
}
