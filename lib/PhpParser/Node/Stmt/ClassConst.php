<?php declare(strict_types=1);

namespace PhpParser\Node\Stmt;

use PhpParser\Modifiers;
use PhpParser\Node;

/**
 * @psalm-import-type AttributeArray from \PhpParser\NodeAttributes
 */
class ClassConst extends Node\Stmt {
    /** @var int Modifiers */
    public int $flags;
    /** @var list<Node\Const_> Constant declarations */
    public array $consts;
    /** @var list<Node\AttributeGroup> PHP attribute groups */
    public array $attrGroups;
    /** @var Node\Identifier|Node\Name|Node\ComplexType|null Type declaration */
    public ?Node $type;

    /**
     * Constructs a class const list node.
     *
     * @param list<Node\Const_> $consts Constant declarations
     * @param int $flags Modifiers
     * @param \PhpParser\NodeAttributes|AttributeArray $attributes Additional attributes
     * @param list<Node\AttributeGroup> $attrGroups PHP attribute groups
     * @param null|Node\Identifier|Node\Name|Node\ComplexType $type Type declaration
     */
    public function __construct(
        array $consts,
        int $flags = 0,
        \PhpParser\NodeAttributes|array $attributes = [],
        array $attrGroups = [],
        ?Node $type = null
    ) {
        $this->attributes = \PhpParser\NodeAttributes::from($attributes);
        $this->flags = $flags;
        $this->consts = $consts;
        $this->attrGroups = $attrGroups;
        $this->type = $type;
    }

    /** @return list<string> */
    public function getSubNodeNames(): array {
        return ['attrGroups', 'flags', 'type', 'consts'];
    }

    /** @return \PhpParser\Node|list<\PhpParser\Node|null>|string|int|float|bool|null */
    public function getSubNode(string $name): mixed {
        return match ($name) {
            'attrGroups' => $this->attrGroups,
            'flags' => $this->flags,
            'type' => $this->type,
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
            case 'flags':
                $this->flags = $value;
                return;
            case 'type':
                $this->type = $value;
                return;
            case 'consts':
                $this->consts = $value;
                return;
        }
        throw new \LogicException('Unknown sub node ' . $name . ' on ' . static::class);
    }

    /**
     * Whether constant is explicitly or implicitly public.
     */
    public function isPublic(): bool {
        return ($this->flags & Modifiers::PUBLIC) !== 0
            || ($this->flags & Modifiers::VISIBILITY_MASK) === 0;
    }

    /**
     * Whether constant is protected.
     */
    public function isProtected(): bool {
        return (bool) ($this->flags & Modifiers::PROTECTED);
    }

    /**
     * Whether constant is private.
     */
    public function isPrivate(): bool {
        return (bool) ($this->flags & Modifiers::PRIVATE);
    }

    /**
     * Whether constant is final.
     */
    public function isFinal(): bool {
        return (bool) ($this->flags & Modifiers::FINAL);
    }

    public function getType(): string {
        return 'Stmt_ClassConst';
    }
}
