<?php declare(strict_types=1);

namespace PhpParser\Node\Expr;

use PhpParser\Node\Expr;

/**
 * @psalm-import-type AttributeArray from \PhpParser\NodeAttributes
 */
class Include_ extends Expr {
    public const TYPE_INCLUDE      = 1;
    public const TYPE_INCLUDE_ONCE = 2;
    public const TYPE_REQUIRE      = 3;
    public const TYPE_REQUIRE_ONCE = 4;

    /** @var Expr Expression */
    public Expr $expr;
    /** @var int Type of include */
    public int $type;

    /**
     * Constructs an include node.
     *
     * @param Expr $expr Expression
     * @param int $type Type of include
     * @param \PhpParser\NodeAttributes|AttributeArray $attributes Additional attributes
     */
    public function __construct(Expr $expr, int $type, \PhpParser\NodeAttributes|array $attributes = []) {
        $this->attributes = $attributes instanceof \PhpParser\NodeAttributes ? $attributes : \PhpParser\NodeAttributes::fromArray($attributes);
        $this->expr = $expr;
        $this->type = $type;
    }

    /** @return list<string> */
    public function getSubNodeNames(): array {
        return ['expr', 'type'];
    }

    /**
     * Every sub node by name, in getSubNodeNames() order (one call instead of one getSubNode() per name).
     *
     * @return array<string, Node|list<Node|null>|string|int|float|bool|null>
     */
    public function getSubNodes(): array {
        return ['expr' => $this->expr, 'type' => $this->type];
    }


    /** @return \PhpParser\Node|list<\PhpParser\Node|null>|string|int|float|bool|null */
    public function getSubNode(string $name): mixed {
        return match ($name) {
            'expr' => $this->expr,
            'type' => $this->type,
            default => throw new \LogicException('Unknown sub node ' . $name . ' on ' . static::class),
        };
    }

    /** @param \PhpParser\Node|list<\PhpParser\Node|null>|string|int|float|bool|null $value */
    public function setSubNode(string $name, mixed $value): void {
        switch ($name) {
            case 'expr':
                $this->expr = $value;
                return;
            case 'type':
                $this->type = $value;
                return;
        }
        throw new \LogicException('Unknown sub node ' . $name . ' on ' . static::class);
    }

    public function getType(): string {
        return 'Expr_Include';
    }
}
