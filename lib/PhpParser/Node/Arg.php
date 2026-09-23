<?php declare(strict_types=1);

namespace PhpParser\Node;

use PhpParser\NodeAbstract;

/**
 * @psalm-import-type AttributeArray from \PhpParser\NodeAttributes
 */
class Arg extends NodeAbstract {
    /** @var Identifier|null Parameter name (for named parameters) */
    public ?Identifier $name;
    /** @var Expr Value to pass */
    public Expr $value;
    /** @var bool Whether to pass by ref */
    public bool $byRef;
    /** @var bool Whether to unpack the argument */
    public bool $unpack;

    /**
     * Constructs a function call argument node.
     *
     * @param Expr $value Value to pass
     * @param bool $byRef Whether to pass by ref
     * @param bool $unpack Whether to unpack the argument
     * @param \PhpParser\NodeAttributes|AttributeArray $attributes Additional attributes
     * @param Identifier|null $name Parameter name (for named parameters)
     */
    public function __construct(
        Expr $value, bool $byRef = false, bool $unpack = false, \PhpParser\NodeAttributes|array $attributes = [],
        ?Identifier $name = null
    ) {
        $this->attributes = $attributes instanceof \PhpParser\NodeAttributes ? $attributes : \PhpParser\NodeAttributes::fromArray($attributes);
        $this->name = $name;
        $this->value = $value;
        $this->byRef = $byRef;
        $this->unpack = $unpack;
    }

    /** @return list<string> */
    public function getSubNodeNames(): array {
        return ['name', 'value', 'byRef', 'unpack'];
    }

    /**
     * Every sub node by name, in getSubNodeNames() order (one call instead of one getSubNode() per name).
     *
     * @return array<string, Node|list<Node|null>|string|int|float|bool|null>
     */
    public function getSubNodes(): array {
        return ['name' => $this->name, 'value' => $this->value, 'byRef' => $this->byRef, 'unpack' => $this->unpack];
    }


    /** @return \PhpParser\Node|list<\PhpParser\Node|null>|string|int|float|bool|null */
    public function getSubNode(string $name): mixed {
        return match ($name) {
            'name' => $this->name,
            'value' => $this->value,
            'byRef' => $this->byRef,
            'unpack' => $this->unpack,
            default => throw new \LogicException('Unknown sub node ' . $name . ' on ' . static::class),
        };
    }

    /** @param \PhpParser\Node|list<\PhpParser\Node|null>|string|int|float|bool|null $value */
    public function setSubNode(string $name, mixed $value): void {
        switch ($name) {
            case 'name':
                $this->name = $value;
                return;
            case 'value':
                $this->value = $value;
                return;
            case 'byRef':
                $this->byRef = $value;
                return;
            case 'unpack':
                $this->unpack = $value;
                return;
        }
        throw new \LogicException('Unknown sub node ' . $name . ' on ' . static::class);
    }

    public function getType(): string {
        return 'Arg';
    }
}
