<?php declare(strict_types=1);

namespace PhpParser\Node;

use PhpParser\Node;
use PhpParser\NodeAbstract;

/**
 * @psalm-import-type AttributeArray from \PhpParser\NodeAttributes
 */
class DeclareItem extends NodeAbstract {
    /** @var Node\Identifier Key */
    public Identifier $key;
    /** @var Node\Expr Value */
    public Expr $value;

    /**
     * Constructs a declare key=>value pair node.
     *
     * @param string|Node\Identifier $key Key
     * @param Node\Expr $value Value
     * @param \PhpParser\NodeAttributes|AttributeArray $attributes Additional attributes
     */
    public function __construct($key, Node\Expr $value, \PhpParser\NodeAttributes|array $attributes = []) {
        $this->attributes = \PhpParser\NodeAttributes::from($attributes);
        $this->key = \is_string($key) ? new Node\Identifier($key) : $key;
        $this->value = $value;
    }

    /** @return list<string> */
    public function getSubNodeNames(): array {
        return ['key', 'value'];
    }

    /** @return \PhpParser\Node|list<\PhpParser\Node|null>|string|int|float|bool|null */
    public function getSubNode(string $name): mixed {
        return match ($name) {
            'key' => $this->key,
            'value' => $this->value,
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
        }
        throw new \LogicException('Unknown sub node ' . $name . ' on ' . static::class);
    }

    public function getType(): string {
        return 'DeclareItem';
    }
}

// @deprecated compatibility alias
class_alias(DeclareItem::class, Stmt\DeclareDeclare::class);
