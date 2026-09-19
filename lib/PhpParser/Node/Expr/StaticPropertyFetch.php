<?php declare(strict_types=1);

namespace PhpParser\Node\Expr;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Name;
use PhpParser\Node\VarLikeIdentifier;

/**
 * @psalm-import-type AttributeArray from \PhpParser\NodeAttributes
 */
class StaticPropertyFetch extends Expr {
    /** @var Name|Expr Class name */
    public Node $class;
    /** @var VarLikeIdentifier|Expr Property name */
    public Node $name;

    /**
     * Constructs a static property fetch node.
     *
     * @param Name|Expr $class Class name
     * @param string|VarLikeIdentifier|Expr $name Property name
     * @param \PhpParser\NodeAttributes|AttributeArray $attributes Additional attributes
     */
    public function __construct(Node $class, $name, \PhpParser\NodeAttributes|array $attributes = []) {
        $this->attributes = \PhpParser\NodeAttributes::from($attributes);
        $this->class = $class;
        $this->name = \is_string($name) ? new VarLikeIdentifier($name) : $name;
    }

    /** @return list<string> */
    public function getSubNodeNames(): array {
        return ['class', 'name'];
    }

    /** @return \PhpParser\Node|list<\PhpParser\Node|null>|string|int|float|bool|null */
    public function getSubNode(string $name): mixed {
        return match ($name) {
            'class' => $this->class,
            'name' => $this->name,
            default => throw new \LogicException('Unknown sub node ' . $name . ' on ' . static::class),
        };
    }

    /** @param \PhpParser\Node|list<\PhpParser\Node|null>|string|int|float|bool|null $value */
    public function setSubNode(string $name, mixed $value): void {
        switch ($name) {
            case 'class':
                $this->class = $value;
                return;
            case 'name':
                $this->name = $value;
                return;
        }
        throw new \LogicException('Unknown sub node ' . $name . ' on ' . static::class);
    }

    public function getType(): string {
        return 'Expr_StaticPropertyFetch';
    }
}
