<?php declare(strict_types=1);

namespace PhpParser\Node;

use PhpParser\NodeAbstract;

/**
 * Represents the "?" argument placeholder of the partial function application syntax,
 * e.g. the "?" in "foo(?)". Like VariadicPlaceholder, it occurs in the argument list
 * of a call, in place of an ordinary Arg.
 *
 * @psalm-import-type AttributeArray from \PhpParser\NodeAttributes
 */
class ArgPlaceholder extends NodeAbstract {
    /** @var Identifier|null Parameter name (for named placeholders) */
    public ?Identifier $name;

    /**
     * Create a "?" argument placeholder (partial function application syntax).
     *
     * @param Identifier|null $name Parameter name (for named placeholders)
     * @param \PhpParser\NodeAttributes|AttributeArray $attributes Additional attributes
     */
    public function __construct(?Identifier $name = null, \PhpParser\NodeAttributes|array $attributes = []) {
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
        return 'ArgPlaceholder';
    }
}
