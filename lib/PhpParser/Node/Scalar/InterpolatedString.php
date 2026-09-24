<?php declare(strict_types=1);

namespace PhpParser\Node\Scalar;

use PhpParser\Node\Expr;
use PhpParser\Node\InterpolatedStringPart;
use PhpParser\Node\Scalar;

/**
 * @psalm-import-type AttributeArray from \PhpParser\NodeAttributes
 */
class InterpolatedString extends Scalar {
    /** @var list<Expr|InterpolatedStringPart> list of string parts */
    public array $parts;

    /**
     * Constructs an interpolated string node.
     *
     * @param list<Expr|InterpolatedStringPart> $parts Interpolated string parts
     * @param \PhpParser\NodeAttributes|AttributeArray $attributes Additional attributes
     */
    public function __construct(array $parts, \PhpParser\NodeAttributes|array $attributes = []) {
        $this->attributes = $attributes instanceof \PhpParser\NodeAttributes ? $attributes : \PhpParser\NodeAttributes::fromArray($attributes);
        $this->parts = $parts;
    }

    /** @return list<string> */
    public function getSubNodeNames(): array {
        return ['parts'];
    }

    /**
     * Every sub node by name, in getSubNodeNames() order (one call instead of one getSubNode() per name).
     *
     * @return array<string, Node|list<Node|null>|string|int|float|bool|null>
     */
    public function getSubNodes(): array {
        return ['parts' => $this->parts];
    }

    #[\Override]
    public function traverseSubNodes(\PhpParser\NodeTraverser $traverser): void {
        $this->parts = $traverser->traverseArray($this->parts);
        if ($traverser->stopTraversal) {
            return;
        }
    }


    /** @return \PhpParser\Node|list<\PhpParser\Node|null>|string|int|float|bool|null */
    public function getSubNode(string $name): mixed {
        return match ($name) {
            'parts' => $this->parts,
            default => throw new \LogicException('Unknown sub node ' . $name . ' on ' . static::class),
        };
    }

    /** @param \PhpParser\Node|list<\PhpParser\Node|null>|string|int|float|bool|null $value */
    public function setSubNode(string $name, mixed $value): void {
        switch ($name) {
            case 'parts':
                $this->parts = $value;
                return;
        }
        throw new \LogicException('Unknown sub node ' . $name . ' on ' . static::class);
    }

    public function getType(): string {
        return 'Scalar_InterpolatedString';
    }
}

// @deprecated compatibility alias
class_alias(InterpolatedString::class, Encapsed::class);
