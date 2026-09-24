<?php declare(strict_types=1);

namespace PhpParser\Node\Stmt;

use PhpParser\Node;
use PhpParser\Node\AttributeGroup;

/**
 * @psalm-import-type AttributeArray from \PhpParser\NodeAttributes
 */
class EnumCase extends Node\Stmt {
    /** @var Node\Identifier Enum case name */
    public Node\Identifier $name;
    /** @var Node\Expr|null Enum case expression */
    public ?Node\Expr $expr;
    /** @var list<Node\AttributeGroup> PHP attribute groups */
    public array $attrGroups;

    /**
     * @param string|Node\Identifier $name Enum case name
     * @param Node\Expr|null $expr Enum case expression
     * @param list<AttributeGroup> $attrGroups PHP attribute groups
     * @param \PhpParser\NodeAttributes|AttributeArray $attributes Additional attributes
     */
    public function __construct($name, ?Node\Expr $expr = null, array $attrGroups = [], \PhpParser\NodeAttributes|array $attributes = []) {
        parent::__construct($attributes);
        $this->name = \is_string($name) ? new Node\Identifier($name) : $name;
        $this->expr = $expr;
        $this->attrGroups = $attrGroups;
    }

    /** @return list<string> */
    public function getSubNodeNames(): array {
        return ['attrGroups', 'name', 'expr'];
    }

    /**
     * Every sub node by name, in getSubNodeNames() order (one call instead of one getSubNode() per name).
     *
     * @return array<string, Node|list<Node|null>|string|int|float|bool|null>
     */
    public function getSubNodes(): array {
        return ['attrGroups' => $this->attrGroups, 'name' => $this->name, 'expr' => $this->expr];
    }

    #[\Override]
    public function traverseSubNodes(\PhpParser\NodeTraverser $traverser): void {
        $this->attrGroups = $traverser->traverseArray($this->attrGroups);
        if ($traverser->stopTraversal) {
            return;
        }
        $this->name = $traverser->traverseRequiredChildNode($this->name);
        if ($traverser->stopTraversal) {
            return;
        }
        if ($this->expr !== null) {
            $this->expr = $traverser->traverseChildNode($this->expr);
            if ($traverser->stopTraversal) {
                return;
            }
        }
    }


    /** @return \PhpParser\Node|list<\PhpParser\Node|null>|string|int|float|bool|null */
    public function getSubNode(string $name): mixed {
        return match ($name) {
            'attrGroups' => $this->attrGroups,
            'name' => $this->name,
            'expr' => $this->expr,
            default => throw new \LogicException('Unknown sub node ' . $name . ' on ' . static::class),
        };
    }

    /** @param \PhpParser\Node|list<\PhpParser\Node|null>|string|int|float|bool|null $value */
    public function setSubNode(string $name, mixed $value): void {
        switch ($name) {
            case 'attrGroups':
                $this->attrGroups = $value;
                return;
            case 'name':
                $this->name = $value;
                return;
            case 'expr':
                $this->expr = $value;
                return;
        }
        throw new \LogicException('Unknown sub node ' . $name . ' on ' . static::class);
    }

    public function getType(): string {
        return 'Stmt_EnumCase';
    }
}
