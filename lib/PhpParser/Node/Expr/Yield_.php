<?php declare(strict_types=1);

namespace PhpParser\Node\Expr;

use PhpParser\Node\Expr;

/**
 * @psalm-import-type AttributeArray from \PhpParser\NodeAttributes
 */
class Yield_ extends Expr {
    /** @var null|Expr Key expression */
    public ?Expr $key;
    /** @var null|Expr Value expression */
    public ?Expr $value;

    /**
     * Constructs a yield expression node.
     *
     * @param null|Expr $value Value expression
     * @param null|Expr $key Key expression
     * @param \PhpParser\NodeAttributes|AttributeArray $attributes Additional attributes
     */
    public function __construct(?Expr $value = null, ?Expr $key = null, \PhpParser\NodeAttributes|array $attributes = []) {
        $this->attributes = \PhpParser\NodeAttributes::from($attributes);
        $this->key = $key;
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
        return 'Expr_Yield';
    }
}
