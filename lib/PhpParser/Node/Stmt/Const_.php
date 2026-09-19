<?php declare(strict_types=1);

namespace PhpParser\Node\Stmt;

use PhpParser\Node;

/**
 * @psalm-import-type AttributeArray from \PhpParser\NodeAttributes
 */
class Const_ extends Node\Stmt {
    /** @var list<Node\Const_> Constant declarations */
    public array $consts;
    /** @var list<Node\AttributeGroup> PHP attribute groups */
    public array $attrGroups;

    /**
     * Constructs a const list node.
     *
     * @param list<Node\Const_> $consts Constant declarations
     * @param \PhpParser\NodeAttributes|AttributeArray $attributes Additional attributes
     * @param list<Node\AttributeGroup> $attrGroups PHP attribute groups
     */
    public function __construct(
        array $consts,
        \PhpParser\NodeAttributes|array $attributes = [],
        array $attrGroups = []
    ) {
        $this->attributes = \PhpParser\NodeAttributes::from($attributes);
        $this->attrGroups = $attrGroups;
        $this->consts = $consts;
    }

    /** @return list<string> */
    public function getSubNodeNames(): array {
        return ['attrGroups', 'consts'];
    }

    /** @return \PhpParser\Node|list<\PhpParser\Node|null>|string|int|float|bool|null */
    public function getSubNode(string $name): mixed {
        return match ($name) {
            'attrGroups' => $this->attrGroups,
            'consts' => $this->consts,
            default => throw new \LogicException('Unknown sub node ' . $name . ' on ' . static::class),
        };
    }

    /** @param \PhpParser\Node|list<\PhpParser\Node|null>|string|int|float|bool|null $value */
    public function setSubNode(string $name, mixed $value): void {
        switch ($name) {
            case 'attrGroups':
                $this->attrGroups = $value;
                return;
            case 'consts':
                $this->consts = $value;
                return;
        }
        throw new \LogicException('Unknown sub node ' . $name . ' on ' . static::class);
    }

    public function getType(): string {
        return 'Stmt_Const';
    }
}
