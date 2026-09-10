<?php declare(strict_types=1);

namespace PhpParser\Node\Stmt;

use PhpParser\Modifiers;
use PhpParser\Node;

class Class_ extends ClassLike {
    /** @deprecated Use Modifiers::PUBLIC instead */
    public const MODIFIER_PUBLIC    =  1;
    /** @deprecated Use Modifiers::PROTECTED instead */
    public const MODIFIER_PROTECTED =  2;
    /** @deprecated Use Modifiers::PRIVATE instead */
    public const MODIFIER_PRIVATE   =  4;
    /** @deprecated Use Modifiers::STATIC instead */
    public const MODIFIER_STATIC    =  8;
    /** @deprecated Use Modifiers::ABSTRACT instead */
    public const MODIFIER_ABSTRACT  = 16;
    /** @deprecated Use Modifiers::FINAL instead */
    public const MODIFIER_FINAL     = 32;
    /** @deprecated Use Modifiers::READONLY instead */
    public const MODIFIER_READONLY  = 64;

    /** @deprecated Use Modifiers::VISIBILITY_MASK instead */
    public const VISIBILITY_MODIFIER_MASK = 7; // 1 | 2 | 4

    /** @var int Modifiers */
    public int $flags;
    /** @var null|Node\Name Name of extended class */
    public ?Node\Name $extends;
    /** @var Node\Name[] Names of implemented interfaces */
    public array $implements;

    /**
     * Constructs a class node.
     *
     * @param string|Node\Identifier|null $name Name
     * @param array{
     *     flags?: int,
     *     type?: int,
     *     extends?: Node\Name|null,
     *     implements?: Node\Name[],
     *     stmts?: Node\Stmt[],
     *     attrGroups?: Node\AttributeGroup[],
     * } $subNodes Array of the following optional subnodes:
     *             'flags'       => 0      : Flags
     *             'extends'     => null   : Name of extended class
     *             'implements'  => array(): Names of implemented interfaces
     *             'stmts'       => array(): Statements
     *             'attrGroups'  => array(): PHP attribute groups
     * @param \PhpParser\NodeAttributes|\PhpParser\NodeAttributes::AttributeArray $attributes Additional attributes
     */
    public function __construct($name, array $subNodes = [], \PhpParser\NodeAttributes|array $attributes = []) {
        $this->attributes = \PhpParser\NodeAttributes::from($attributes);
        $this->flags = $subNodes['flags'] ?? $subNodes['type'] ?? 0;
        $this->name = \is_string($name) ? new Node\Identifier($name) : $name;
        $this->extends = $subNodes['extends'] ?? null;
        $this->implements = $subNodes['implements'] ?? [];
        $this->stmts = $subNodes['stmts'] ?? [];
        $this->attrGroups = $subNodes['attrGroups'] ?? [];
    }

    public function getSubNodeNames(): array {
        return ['attrGroups', 'flags', 'name', 'extends', 'implements', 'stmts'];
    }

    /** @return \PhpParser\Node|list<\PhpParser\Node|null>|string|int|float|bool|null */
    public function getSubNode(string $name): mixed {
        return match ($name) {
            'attrGroups' => $this->attrGroups,
            'flags' => $this->flags,
            'name' => $this->name,
            'extends' => $this->extends,
            'implements' => $this->implements,
            'stmts' => $this->stmts,
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
            case 'name':
                $this->name = $value;
                return;
            case 'extends':
                $this->extends = $value;
                return;
            case 'implements':
                $this->implements = $value;
                return;
            case 'stmts':
                $this->stmts = $value;
                return;
        }
        throw new \LogicException('Unknown sub node ' . $name . ' on ' . static::class);
    }

    /**
     * Whether the class is explicitly abstract.
     */
    public function isAbstract(): bool {
        return (bool) ($this->flags & Modifiers::ABSTRACT);
    }

    /**
     * Whether the class is final.
     */
    public function isFinal(): bool {
        return (bool) ($this->flags & Modifiers::FINAL);
    }

    public function isReadonly(): bool {
        return (bool) ($this->flags & Modifiers::READONLY);
    }

    /**
     * Whether the class is anonymous.
     */
    public function isAnonymous(): bool {
        return null === $this->name;
    }

    public function getType(): string {
        return 'Stmt_Class';
    }
}
