<?php declare(strict_types=1);

namespace PhpParser\Node;

use PhpParser\NodeAbstract;

/**
 * @psalm-import-type AttributeArray from \PhpParser\NodeAttributes
 */
class ArrayItem extends NodeAbstract {
    /** @var null|Expr Key */
    public ?Expr $key;
    /** @var Expr Value */
    public Expr $value;
    /** @var bool Whether to assign by reference */
    public bool $byRef;
    /** @var bool Whether to unpack the argument */
    public bool $unpack;

    /**
     * Constructs an array item node.
     *
     * @param Expr $value Value
     * @param null|Expr $key Key
     * @param bool $byRef Whether to assign by reference
     * @param \PhpParser\NodeAttributes|AttributeArray $attributes Additional attributes
     */
    public function __construct(Expr $value, ?Expr $key = null, bool $byRef = false, \PhpParser\NodeAttributes|array $attributes = [], bool $unpack = false) {
        $this->attributes = \PhpParser\NodeAttributes::from($attributes);
        $this->key = $key;
        $this->value = $value;
        $this->byRef = $byRef;
        $this->unpack = $unpack;
    }

    /** @return list<string> */
    public function getSubNodeNames(): array {
        return ['key', 'value', 'byRef', 'unpack'];
    }

    /** @return \PhpParser\Node|list<\PhpParser\Node|null>|string|int|float|bool|null */
    public function getSubNode(string $name): mixed {
        return match ($name) {
            'key' => $this->key,
            'value' => $this->value,
            'byRef' => $this->byRef,
            'unpack' => $this->unpack,
            default => throw new \LogicException('Unknown sub node ' . $name . ' on ' . static::class),
        };
    }

    /** @param \PhpParser\Node|list<\PhpParser\Node|null>|string|int|float|bool|null $value */
    public function setSubNode(string $name, mixed $value): void {
        switch ($name) {
            case 'key':
                $this->key = $value;
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
        return 'ArrayItem';
    }
}

// @deprecated compatibility alias
class_alias(ArrayItem::class, Expr\ArrayItem::class);
