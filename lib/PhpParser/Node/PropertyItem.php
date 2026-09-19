<?php declare(strict_types=1);

namespace PhpParser\Node;

use PhpParser\Node;
use PhpParser\NodeAbstract;

/**
 * @psalm-import-type AttributeArray from \PhpParser\NodeAttributes
 */
class PropertyItem extends NodeAbstract {
    /** @var Node\VarLikeIdentifier Name */
    public VarLikeIdentifier $name;
    /** @var null|Node\Expr Default */
    public ?Expr $default;

    /**
     * Constructs a class property item node.
     *
     * @param string|Node\VarLikeIdentifier $name Name
     * @param null|Node\Expr $default Default value
     * @param \PhpParser\NodeAttributes|AttributeArray $attributes Additional attributes
     */
    public function __construct($name, ?Node\Expr $default = null, \PhpParser\NodeAttributes|array $attributes = []) {
        $this->attributes = \PhpParser\NodeAttributes::from($attributes);
        $this->name = \is_string($name) ? new Node\VarLikeIdentifier($name) : $name;
        $this->default = $default;
    }

    /** @return list<string> */
    public function getSubNodeNames(): array {
        return ['name', 'default'];
    }

    /** @return \PhpParser\Node|list<\PhpParser\Node|null>|string|int|float|bool|null */
    public function getSubNode(string $name): mixed {
        return match ($name) {
            'name' => $this->name,
            'default' => $this->default,
            default => throw new \LogicException('Unknown sub node ' . $name . ' on ' . static::class),
        };
    }

    /** @param \PhpParser\Node|list<\PhpParser\Node|null>|string|int|float|bool|null $value */
    public function setSubNode(string $name, mixed $value): void {
        switch ($name) {
            case 'name':
                $this->name = $value;
                return;
            case 'default':
                $this->default = $value;
                return;
        }
        throw new \LogicException('Unknown sub node ' . $name . ' on ' . static::class);
    }

    public function getType(): string {
        return 'PropertyItem';
    }
}

// @deprecated compatibility alias
class_alias(PropertyItem::class, Stmt\PropertyProperty::class);
