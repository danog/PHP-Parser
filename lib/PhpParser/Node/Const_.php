<?php declare(strict_types=1);

namespace PhpParser\Node;

use PhpParser\NodeAbstract;

/**
 * @psalm-import-type AttributeArray from \PhpParser\NodeAttributes
 */
class Const_ extends NodeAbstract {
    /** @var Identifier Name */
    public Identifier $name;
    /** @var Expr Value */
    public Expr $value;

    /** @var Name|null Namespaced name (if using NameResolver) */
    public ?Name $namespacedName;

    /**
     * Constructs a const node for use in class const and const statements.
     *
     * @param string|Identifier $name Name
     * @param Expr $value Value
     * @param \PhpParser\NodeAttributes|AttributeArray $attributes Additional attributes
     */
    public function __construct($name, Expr $value, \PhpParser\NodeAttributes|array $attributes = []) {
        $this->attributes = \PhpParser\NodeAttributes::from($attributes);
        $this->name = \is_string($name) ? new Identifier($name) : $name;
        $this->value = $value;
    }

    /** @return list<string> */
    public function getSubNodeNames(): array {
        return ['name', 'value'];
    }

    /** @return \PhpParser\Node|list<\PhpParser\Node|null>|string|int|float|bool|null */
    public function getSubNode(string $name): mixed {
        return match ($name) {
            'name' => $this->name,
            'value' => $this->value,
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
        }
        throw new \LogicException('Unknown sub node ' . $name . ' on ' . static::class);
    }

    public function getType(): string {
        return 'Const';
    }
}
